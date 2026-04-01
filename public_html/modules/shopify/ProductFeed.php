<?php
declare(strict_types=1);

namespace app\modules\shopify;

use app\models\Product as BaseProduct;
use app\modules\shopify\models\Product;
use app\modules\shopify\ApiClient;
use app\modules\xml_generator\src\XmlFeed;
use SimpleXMLElement;
use Exception;
use app\models\IntegrationData;
use app\modules\shopify\models\ProductXml;

class ProductFeed extends XmlFeed
{
    const API_RESULT_COUNT = 5;  // default 100
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
            $created = $this->prepareProductXml($file, $temp);
        } else {
            $created = $this->createProductXml($file, $temp);
        }

        return $created;
    }

    /**
     * @param $file
     * @param $temp
     *
     * @return bool
     */

    private function prepareProductXml($file, $temp)
    {
        $integrationDataCurrentPage = $this->_queue->page;
        $integrationDataMaxPage = $this->_queue->max_page;
        $page_size = self::XML_PAGE_SIZE;

        $query = BaseProduct::find()->where(['user_id' => $this->_queue->getCurrentUser()->id]);

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

        $products_str = "";

        try {
            foreach ($res as $product) {
                /** @var BaseProduct $product */

                $xmlEntity = ProductXml::getEntity($product, $this->_user);

                if (!$xmlEntity) {
                    continue;
                }

                $products_str .= $xmlEntity;
            }

            $file_handle = fopen($temp, 'a+');
            fwrite($file_handle, $products_str);
            fclose($file_handle);
        } catch (Exception $e) {
            // TODO: log
            return self::STATUS_FAIL;
        }

        $page++;

        $this->_queue->page = $page;
        $this->_queue->save();

        if ($page > (int) $integrationDataMaxPage) {
            return $this->createProductXml($file, $temp);
        }

        return self::STATUS_OK;
    }

    private function createProductXml($file, $temp): int
    {
        $products = new SimpleXMLElement('<PRODUCTS/>');
        $products->addChild('PRODUCT');
        file_put_contents($file, str_replace('<PRODUCT/>', file_get_contents($temp), $products->asXML()));
        file_put_contents($temp, '');
        return is_file($file) ? self::STATUS_FINISHED : self::STATUS_FAIL;
    }

    private function getAllProductsCount()
    {
        $queries = ['status:active'];

        // --- Enable incremental fetching after initial full database fetch
        if (IntegrationData::getData('INITIAL_PRODUCTS_DONE', $this->_user->id)) {
            $queries[] = 'updated_at:>' . IntegrationData::getDataValue('last_products_integration_date', $this->_user->id);
        }

        $query = '"' . implode(" ", $queries) . '"';

        $graphQL = <<<Query
            query {
                productsCount(limit: null, query: {$query}) {
                    count
                    precision
                }
            }
        Query;

        try {
            $result = $this->client->GraphQL->post($graphQL);

            return $result['data']['productsCount']['count'];
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

    private function fetchProducts()
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

        $queries = ['status:active'];

        // --- Enable incremental fetching after initial full database fetch
        if (IntegrationData::getData('INITIAL_PRODUCTS_DONE', $this->_user->id)) {
            $queries[] = 'updated_at:>' . IntegrationData::getDataValue('last_products_integration_date', $this->_user->id);
        }

        $query = '"' . implode(" ", $queries) . '"';

        if (!$after) {
            $graphQL = <<<Query
                query GetProducts {
                    products(first: {$first}, query: {$query}) {
                        nodes {
                            id
                            title
                            handle
                            description
                            totalInventory
                            hasOnlyDefaultVariant
                            vendor
                            category {
                                id
                                name
                            }
                            priceRangeV2 {
                                minVariantPrice {
                                    amount
                                }
                            }
                            compareAtPriceRange {
                                minVariantCompareAtPrice {
                                amount
                                }
                            }
                            featuredMedia {
                                preview {
                                    image {
                                        altText
                                        url(transform: { maxWidth: 300, maxHeight: 300 })
                                    }
                                }
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
                                    type
                                    reference {
                                        __typename
                                        ... on Metaobject {
                                            id
                                            handle
                                            type
                                            fields {
                                                key
                                                value
                                            }
                                        }
                                        ... on Collection {
                                            title
                                        }
                                    }
                                    references(first: 10) {
                                        nodes {
                                            __typename
                                            ... on Metaobject {
                                                id
                                                handle
                                                type
                                                fields { 
                                                    key 
                                                    value 
                                                }
                                            }
                                            ... on Collection {
                                                title
                                            }
                                        }
                                    }
                                }
                            }
                            variants(first: 10) {
                                nodes {
                                    id
                                    title
                                    price
                                    compareAtPrice
                                    availableForSale
                                    inventoryQuantity
                                    image {
                                        url(transform: { maxWidth: 300, maxHeight: 300 })
                                    }
                                    metafields(first: 10) {
                                        nodes {
                                            id
                                            key
                                            definition {
                                                name
                                            }
                                            jsonValue
                                            value
                                            type
                                            reference {
                                                __typename
                                                ... on Metaobject {
                                                    id
                                                    handle
                                                    type
                                                    fields {
                                                    key
                                                    value
                                                    }
                                                }
                                                ... on Collection {
                                                    title
                                                }
                                            }
                                            references(first: 2) {
                                                nodes {
                                                    __typename
                                                    ... on Metaobject {
                                                        id
                                                        handle
                                                        type
                                                        fields { 
                                                            key 
                                                            value 
                                                        }
                                                    }
                                                    ... on Collection {
                                                        title
                                                    }
                                                }
                                            }
                                        }
                                    }
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
                query GetProducts {
                    products(first: {$first}, after: {$after}, query: {$query}) {
                        nodes {
                            id
                            title
                            handle
                            description
                            totalInventory
                            hasOnlyDefaultVariant
                            vendor
                            category {
                                id
                                name
                            }
                            priceRangeV2 {
                                minVariantPrice {
                                    amount
                                }
                            }
                            compareAtPriceRange {
                                minVariantCompareAtPrice {
                                amount
                                }
                            }
                            featuredMedia {
                                preview {
                                    image {
                                        altText
                                        url(transform: { maxWidth: 300, maxHeight: 300 })
                                    }
                                }
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
                                    type
                                    reference {
                                        __typename
                                        ... on Metaobject {
                                            id
                                            handle
                                            type
                                            fields {
                                                key
                                                value
                                            }
                                        }
                                        ... on Collection {
                                            title
                                        }
                                    }
                                    references(first: 10) {
                                        nodes {
                                            __typename
                                            ... on Metaobject {
                                                id
                                                handle
                                                type
                                                fields { 
                                                    key 
                                                    value 
                                                }
                                            }
                                            ... on Collection {
                                                title
                                            }
                                        }
                                    }
                                }
                            }
                            variants(first: 10) {
                                nodes {
                                    id
                                    title
                                    price
                                    compareAtPrice
                                    availableForSale
                                    inventoryQuantity
                                    image {
                                        url(transform: { maxWidth: 300, maxHeight: 300 })
                                    }
                                    metafields(first: 10) {
                                        nodes {
                                            id
                                            key
                                            definition {
                                                name
                                            }
                                            jsonValue
                                            value
                                            type
                                            reference {
                                                __typename
                                                ... on Metaobject {
                                                    id
                                                    handle
                                                    type
                                                    fields {
                                                    key
                                                    value
                                                    }
                                                }
                                                ... on Collection {
                                                    title
                                                }
                                            }
                                            references(first: 2) {
                                                nodes {
                                                    __typename
                                                    ... on Metaobject {
                                                        id
                                                        handle
                                                        type
                                                        fields { 
                                                            key 
                                                            value 
                                                        }
                                                    }
                                                    ... on Collection {
                                                        title
                                                    }
                                                }
                                            }
                                        }
                                    }
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

            $products = $result['data']['products']['nodes'];
            $pageInfo = $result['data']['products']['pageInfo'];

            $this->setPaginationInfo($pageInfo);

            return ['status' => 'success', 'paginationInfo' => $pageInfo, 'products' => $products];
        } catch (Exception $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }

    // private function checkQueueConstraints()
    // {
    //     // todo

    //     if ($this->_queue->max_page == $this->_queue->page && $this->_queue->max_page != 0) {
    //         IntegrationData::setData('last_products_integration_date', date('Y-m-d'), $this->_user->id);
    //     }

    //     if ($this->_queue->max_page > 0) {
    //         return false; // no need every time
    //     }
    //     $request                           = $this->request_parameters;
    //     $request['params']['resultsLimit'] = 1;
    //     $response                          = $this->_client->post($this->apiMethod, $request);
    //     // var_dump($request);
    //     // var_dump($response['resultsNumberAll']);
    //     // die("!!!");
    //     if (! $response['resultsNumberAll']) {
    //         // echo "Res no: ";
    //         var_dump($response);
    //         echo "no results" . PHP_EOL;
    //         return false;
    //     }

    //     $maxPage = ceil($response['resultsNumberAll'] / self::API_RESULT_COUNT);
    //     if ($this->_queue->max_page < $maxPage) {
    //         $this->_queue->max_page = $maxPage;
    //         $this->_queue->save();
    //     }

    //     return true;
    // }

    private function checkQueueConstraints()
    {
        if ($this->_queue->max_page === $this->_queue->page && $this->_queue->max_page !== 0) {
            IntegrationData::setData('last_products_integration_date', date('Y-m-d'), $this->_user->id);
        }

        if ($this->_queue->max_page > 0) {
            return true; // no need every time
        }

        $count = $this->getAllProductsCount();

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
                BaseProduct::deleteAll(['user_id' => $this->_user->id]); // delete all obsolete entries
            }

            $date2weeksago = date('Y-m-d', strtotime('-2 weeks'));
            IntegrationData::setLastProductIntegrationDate($date2weeksago, $this->_user->id);
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

        $fetchResult = $this->fetchProducts();


        if ($fetchResult['status'] === 'fail') {
            return self::STATUS_FAIL;
        }

        // if empty result == done
        if (empty($fetchResult['products'])) {
            $this->_queue->max_page = $this->_queue->page;
            $this->_queue->save();
            IntegrationData::setData('last_products_integration_date', date('Y-m-d'), $this->_user->id);
            IntegrationData::setData('INITIAL_PRODUCTS_DONE', "1", $this->_user->id);
            return self::STATUS_FINISHED;
        }

        // if out of scope then finish
        if ($this->_queue->page >= $this->_queue->max_page) {
            IntegrationData::setData('last_products_integration_date', date('Y-m-d'), $this->_user->id);
            IntegrationData::setData('INITIAL_PRODUCTS_DONE', "1", $this->_user->id);
            return self::STATUS_FINISHED;
        }

        try {
            foreach ($fetchResult['products'] as $product) {
                $productModel = new Product($product, $this->_user); 
                if (!$productModel->prepareFromApi()) {
                    $this->_queue->setErrorStatus('Błąd zapisu produktu');
                    return self::STATUS_FAIL;
                }
            }

            // if there is no next page then finish
            if ($fetchResult['paginationInfo']['hasNextPage'] === false) {
                $this->_queue->max_page = $this->_queue->page;
                $this->_queue->save();
                IntegrationData::setData('last_products_integration_date', date('Y-m-d'), $this->_user->id);
                IntegrationData::setData('INITIAL_PRODUCTS_DONE', "1", $this->_user->id);
                return self::STATUS_FINISHED;
            }

            $this->_queue->increasePage();
            return self::STATUS_OK;
        } catch (Exception $e) {
            return self::STATUS_FAIL;
        }
    }
}
