<?php
declare(strict_types=1);

namespace app\modules\shopify\models;

use yii\base\Event;
use yii\db\Command;
use Exception;
use app\models\User;
use app\models\Product as BaseProduct;

class Product
{
    const PARAMETER_TYPE_SINGLE = 'single';
    const PARAMETER_TYPE_MULTI = 'multi';

    private $product;

    /**
     * @var User
     */
    private $user;

    public function __construct($product, $user)
    {
        $this->product = $product;
        $this->user = $user;
    }

    private function getId()
    {
        $parts = explode('/', $this->product['id']);
        return (int) end($parts);
    }

    private function getPriceBeforeDiscount()
    {
        if (
            !$this->product['compareAtPriceRange'] || 
            !$this->product['compareAtPriceRange']['minVariantCompareAtPrice'] || 
            !$this->product['compareAtPriceRange']['minVariantCompareAtPrice']['amount']
        ) {
            return $this->getPrice();
        }

        return $this->product['compareAtPriceRange']['minVariantCompareAtPrice']['amount'];
    }

    private function getPrice()
    {
        if (
            !$this->product['priceRangeV2'] ||
            !$this->product['priceRangeV2']['minVariantPrice'] ||
            !$this->product['priceRangeV2']['minVariantPrice']['amount']
        ) {
            return 0;
        }

        return $this->product['priceRangeV2']['minVariantPrice']['amount'];
    }
    private function getTitle()
    {
        return htmlspecialchars($this->product['title']);
    }

    private function getDescription()
    {
        return htmlspecialchars($this->product['description']);
    }

    private function getProductUrl()
    {
        $session = $this->user->getSession();

        if (!$session) {
            return '';
        }

        if (!$this->product['handle']) {
            return '';
        }

        return "https://" . $session->getShop() . "/products/" . $this->product['handle']; 
    }

    private function getImage()
    {
        if (
            !$this->product['featuredMedia'] ||
            !$this->product['featuredMedia']['preview'] ||
            !$this->product['featuredMedia']['preview']['image'] ||
            !$this->product['featuredMedia']['preview']['image']['url']
        ) {
            return '';
        }

        return $this->product['featuredMedia']['preview']['image']['url'];
    }

    private function getBrand()
    {
        if (!$this->product['vendor']) {
            return '';
        }

        return $this->product['vendor'];
    }

    private function getCategory()
    {
        if (!$this->product['category'] || !$this->product['category']['name']) {
            return '';
        }

        if (isset($this->product['category']['id'])) {
            $result = $this->resolveTaxonomyCategory($this->product['category']['id']);

            if ($result) {
                return $result;
            }
        }

        return $this->product['category']['name'];
    }

    private function getShow()
    {
        return 'TRUE';
    }

    private function getStock()
    {
        return $this->product['totalInventory'];
    }

    private function getVariantId($variant)
    {
        $parts = explode('/', $variant['id']);
        return (int) end($parts);
    }

    private function getVariantImage($variant)
    {
        if (!$variant['image'] || !$variant['image']['url']) {
            $image = $this->getImage();
            return empty($image) ? $image : '';
        }

        return $variant['image']['url'];
    }

    private function getVariantPrice($variant)
    {
        if (!$variant['price']) {
            return '';
        }

        return $variant['price'];
    }

    private function getVariantPriceBeforeDiscount($variant)
    {
        if (!$variant['compareAtPrice']) {
            return $this->getVariantPrice($variant);
        }

        return $variant['compareAtPrice'];
    }

    private function getVariantTitle($variant)
    {
        return $variant['title'];
    }

    private function getVariantStock($variant)
    {
        if (!$variant['inventoryQuantity']) {
            return 0;
        }

        return $variant['inventoryQuantity'];
    }

    private function getVariantUrl($variant)
    {
        $productUrl = $this->getProductUrl();

        if (empty($productUrl)) {
            return '';
        }

        return $productUrl . '?variant=' . $this->getVariantId($variant);
    }

    private function getVariantDescription()
    {
        return $this->getDescription();
    }

    private function getVariants()
    {
        if (!$this->product['variants'] && !$this->product['variants']['nodes']) {
            return '';
        }

        $variants = [];

        foreach ($this->product['variants']['nodes'] as $variant) {
            $variantObj = [
                'PRODUCT_ID' => $this->getVariantId($variant),
                'TITLE' => $this->getVariantTitle($variant),
                'DESCRIPTION' => $this->getVariantDescription(),
                'PRICE' => $this->getVariantPrice($variant),
                'PRICE_BEFORE_DISCOUNT' => $this->getVariantPriceBeforeDiscount($variant),
                'STOCK' => $this->getVariantStock($variant),
                'URL' => $this->getVariantUrl($variant),
                'IMAGE' => $this->getVariantImage($variant),
            ];

            $variantParameters = $this->getVariantParameters($variant);

            if ($variantParameters) {
                $variantObj['PARAMETERS'] = $variantParameters;
            }

            $variants[] = $variantObj;
        }

        return serialize($variants);
    }

    private function getParameterName($metafield)
    {
        if (!$metafield['definition'] || !$metafield['definition']['name']) {
            return $metafield['key'];
        }
        
        return $metafield['definition']['name'];
    }

    private function getParameterObject($name, $value, $type = self::PARAMETER_TYPE_SINGLE)
    {
        return [
            'NAME' => $name,
            'VALUE' => $value,
            'TYPE' => $type
        ];
    }

    private function getDimensionParameter($metafield)
    {
        if (!$metafield['jsonValue']) {
            return null;
        }

        $name = $this->getParameterName($metafield);

        try {
            if ($metafield['jsonValue']['unit'] === 'POUNDS') {
                return $this->getParameterObject($name, $metafield['jsonValue']['value'] . " lb");
            }

            if ($metafield['jsonValue']['unit'] === 'MILLIMETERS') {
                return $this->getParameterObject($name, $metafield['jsonValue']['value'] . " mm");
            }

            if ($metafield['jsonValue']['unit'] === 'CENTIMETERS') {
                return $this->getParameterObject($name, $metafield['jsonValue']['value'] . " cm");
            }

            if ($metafield['jsonValue']['unit'] === 'METERS') {
                return $this->getParameterObject($name, $metafield['jsonValue']['value'] . " m");
            }

            if ($metafield['jsonValue']['unit'] === 'INCHES') {
                return $this->getParameterObject($name, $metafield['jsonValue']['value'] . " in");
            }

            if ($metafield['jsonValue']['unit'] === 'FEET') {
                return $this->getParameterObject($name, $metafield['jsonValue']['value'] . " ft");
            }

            if ($metafield['jsonValue']['unit'] === 'YARDS') {
                return $this->getParameterObject($name, $metafield['jsonValue']['value'] . " yd");
            }
        } catch (Exception $e) {
            //
        }

        return null;
    }

    private function getColorListParameter($metafield)
    {
        if (!$metafield['jsonValue']) {
            return null;
        }

        $name = $this->getParameterName($metafield);

        $parameters = [];

        foreach ($metafield['jsonValue'] as $value) {
           $parameters[]  = [
                'NAME' => $name,
                'VALUE' => $value,
            ];
        }

        if (empty($parameters)) {
            return null;
        }

        $parameters['TYPE'] = self::PARAMETER_TYPE_MULTI;

        return $parameters;
    }

    private function getMetaobjectParameter($metafield)
    {
        if (
            !$metafield['reference'] || 
            !isset($metafield['reference']['fields']) || 
            empty($metafield['reference']['fields'])
        ) {
            return null;
        }

        $mainName = $this->getParameterName($metafield);

        $parameters = [];

        if (isset($metafield['reference']['fields'][1])) {
            $fieldSecond = $metafield['reference']['fields'][1];

            if ($fieldSecond['key'] === 'taxonomy_reference') {
                $paramResult = $this->resolveTaxonomyValue($mainName, $fieldSecond['value']);

                if (!empty($paramResult)) {
                    $parameters[] = $paramResult;
                    $parameters['TYPE'] = self::PARAMETER_TYPE_MULTI;
                    return $parameters;
                }
            }
        }

        $field = reset($metafield['reference']['fields']);

        if (!$field) {
            return null;
        }

        $parameters[]  = [
            'NAME' => $mainName,
            'VALUE' => $field['value'],
        ];

        if (empty($parameters)) {
            return null;
        }

        $parameters['TYPE'] = self::PARAMETER_TYPE_MULTI;

        return $parameters;
    }

    private function getMetaobjectListParameter($metafield)
    {
        try {
            if (
                !$metafield['references'] || 
                !isset($metafield['references']['nodes']) || 
                empty($metafield['references']['nodes'])
            ) {
                return null;
            }

            $paramName = $this->getParameterName($metafield);

            $parameters = [];
            $paramsToProcess = [];

            foreach ($metafield['references']['nodes'] as $reference) {
                if (empty($reference['fields'])) {
                    continue;
                }

                $field = reset($reference['fields']);

                if (!$field) {
                    continue;
                }

                $param = [
                    'NAME' => $paramName,
                    'VALUE' => $field['value'],
                ];

                if (isset($reference['fields'][1])) {
                    $fieldSecond = $reference['fields'][1];

                    if ($fieldSecond['key'] === 'taxonomy_reference') {
                        $paramsToProcess[] = $fieldSecond['value'];
                        continue;
                    }
                }

                $parameters[] = $param;
            }

            if (empty($parameters) && empty($paramsToProcess)) {
                return null;
            }

            if (!empty($paramsToProcess)) {
                $processedParams = $this->resolveTaxonomyValues($metafield['key'], $paramsToProcess);

                if ($processedParams) {
                    $parameters = array_merge($parameters, $processedParams);
                }
            }

            $parameters['TYPE'] = self::PARAMETER_TYPE_MULTI;

            return $parameters;
        } catch (Exception $e) {
            echo '>----- product - getMetaobjectListParameter - Error' . PHP_EOL;
            var_dump($e->getMessage());
            return null;
        }
    }

    private function getCollectionParameter($metafield)
    {
        if (
            !$metafield['reference'] || 
            !isset($metafield['reference']['title']) || 
            empty($metafield['reference']['title'])
        ) {
            return null;
        }

        return $this->getParameterObject($this->getParameterName($metafield), $metafield['reference']['title']);
    }

    private function getCollectionListParameter($metafield)
    {
        try {
            if (
                !$metafield['references'] || 
                !isset($metafield['references']['nodes']) || 
                empty($metafield['references']['nodes'])
            ) {
                return null;
            }

            $paramName = $this->getParameterName($metafield);

            $parameters = [];
            $paramsToProcess = [];

            foreach ($metafield['references']['nodes'] as $reference) {
                if (empty($reference['title'])) {
                    continue;
                }

                $parameters[] = [
                    'NAME' => $paramName,
                    'VALUE' => $reference['title'],
                ];
            }

            if (empty($parameters) && empty($paramsToProcess)) {
                return null;
            }

            $parameters['TYPE'] = self::PARAMETER_TYPE_MULTI;

            return $parameters;
        } catch (Exception $e) {
            echo '>----- product - getCollectionListParameter - Error' . PHP_EOL;
            var_dump($e->getMessage());
            return null;
        }
    }

    private function getLowestPriceParameter($metafield)
    {
        if ($metafield['type'] !== 'json' || empty($metafield['jsonValue'])) {
            return null;
        }

        $lowestPrice = 0;

        foreach($metafield['jsonValue'] as $item) {
            if ($lowestPrice === 0) {
                $lowestPrice = $item['lowest_price'];
            } else if ($item['lowest_price'] < $lowestPrice) {
                $lowestPrice = $item['lowest_price'];
            }
        }

        if ($lowestPrice === 0) {
            return null;
        }

        return $this->getParameterObject("Lowest price", (string) $lowestPrice);
    }

    private function getParameter($metafield)
    {
        if ($metafield['key'] === 'omnibus_variant_lowest_prices') {
            return $this->getLowestPriceParameter($metafield);
        }

        if ($metafield['type'] === 'dimension' || $metafield['type'] === 'weight') {
            return $this->getDimensionParameter($metafield);
        }

        if ($metafield['type'] === 'list.color') {
            return $this->getColorListParameter($metafield);
        }

        if ($metafield['type'] === 'metaobject_reference') {
            return $this->getMetaobjectParameter($metafield);
        }

        if ($metafield['type'] === 'list.metaobject_reference') {
            return $this->getMetaobjectListParameter($metafield);
        }

        if ($metafield['type'] === 'collection_reference') {
            return $this->getCollectionParameter($metafield);
        }

        if ($metafield['type'] === 'list.collection_reference') {
            return $this->getCollectionListParameter($metafield);
        }

        // number_integer
        // string
        // single_line_text_field

        return $this->getParameterObject($this->getParameterName($metafield), $metafield['value']);
    }

    private function getParameters()
    {
        if (!$this->product['metafields'] || !$this->product['metafields']['nodes']) {
            return serialize([]);
        }

        $parameters = [];

        foreach ($this->product['metafields']['nodes'] as $metafield) {
            $parameter = $this->getParameter($metafield);

            if (!$parameter) {
                continue;
            }

            if ($parameter['TYPE'] === self::PARAMETER_TYPE_SINGLE) {
                unset($parameter['TYPE']);
                $parameters[] = $parameter;
            } else if ($parameter['TYPE'] === self::PARAMETER_TYPE_MULTI) {
                unset($parameter['TYPE']);
                foreach($parameter as $param) {
                    $parameters[] = $param;
                }
            }
        }

        return serialize($parameters);
    }

    private function getVariantParameter($metafield)
    {
        if ($metafield['key'] === 'omnibus_variant_lowest_prices') {
            return $this->getLowestPriceParameter($metafield);
        }

        if ($metafield['type'] === 'dimension' || $metafield['type'] === 'weight') {
            return $this->getDimensionParameter($metafield);
        }

        if ($metafield['type'] === 'single_line_text_field') {
            return $this->getParameterObject($this->getParameterName($metafield), $metafield['value']);
        }

        if ($metafield['type'] === 'string') {
            return $this->getParameterObject($this->getParameterName($metafield), $metafield['value']);
        }

        if ($metafield['type'] === 'number_integer') {
            return $this->getParameterObject($this->getParameterName($metafield), $metafield['value']);
        }

        // if ($metafield['type'] === 'list.color') {
        //     return $this->getColorListParameter($metafield);
        // }

        if ($metafield['type'] === 'metaobject_reference') {
            return $this->getMetaobjectParameter($metafield);
        }

        if ($metafield['type'] === 'list.metaobject_reference') {
            return $this->getMetaobjectListParameter($metafield);
        }

        if ($metafield['type'] === 'collection_reference') {
            return $this->getCollectionParameter($metafield);
        }

        if ($metafield['type'] === 'list.collection_reference') {
            return $this->getCollectionListParameter($metafield);
        }

        // number_integer
        // string
        // single_line_text_field

        return null;
    }

    private function getVariantParameters($variant)
    {
        if (!$variant['metafields'] || !$variant['metafields']['nodes']) {
            return serialize([]);
        }

        $parameters = [];

        foreach ($variant['metafields']['nodes'] as $metafield) {
            $parameter = $this->getVariantParameter($metafield);

            if (!$parameter) {
                continue;
            }

            if ($parameter['TYPE'] === self::PARAMETER_TYPE_SINGLE) {
                unset($parameter['TYPE']);
                $parameters[] = $parameter;
            } else if ($parameter['TYPE'] === self::PARAMETER_TYPE_MULTI) {
                unset($parameter['TYPE']);
                foreach($parameter as $param) {
                    $parameters[] = $param;
                }
            }
        }

        if (empty($parameters)) {
            return null;
        }

        return serialize($parameters);
    }

    function resolveTaxonomyValue(string $label, string $valueGid) 
    {
        $lang = $this->user->config->get("data_language") ?? 'en';
        $file = 'attribute_values.json';

        $filePath = __DIR__ . '/taxonomy/' . $lang . '/' . $file;

        return TaxonomyAttributesResolver::resolveValue($label, $valueGid, $filePath);
    }

    function resolveTaxonomyValues(string $attributeHandle, array $valueGids) 
    {
        $lang = $this->user->config->get("data_language") ?? 'en';
        $file = 'attributes.json';

        $filePath = __DIR__ . '/taxonomy/' . $lang . '/' . $file;

        return TaxonomyAttributesResolver::resolveValues($attributeHandle, $valueGids, $filePath);
    }

    function resolveTaxonomyCategory(string $categoryGid) 
    {
        $lang = $this->user->config->get("data_language") ?? 'en';
        $file = 'categories.txt';

        $filePath = __DIR__ . '/taxonomy/' . $lang . '/' . $file;

        $resolver = new TaxonomyCategoryResolver($filePath);

        return $resolver->getLeaf($categoryGid);
    }

    public function prepareFromApi($force = false)
    {
        $hash = md5(serialize($this->product));

        $productModel = BaseProduct::find()
            ->where(['PRODUCT_ID' => $this->getId(), 'user_id' => $this->user->id])->one();

        if (!$productModel) {
            $productModel = new BaseProduct();
            $productModel->from_api_page = 1;
        }

        try {
            $productModel->PRODUCT_ID = $this->getId();
            $productModel->TITLE = $this->getTitle();
            $productModel->PRICE = $this->getPrice(); // get min price of variants
            $productModel->PRICE_BEFORE_DISCOUNT = $this->getPriceBeforeDiscount();
            $productModel->URL = $this->getProductUrl();
            $productModel->IMAGE = $this->getImage();
            $productModel->CATEGORYTEXT = $this->getCategory();
            $productModel->DESCRIPTION = $this->getDescription();
            $productModel->STOCK = $this->getStock();
            $productModel->BRAND = $this->getBrand();
            $productModel->VARIANT = $this->getVariants();
            $productModel->SHOW = $this->getShow();

            $productModel->user_id = $this->user->id;

            $productModel->PRICES = '';
            $productModel->PRODUCT_LINE = ''; // ??
            $productModel->PARAMETERS = $this->getParameters();
            $productModel->response = '';
            $productModel->translation = '';
            $productModel->variants_values = '';
            $productModel->PRICE_BUY = 0;
            $productModel->PRICE_WHOLESALE = 0;

            $productModel->params_hash = $hash;

            if ($productModel->save()) {
                echo '>----- product - prepareFromApi - Success' . PHP_EOL;
                return true;
            } else {
                echo '>----- product - prepareFromApi - Error 1:' . PHP_EOL;
                var_dump($productModel->getErrors());
            }
        } catch (Exception $e) {
            echo '>----- product - prepareFromApi - Error 2:' . PHP_EOL;
            var_dump($e->getMessage());
        }

        return false;
    }
}
