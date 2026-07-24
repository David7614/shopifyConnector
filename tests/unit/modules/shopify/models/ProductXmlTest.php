<?php

namespace tests\unit\modules\shopify\models;

use app\modules\shopify\models\ProductXml;
use Codeception\Test\Unit;
use SimpleXMLElement;
use stdClass;

class ProductXmlTest extends Unit
{
    private function makeProduct(int $stock): stdClass
    {
        $product = new stdClass();
        $product->PRODUCT_ID = 123;
        $product->URL = 'https://example.com/product';
        $product->TITLE = 'Test product';
        $product->PRICE = 10.0;
        $product->IMAGE = '';
        $product->DESCRIPTION = '';
        $product->BRAND = '';
        $product->PRICE_BEFORE_DISCOUNT = 0;
        $product->CATEGORYTEXT = '';
        $product->PARAMETERS = serialize([]);
        $product->VARIANT = '';
        $product->STOCK = $stock;

        return $product;
    }

    public function testStockTagIsPresentWhenProductHasZeroStock()
    {
        $product = $this->makeProduct(0);

        $xml = ProductXml::getEntity($product, null, ['product_stock']);
        $simpleXml = new SimpleXMLElement($xml);

        $this->assertTrue(isset($simpleXml->STOCK), 'Expected <STOCK> tag to be present when stock is 0');
        $this->assertSame('0', (string) $simpleXml->STOCK);
    }

    public function testStockTagReflectsPositiveStock()
    {
        $product = $this->makeProduct(5);

        $xml = ProductXml::getEntity($product, null, ['product_stock']);
        $simpleXml = new SimpleXMLElement($xml);

        $this->assertTrue(isset($simpleXml->STOCK));
        $this->assertSame('5', (string) $simpleXml->STOCK);
    }

    public function testStockTagAbsentWhenFeatureDisabled()
    {
        $product = $this->makeProduct(0);

        $xml = ProductXml::getEntity($product, null, []);
        $simpleXml = new SimpleXMLElement($xml);

        $this->assertFalse(isset($simpleXml->STOCK), 'STOCK tag should not appear when product_stock is disabled');
    }
}
