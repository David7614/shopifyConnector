<?php
declare(strict_types=1);

namespace app\modules\shopify;

use app\models\Orders;
use app\models\Queue;
use app\modules\shopify\ApiClient;
use app\modules\xml_generator\src\XmlFeed;
use app\services\FeedStorageService;
use SimpleXMLElement;
use Exception;
use app\models\IntegrationData;
use app\modules\shopify\models\Order;
use app\modules\shopify\models\OrderXml;

class OrderFeed extends XmlFeed
{
    const API_RESULT_COUNT = 100;  // default 100
    const XML_PAGE_SIZE = 100; // default 100

    const STATUS_OK = 1;
    const STATUS_FAIL = 0;
    const STATUS_FINISHED = 10;

    /** S3/MinIO requires every part except the last to be at least 5 MB. */
    const MULTIPART_CHUNK_THRESHOLD = 5 * 1024 * 1024;

    private $client;

    public function generate($processType = null): int
    {
        if ($processType == 'objects') {
            return $this->processData();
        }

        if (FeedStorageService::isConfigured()) {
            return $this->generateXmlViaStorage();
        }

        $file = $this->getFile(true, false);
        $temp = $this->getFile(true, true);

        if (!$this->isFinished()) {
            $created = $this->prepareOrderXml($file, $temp);
        } else {
            $created = $this->createOrderXml($file, $temp);
        }

        return $created;
    }

    private function getStorageKey(bool $temp = false): string
    {
        $ext = $temp ? '.xml.tmp' : '.xml';
        return 'order/' . $this->_user->uuid . '/order' . $ext;
    }

    /**
     * Per-page fragment written durably as its own small object — a page
     * is only ever considered done once this write succeeds, so an abrupt
     * interruption (loopQueue can be killed anytime) costs at most one
     * re-fetched page, never more.
     */
    private function getPartKey(int $page): string
    {
        return 'order/' . $this->_user->uuid . '/tmp/parts/' . $page . '.xml';
    }

    private function generateXmlViaStorage(): int
    {
        $storage = FeedStorageService::create();
        $fileKey = $this->getStorageKey(false);

        $this->migrateLegacyStorageIfNeeded($storage);

        if (!$this->isFinished()) {
            return $this->prepareOrderXmlViaStorage($storage, $fileKey);
        }

        return $this->createOrderXmlViaStorage($storage, $fileKey);
    }

    /**
     * Queues that were mid-flight when the per-page storage scheme shipped
     * accumulated their progress in the old single growing tempKey, which
     * this code no longer reads. If page > 0 but part 0 was never written,
     * the progress must predate this scheme — reset and redo from page 0.
     * Cheap: phase 2 only re-reads MySQL, no Shopify API calls involved.
     */
    private function migrateLegacyStorageIfNeeded(FeedStorageService $storage): void
    {
        if ($this->_queue->page <= 0) {
            return;
        }

        if ($storage->exists($this->getPartKey(0))) {
            return;
        }

        $legacyTempKey = $this->getStorageKey(true);
        if ($storage->exists($legacyTempKey)) {
            $storage->delete($legacyTempKey);
        }

        $this->_queue->page     = 0;
        $this->_queue->max_page = 0;
        $this->_queue->save();
    }

    private function prepareOrderXmlViaStorage(FeedStorageService $storage, string $fileKey): int
    {
        $page      = $this->_queue->page;
        $max_page  = $this->_queue->max_page;
        $page_size = self::XML_PAGE_SIZE;

        $query = Orders::find()->where(['user_id' => $this->_queue->getCurrentUser()->id]);

        if ($max_page == 0) {
            $total    = $query->count();
            $max_page = (int) ceil($total / $page_size);
            $this->_queue->max_page = $max_page;
            $this->_queue->page     = $page;
            $this->_queue->save();
        }

        $res = $query->limit($page_size)->offset($page * $page_size)->all();
        $orders_str = '';

        try {
            foreach ($res as $order) {
                if (Queue::isDisallowedEmail($order->email)) {
                    continue;
                }
                $xmlEntity = OrderXml::getEntity($order, $this->_user);
                if (!$xmlEntity) {
                    continue;
                }
                $orders_str .= $xmlEntity;
            }

            // Single put of just this page's content — no read-modify-write
            // of everything accumulated so far, unlike the old tempKey scheme.
            $storage->put($this->getPartKey($page), $orders_str);
        } catch (Exception $e) {
            return self::STATUS_FAIL;
        }

        $page++;
        $this->_queue->page = $page;
        $this->_queue->save();

        if ($page > (int) $max_page) {
            return $this->createOrderXmlViaStorage($storage, $fileKey);
        }

        return self::STATUS_OK;
    }

    /**
     * Assembles the final feed from the per-page parts via a streamed S3
     * multipart upload, so memory use stays bounded to one chunk
     * (~5 MB) regardless of how many orders/pages the feed has.
     * Retryable: nothing durable is deleted until CompleteMultipartUpload
     * succeeds, so a kill mid-assembly just redoes this step from parts
     * that are still sitting in storage.
     */
    private function createOrderXmlViaStorage(FeedStorageService $storage, string $fileKey): int
    {
        $max_page = (int) $this->_queue->max_page;

        $orders = new SimpleXMLElement('<ORDERS/>');
        $orders->addChild('ORDER');
        [$prefix, $suffix] = explode('<ORDER/>', $orders->asXML(), 2);

        $storage->abortStaleMultipartUploads($fileKey);
        $uploadId = $storage->createMultipartUpload($fileKey, 'application/xml');

        $parts      = [];
        $partNumber = 1;
        $buffer     = $prefix;

        try {
            for ($page = 0; $page < $max_page; $page++) {
                $buffer .= $storage->get($this->getPartKey($page));

                if (strlen($buffer) >= self::MULTIPART_CHUNK_THRESHOLD) {
                    $parts[] = [
                        'PartNumber' => $partNumber,
                        'ETag'       => $storage->uploadPart($fileKey, $uploadId, $partNumber, $buffer),
                    ];
                    $partNumber++;
                    $buffer = '';
                }
            }

            $buffer .= $suffix;
            $parts[] = [
                'PartNumber' => $partNumber,
                'ETag'       => $storage->uploadPart($fileKey, $uploadId, $partNumber, $buffer),
            ];

            $storage->completeMultipartUpload($fileKey, $uploadId, $parts);
        } catch (Exception $e) {
            $storage->abortMultipartUpload($fileKey, $uploadId);
            return self::STATUS_FAIL;
        }

        for ($page = 0; $page < $max_page; $page++) {
            $storage->delete($this->getPartKey($page));
        }

        return self::STATUS_FINISHED;
    }

    private function prepareOrderXml($file, $temp): int
    {
        $integrationDataCurrentPage = $this->_queue->page;
        $integrationDataMaxPage = $this->_queue->max_page;
        $page_size = self::XML_PAGE_SIZE;

        $query = Orders::find()->where(['user_id' => $this->_queue->getCurrentUser()->id]);

        $page = $integrationDataCurrentPage;

        if ($integrationDataMaxPage == 0) {
            $ordersQueryAll = $query->count();
            $pages = ceil($ordersQueryAll / $page_size);
            $this->_queue->max_page = $pages;
            $integrationDataMaxPage = $pages;
            $this->_queue->page = $page;
            $this->_queue->save();
        }

        $res = $query->limit($page_size)->offset(($page) * $page_size)->all();

        $orders_str = "";

        try {
            foreach ($res as $order) {
                /** @var Orders $order */

                // omit allegro etc
                if (Queue::isDisallowedEmail($order->email)) {
                    continue;
                }

                $xmlEntity = OrderXml::getEntity($order, $this->_user);

                if (!$xmlEntity) {
                    continue;
                }

                $orders_str .= $xmlEntity;
            }

            $file_handle = fopen($temp, 'a+');
            fwrite($file_handle, $orders_str);
            fclose($file_handle);
        } catch(Exception $e) {
            // TODO: log
            return self::STATUS_FAIL;
        }

        $page++;

        $this->_queue->page = $page;
        $this->_queue->save();

        if ($page > (int) $integrationDataMaxPage) {
            return $this->createOrderXml($file, $temp);
        }

        return self::STATUS_OK;
    }

    private function createOrderXml(string $file, string $temp)
    {
        $orders = new SimpleXMLElement('<ORDERS/>');
        $orders->addChild('ORDER');
        file_put_contents($file, str_replace('<ORDER/>', file_get_contents($temp), $orders->asXML()));
        file_put_contents($temp, '');
        return is_file($file) ? self::STATUS_FINISHED : self::STATUS_FAIL;

        file_put_contents($file, '');
        $fileContent = file_get_contents($temp);
        $file_handle = fopen($file, 'a+');
        fwrite($file_handle, '<?xml version="1.0"?> <ORDERS>');
        fwrite($file_handle, $fileContent);
        fwrite($file_handle, "</ORDERS>");
        fclose($file_handle);
        file_put_contents($temp, '');
        return is_file($file) ? self::STATUS_FINISHED : self::STATUS_FAIL;
    }

    private function getAllItemsCount()
    {
        $queries = [];

        $lastOrderIntegrationDate = IntegrationData::getDataValue('last_orders_integration_date', $this->_user->id);

        if ($lastOrderIntegrationDate) {
            $queries[] = 'updated_at:>' . date('Y-m-d', strtotime($lastOrderIntegrationDate . " - 1 week"));
        } else {
            if ($dateFrom = $this->_user->getConfig()->getOrdersDateFrom()) {
                $queries[] = 'updated_at:>' . date('Y-m-d', strtotime($dateFrom));
            }
        }

        $query = '"' . implode(" ", $queries) . '"';

        $graphQL = <<<Query
            query OrdersCount {
                ordersCount(limit: null, query: {$query}) {
                    count
                    precision
                }
            }
        Query;

        try {
            $result = $this->client->GraphQL->post($graphQL);
            return $result['data']['ordersCount']['count'];
        } catch (Exception $e) {
            return null;
        }
    }

    private function getPaginationInfo()
    {
        $parameters = $this->_queue->getAdditionalParameters();

        $params = [];

        if (isset($parameters['endCursor'])) {
            $params['endCursor'] = $parameters['endCursor'];
        }

        if (isset($parameters['hasNextPage'])) {
            $params['hasNextPage'] = $parameters['hasNextPage'];
        }

        return $params;
    }
        
    private function setPaginationInfo($pageInfo)
    {
        $parameters = $this->_queue->getAdditionalParameters();
        $parameters['endCursor'] = $pageInfo['endCursor'];
        $parameters['hasNextPage'] = $pageInfo['hasNextPage'];
        $parameters = $this->_queue->setAdditionalParameters($parameters);
    }

    private function fetchItems()
    {
        $paginationInfo = $this->getPaginationInfo();

        if (!empty($paginationInfo['hasNextPage']) && $paginationInfo['hasNextPage'] === false) {
            return [];
        }

        $cursor = null;

        if (!empty($paginationInfo['endCursor'])) {
            $cursor = $paginationInfo['endCursor'];
        }

        $first = self::API_RESULT_COUNT;
        $after = $cursor ? '"' . $cursor . '"' : null; 

        // $queries = ["email:* AND customer_id:*"];
        $queries = [];

        $lastOrderIntegrationDate = IntegrationData::getDataValue('last_orders_integration_date', $this->_user->id);

        if ($lastOrderIntegrationDate) {
            $queries[] = 'updated_at:>' . date('Y-m-d', strtotime($lastOrderIntegrationDate . " - 1 week"));
        } else {
            if ($dateFrom = $this->_user->getConfig()->getOrdersDateFrom()) {
                $queries[] = 'updated_at:>' . date('Y-m-d', strtotime($dateFrom));
            }
        }

        $query = '"' . implode(" ", $queries) . '"';

        if (!$after) {
            $graphQL = <<<Query
                query {
                    orders(first: {$first}, query: {$query}, sortKey: UPDATED_AT, reverse: true) {
                        nodes {
                            id
                            name
                            createdAt
                            closedAt
                            cancelledAt
                            updatedAt
                            displayFinancialStatus
                            displayFulfillmentStatus
                            totalPriceSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            subtotalPriceSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            email
                            customer {
                                id
                                firstName
                                lastName
                                defaultPhoneNumber {
                                    phoneNumber
                                }
                            }
                            shippingAddress {
                                address1
                                city
                                provinceCode
                                zip
                                phone
                            }
                            billingAddress {
                                address1
                                city
                                zip
                                countryCodeV2
                                phone
                            }
                            lineItems(first: 5) {
                                nodes {
                                    id
                                    name
                                    quantity
                                    sku
                                    discountedTotalSet {
                                        presentmentMoney {
                                            amount
                                        }
                                    }
                                    variant {
                                        id
                                        title
                                    }
                                }
                            }
                        }
                        pageInfo {
                            hasNextPage
                            hasPreviousPage
                            startCursor
                            endCursor
                        }
                    }
                }
            Query;
        } else {
            $graphQL = <<<Query
                query {
                    orders(first: {$first}, after: {$after}, query: {$query}, sortKey: UPDATED_AT, reverse: true) {
                        nodes {
                            id
                            name
                            createdAt
                            closedAt
                            cancelledAt
                            updatedAt
                            displayFinancialStatus
                            displayFulfillmentStatus
                            totalPriceSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            subtotalPriceSet {
                                shopMoney {
                                    amount
                                    currencyCode
                                }
                            }
                            email
                            customer {
                                id
                                firstName
                                lastName
                                defaultPhoneNumber {
                                    phoneNumber
                                }
                            }
                            shippingAddress {
                                address1
                                city
                                provinceCode
                                zip
                                phone
                            }
                            billingAddress {
                                address1
                                city
                                zip
                                countryCodeV2
                                phone
                            }
                            lineItems(first: 5) {
                                nodes {
                                    id
                                    name
                                    quantity
                                    sku
                                    discountedTotalSet {
                                        presentmentMoney {
                                            amount
                                        }
                                    }
                                    variant {
                                        id
                                        title
                                    }
                                }
                            }
                        }
                        pageInfo {
                            hasNextPage
                            hasPreviousPage
                            startCursor
                            endCursor
                        }
                    }
                }
            Query;
        }

        try {
            $result = $this->client->GraphQL->post($graphQL);

            $items = $result['data']['orders']['nodes'];
            $pageInfo = $result['data']['orders']['pageInfo'];

            $this->setPaginationInfo($pageInfo);

            return ['status' => 'success', 'paginationInfo' => $pageInfo, 'orders' => $items];
        } catch (Exception $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    private function checkQueueConstraints()
    {
        if ($this->_queue->max_page === $this->_queue->page && $this->_queue->max_page !== 0) {
            IntegrationData::setData('last_orders_integration_date', date('Y-m-d'), $this->_user->id);
        }

        // no need every time
        if ($this->_queue->max_page > 0) {
            return true; 
        }

        $count = $this->getAllItemsCount();

        if (!$count) {
            return false;
        }

        $maxPage = ceil($count / self::API_RESULT_COUNT);

        try {
            if ($this->_queue->max_page < $maxPage) {
                $this->_queue->max_page = $maxPage;
                $this->_queue->save();
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function processData()
    {
        $session = $this->_user->getSession();
        
        if (!$session) {
            return self::STATUS_FAIL;
        }
        
        $this->client = ApiClient::getClient($session);

        // --- Enable incremental fetching after initial full database fetch
            // if (IntegrationData::getData('INITIAL_PRODUCTS_DONE', $this->_user->id)) {
            //     $this->request_parameters['params']['productDate'] = [
            //         'productDateMode'  => 'modified',
            //         'productDateBegin' => IntegrationData::getDataValue('last_products_integration_date', $this->_user->id),
            //     ];
            // }

        if ($this->_user->config->get('export_type') == 1) { // incremental
            if ($this->_queue->page == 0) {
                Orders::deleteAll(['user_id' => $this->_user->id]); // delete all obsolete entries
            }
            $date2weeksago = date('Y-m-d', strtotime('-2 weeks'));
            IntegrationData::setLastOrdersIntegrationDate($date2weeksago, $this->_user->id);
        }

        $checkStatus = $this->checkQueueConstraints();

        if (!$checkStatus) {
            return self::STATUS_FAIL;
        }

        $fetchResult = $this->fetchItems();

        if ($fetchResult['status'] === 'fail') {
            return self::STATUS_FAIL;
        }

        // if empty result == done
        if (empty($fetchResult) || empty($fetchResult['orders'])) {
            $this->_queue->max_page = $this->_queue->page;
            $this->_queue->save();
            IntegrationData::setLastOrdersIntegrationDate(date('Y-m-d'), $this->_user->id);
            IntegrationData::setData('INITIAL_ORDERS_DONE', "1", $this->_user->id);
            return self::STATUS_FINISHED;
        }

        // if out of scope then finish
        if ($this->_queue->page >= $this->_queue->max_page) {
            IntegrationData::setLastOrdersIntegrationDate(date('Y-m-d'), $this->_user->id);
            IntegrationData::setData('INITIAL_ORDERS_DONE', "1", $this->_user->id);
            // IntegrationData::setIsNew('ORDER', false, $this->_user->id);
            return self::STATUS_FINISHED;
        }

        try {
            foreach ($fetchResult['orders'] as $order) {
                $orderModel = new Order($order, $this->_user); 
                if (!$orderModel->prepareFromApi()) {
                    $this->_queue->setErrorStatus('Błąd zapisu zamówienia');
                    return self::STATUS_FAIL;
                }
            }

            // if there is no next page then finish
            if ($fetchResult['paginationInfo']['hasNextPage'] === false) {
                $this->_queue->max_page = $this->_queue->page;
                $this->_queue->save();
                IntegrationData::setLastOrdersIntegrationDate(date('Y-m-d'), $this->_user->id);
                IntegrationData::setData('INITIAL_ORDERS_DONE', "1", $this->_user->id);
                return self::STATUS_FINISHED;
            }

            $this->_queue->increasePage();

            return self::STATUS_OK;
        } catch (Exception $e) {
            return self::STATUS_FAIL;
        }
    }
}

