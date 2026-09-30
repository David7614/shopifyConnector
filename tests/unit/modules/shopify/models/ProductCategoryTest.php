<?php

namespace tests\unit\modules\shopify\models;

use app\modules\shopify\models\Product;
use Codeception\Test\Unit;

/**
 * Stands in for User::getConfig() so the decision logic can be tested
 * without touching the database.
 */
class FakeUserConfig
{
    private $values;

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function get($key)
    {
        return $this->values[$key] ?? null;
    }
}

class FakeUser
{
    public $config;

    public function __construct(array $config)
    {
        $this->config = new FakeUserConfig($config);
    }
}

class ProductCategoryTest extends Unit
{
    /** A real GID from taxonomy/pl/categories.txt, leaf: "Karma dla psów". */
    private const GID_DOG_FOOD = 'gid://shopify/TaxonomyCategory/ap-2-3-3';

    /** getCategory() is internal to the feed pipeline, so it is reached reflectively. */
    private function categoryOf(Product $product): string
    {
        $method = new \ReflectionMethod(Product::class, 'getCategory');
        $method->setAccessible(true);

        return (string) $method->invoke($product);
    }

    private function makeProduct(array $overrides, array $config = []): Product
    {
        $product = array_merge([
            'productType' => '',
            'category'    => null,
        ], $overrides);

        $user = new FakeUser(array_merge(['data_language' => 'pl'], $config));

        return new Product($product, $user);
    }

    private function taxonomyNode(string $gid, string $name): array
    {
        return ['id' => $gid, 'name' => $name];
    }

    public function testDefaultsToTaxonomyWhenNothingIsConfigured()
    {
        $product = $this->makeProduct([
            'productType' => 'Przysmak dla Psa',
            'category'    => $this->taxonomyNode(self::GID_DOG_FOOD, 'Dog Food'),
        ]);

        $this->assertSame('Karma dla psów', $this->categoryOf($product));
    }

    public function testTaxonomySourceResolvesGidToLocalizedLeaf()
    {
        $product = $this->makeProduct(
            ['category' => $this->taxonomyNode(self::GID_DOG_FOOD, 'Dog Food')],
            ['product_category_source' => Product::CATEGORY_SOURCE_TAXONOMY]
        );

        $this->assertSame('Karma dla psów', $this->categoryOf($product));
    }

    /**
     * Shops on the taxonomy source must keep the behaviour they had before this
     * setting existed: Shopify's "no category" node is a label like any other.
     */
    public function testUncategorizedTaxonomyNodeKeepsItsLabelOnTaxonomySource()
    {
        $product = $this->makeProduct(
            ['category' => $this->taxonomyNode(Product::TAXONOMY_UNCATEGORIZED_GID, 'Bez kategorii')],
            ['product_category_source' => Product::CATEGORY_SOURCE_TAXONOMY]
        );

        $this->assertSame('Bez kategorii', $this->categoryOf($product));
    }

    public function testUncategorizedTaxonomyNodeKeepsItsLabelWhenNothingIsConfigured()
    {
        $product = $this->makeProduct(
            ['category' => $this->taxonomyNode(Product::TAXONOMY_UNCATEGORIZED_GID, 'Bez kategorii')]
        );

        $this->assertSame('Bez kategorii', $this->categoryOf($product));
    }

    public function testMissingTaxonomyNodeYieldsEmpty()
    {
        $product = $this->makeProduct(
            ['category' => null],
            ['product_category_source' => Product::CATEGORY_SOURCE_TAXONOMY]
        );

        $this->assertSame('', $this->categoryOf($product));
    }

    public function testProductTypeSourceUsesProductType()
    {
        $product = $this->makeProduct(
            [
                'productType' => 'Przysmak dla Psa',
                'category'    => $this->taxonomyNode(self::GID_DOG_FOOD, 'Dog Food'),
            ],
            ['product_category_source' => Product::CATEGORY_SOURCE_PRODUCT_TYPE]
        );

        $this->assertSame('Przysmak dla Psa', $this->categoryOf($product));
    }

    public function testProductTypeIsTrimmed()
    {
        $product = $this->makeProduct(
            ['productType' => '  Przysmak dla Psa  '],
            ['product_category_source' => Product::CATEGORY_SOURCE_PRODUCT_TYPE]
        );

        $this->assertSame('Przysmak dla Psa', $this->categoryOf($product));
    }

    public function testEmptyProductTypeYieldsEmptyWithoutFallback()
    {
        $product = $this->makeProduct(
            [
                'productType' => '',
                'category'    => $this->taxonomyNode(self::GID_DOG_FOOD, 'Dog Food'),
            ],
            ['product_category_source' => Product::CATEGORY_SOURCE_PRODUCT_TYPE]
        );

        $this->assertSame('', $this->categoryOf($product));
    }

    public function testEmptyProductTypeFallsBackToTaxonomyWhenEnabled()
    {
        $product = $this->makeProduct(
            [
                'productType' => '',
                'category'    => $this->taxonomyNode(self::GID_DOG_FOOD, 'Dog Food'),
            ],
            [
                'product_category_source'             => Product::CATEGORY_SOURCE_PRODUCT_TYPE,
                'product_category_fallback_taxonomy'  => '1',
            ]
        );

        $this->assertSame('Karma dla psów', $this->categoryOf($product));
    }

    public function testFallbackToUncategorizedTaxonomyStillYieldsEmpty()
    {
        $product = $this->makeProduct(
            [
                'productType' => '',
                'category'    => $this->taxonomyNode(Product::TAXONOMY_UNCATEGORIZED_GID, 'Bez kategorii'),
            ],
            [
                'product_category_source'            => Product::CATEGORY_SOURCE_PRODUCT_TYPE,
                'product_category_fallback_taxonomy' => '1',
            ]
        );

        $this->assertSame('', $this->categoryOf($product));
    }

    public function testUnknownSourceValueFallsBackToTaxonomy()
    {
        $product = $this->makeProduct(
            [
                'productType' => 'Przysmak dla Psa',
                'category'    => $this->taxonomyNode(self::GID_DOG_FOOD, 'Dog Food'),
            ],
            ['product_category_source' => 'collections']
        );

        $this->assertSame('Karma dla psów', $this->categoryOf($product));
    }

    public function testMissingProductTypeKeyIsHandled()
    {
        $user    = new FakeUser(['product_category_source' => Product::CATEGORY_SOURCE_PRODUCT_TYPE]);
        $product = new Product(['category' => null], $user);

        $this->assertSame('', $this->categoryOf($product));
    }
}
