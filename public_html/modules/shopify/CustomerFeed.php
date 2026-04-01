<?php
declare(strict_types=1);

namespace app\modules\shopify;

use app\models\Queue;
use app\models\Customers;
use app\models\Product as BaseProduct;
use app\modules\shopify\models\Product;
use app\modules\shopify\models\Customer;
use app\modules\shopify\ApiClient;
use Shopify\Clients\Graphql;
use Shopify\Utils;
use app\modules\xml_generator\src\XmlFeed;
use SimpleXMLElement;
use Throwable;
use stdClass;
use Exception;
use app\models\IntegrationData;
use app\modules\shopify\models\CustomerXml;

// use app\models\Product;

class CustomerFeed extends XmlFeed
{
    const API_RESULT_COUNT = 40;  // default 100
    const XML_PAGE_SIZE = 100; // default 100

    const STATUS_OK = 1;
    const STATUS_FAIL = 0;
    const STATUS_FINISHED = 10;

    private $client;

    public function generate($processType = null): int
    {
        if ($this->_user->config->get('feed_enabled') == 0) {
            throw new Exception('Feed disabled');
        }

        if ($processType == 'objects') {
            return $this->processData();
        }

        $file = $this->getFile(true, false);
        $temp = $this->getFile(true, true);

        if (!$this->isFinished()) {
            $created = $this->prepareCustomerXml($file, $temp);
        } else {
            $created = $this->createCustomerXml($file, $temp);
        }

        return $created;
    }

    /**
     * @param $temp
     * @param $file
     *
     * @return bool|\SimpleXMLElement|null
     *
     * @throws \Exception
     */
    protected function prepareCustomerXml($file, $temp)
    {
        $integrationDataCurrentPage = $this->_queue->page;
        $integrationDataMaxPage = $this->_queue->max_page;
        $page_size = self::XML_PAGE_SIZE;

        $query = Customers::find()->where(['user_id' => $this->_queue->getCurrentUser()->id]);

        $page = $integrationDataCurrentPage;

        if ($integrationDataMaxPage == 0) {
            $customers_all = $query->count();
            $pages = ceil($customers_all / $page_size);
            $this->_queue->max_page = $pages;
            $integrationDataMaxPage = $pages;
            $this->_queue->page = $page;
            $this->_queue->save();
        }

        $res = $query->limit($page_size)->offset(($page) * $page_size)->all();

        try {
            $customers_str = "";

            foreach ($res as $customer) {
                /** @var Customers $customer */

                // ommit allegro etc
                if (Queue::isDisallowedEmail($customer['email'])) {
                    continue;
                }

                if ($customer->isCustomerValidForXml() == false) {
                    continue;
                }

                $xmlEntity = CustomerXml::getEntity($customer, $this->_user);

                if (!$xmlEntity) {
                    continue;
                }

                $customers_str .= $xmlEntity;
            }

            $file_handle = fopen($temp, 'a+');
            fwrite($file_handle, $customers_str);
            fclose($file_handle);
        } catch (Exception $e) {
            // TODO: log
            return self::STATUS_FAIL;
        }

        $page++;

        $this->_queue->page = $page;
        $this->_queue->save();

        if ($page > (int) $integrationDataMaxPage) {
            return $this->createCustomerXml($file, $temp);
        }

        return self::STATUS_OK;
    }

    private function createCustomerXml(string $file, string $temp)
    {
        $customers = new SimpleXMLElement('<CUSTOMERS/>');
        $customers->addChild('CUSTOMER');
        file_put_contents($file, str_replace('<CUSTOMER/>', file_get_contents($temp), $customers->asXML()));
        file_put_contents($temp, '');
        return is_file($file) ? self::STATUS_FINISHED : self::STATUS_FAIL;
    }

    private function getAllItemsCount()
    {
        $queries = [];

        if (IntegrationData::getData('last_customer_integration_date', $this->_user->id)) {
            $queries[] = 'updated_at:>' . IntegrationData::getLastCustomerIntegrationDate($this->_user->id);
        }

        $query = '"' . implode(" ", $queries) . '"';

        $graphQL = <<<Query
            query {
                customersCount(limit: null, query: {$query}) {
                    count
                    precision
                }
            }
        Query;

        try {
            $result = $this->client->GraphQL->post($graphQL);

            return $result['data']['customersCount']['count'];
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

        $queries = [];

        if (IntegrationData::getData('last_customer_integration_date', $this->_user->id)) {
            $queries[] = 'updated_at:>' . IntegrationData::getLastCustomerIntegrationDate($this->_user->id);
        }

        $query = '"' . implode(" ", $queries) . '"';

        if (!$after) {
            $graphQL = <<<Query
                query CustomerList {
                    customers(first: {$first}, query: {$query}) {
                        nodes {
                            id
                            firstName
                            lastName
                            defaultEmailAddress {
                                emailAddress
                                marketingState
                                marketingOptInLevel
                                marketingUpdatedAt
                            }
                            defaultPhoneNumber {
                                phoneNumber
                                marketingState
                                marketingCollectedFrom
                            }
                            createdAt
                            updatedAt
                            numberOfOrders
                            state
                            amountSpent {
                                amount
                                currencyCode
                            }
                            verifiedEmail
                            taxExempt
                            tags
                            addresses {
                                id
                                firstName
                                lastName
                                address1
                                city
                                province
                                country
                                zip
                                phone
                                name
                                provinceCode
                                countryCodeV2
                            }
                            defaultAddress {
                                id
                                address1
                                city
                                province
                                country
                                zip
                                phone
                                provinceCode
                                countryCodeV2
                            }
                            metafields(first: 20) {
                                nodes {
                                    id
                                    key
                                    definition {
                                        name
                                    }
                                    jsonValue
                                    value
                                }
                            }
                        }
                        pageInfo {
                            endCursor
                            hasNextPage
                        }
                    }
                }
            Query;
        } else {
            $graphQL = <<<Query
                query CustomerList {
                    customers(first: {$first}, after: {$after}, query: {$query}) {
                        nodes {
                            id
                            firstName
                            lastName
                            defaultEmailAddress {
                                emailAddress
                                marketingState
                                marketingOptInLevel
                                marketingUpdatedAt
                            }
                            defaultPhoneNumber {
                                phoneNumber
                                marketingState
                                marketingCollectedFrom
                            }
                            createdAt
                            updatedAt
                            numberOfOrders
                            state
                            amountSpent {
                                amount
                                currencyCode
                            }
                            verifiedEmail
                            taxExempt
                            tags
                            addresses {
                                id
                                firstName
                                lastName
                                address1
                                city
                                province
                                country
                                zip
                                phone
                                name
                                provinceCode
                                countryCodeV2
                            }
                            defaultAddress {
                                id
                                address1
                                city
                                province
                                country
                                zip
                                phone
                                provinceCode
                                countryCodeV2
                            }
                            metafields(first: 20) {
                                nodes {
                                    id
                                    key
                                    definition {
                                        name
                                    }
                                    jsonValue
                                    value
                                }
                            }
                        }
                        pageInfo {
                            endCursor
                            hasNextPage
                        }
                    }
                }
            Query;
        }

        try {
            $result = $this->client->GraphQL->post($graphQL);

            $items = $result['data']['customers']['nodes'];
            $pageInfo = $result['data']['customers']['pageInfo'];

            $this->setPaginationInfo($pageInfo);

            return ['status' => 'success', 'paginationInfo' => $pageInfo, 'customers' => $items];
        } catch (Exception $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    private function checkQueueConstraints()
    {
        // if ($this->_queue->max_page === $this->_queue->page && $this->_queue->max_page !== 0) {
        //     // IntegrationData::setData('last_products_integration_date', date('Y-m-d'), $this->_user->id);
        //     IntegrationData::setData('last_customer_integration_date', date('Y-m-d'), $this->_user->id);
        // }

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

    private function checkExportType()
    {
        if ($this->_user->config->get('export_type') == 1) { // incremental
            if ($this->_queue->page == 0) {
                Customers::deleteAll(['user_id' => $this->_user->id]); // delete all obsolete entries
            }

            $date2weeksago = date('Y-m-d', strtotime('-2 weeks'));
            IntegrationData::setLastCustomerIntegrationDate($date2weeksago, $this->_user->id);
        }
    }

    private function processData()
    {
        $session = $this->_user->getSession();
        
        if (!$session) {
            return self::STATUS_FAIL;
        }
        
        $this->client = ApiClient::getClient($session);

        $this->checkExportType();

        $checkStatus = $this->checkQueueConstraints();

        if (!$checkStatus) {
            return self::STATUS_FAIL;
        }

        $fetchResult = $this->fetchItems();

        if ($fetchResult['status'] === 'fail') {
            return self::STATUS_FAIL;
        }

        // if empty result == done
        if (empty($fetchResult['customers'])) {
            $this->_queue->max_page = $this->_queue->page;
            $this->_queue->save();
            IntegrationData::setLastCustomerIntegrationDate(date('Y-m-d'), $this->_user->id);
            IntegrationData::setData('INITIAL_CUSTOMERS_DONE', "1", $this->_user->id);
            return self::STATUS_FINISHED;
        }

        // if out of scope then finish
        if ($this->_queue->page >= $this->_queue->max_page) {
            IntegrationData::setLastCustomerIntegrationDate(date('Y-m-d'), $this->_user->id);
            IntegrationData::setData('INITIAL_CUSTOMERS_DONE', "1", $this->_user->id);
            // IntegrationData::setIsNew('CUSTOMER', 0, $this->_user->id);
            return self::STATUS_FINISHED;
        }

        try {
            foreach ($fetchResult['customers'] as $customer) {
                $isValid = $this->validateCustomer($customer);

                if (!$isValid) {
                    continue;
                }

                $customerModel = new Customer($customer, $this->_user); 

                if (!$customerModel->prepareFromApi()) {
                    $this->_queue->setErrorStatus('Błąd zapisu klienta');
                    return self::STATUS_FAIL;
                }
            }

            // if there is no next page then finish
            if ($fetchResult['paginationInfo']['hasNextPage'] === false) {
                $this->_queue->max_page = $this->_queue->page;
                $this->_queue->save();
                IntegrationData::setLastCustomerIntegrationDate(date('Y-m-d'), $this->_user->id);
                IntegrationData::setData('INITIAL_CUSTOMERS_DONE', "1", $this->_user->id);
                return self::STATUS_FINISHED;
            }

            $this->_queue->increasePage();
            return self::STATUS_OK;
        } catch (Exception $e) {
            return self::STATUS_FAIL;
        }
    }

    private function getEmailApproval($customer)
    {
        if (!$customer['defaultEmailAddress'] || !$customer['defaultEmailAddress']['marketingState']) {
            return false;
        }

        return $customer['defaultEmailAddress']['marketingState'] === 'SUBSCRIBED' ? true : false;
    }

    private function getSmsApproval($customer)
    {
        if (!$customer['defaultPhoneNumber'] || !$customer['defaultPhoneNumber']['marketingState']) {
            return false;
        }

        return $customer['defaultPhoneNumber']['marketingState'] === 'SUBSCRIBED' ? true : false;
    }

    private function validateCustomer($customer)
    {
        $emailApproval = $this->getEmailApproval($customer);
        $smsApproval = $this->getSmsApproval($customer);

        if (!$emailApproval && !$smsApproval) {
            return false;
        }

        return true;
    }
}
