<?php
declare(strict_types=1);

namespace app\modules\shopify\models;

use SimpleXMLElement;
use app\models\User;
use app\models\Product;
use app\modules\xml_generator\helper\SambaHelper;
use Exception;

class ProductXml
{
    /**
     * @param Product $product
     * @param User $user
     */
    public static function getEntity($product, $user)
    {
        try {
            $productsXml = new SimpleXMLElement('<PRODUCTS/>');
            $productXml = $productsXml->addChild('PRODUCT');

            $productXml->addChild('PRODUCT_ID', (string) $product->PRODUCT_ID);
            $productXml->addChild('URL', $product->URL);
            $productXml->addChild('TITLE', SambaHelper::sanitizeForXml($product->TITLE));
            $productXml->addChild('PRICE', (string) $product->PRICE);

            $fields_to_integrate = self::getFieldsToIntegrate($user);

            if (in_array('product_image', $fields_to_integrate) && !empty($product->IMAGE)) {
                $productXml->addChild('IMAGE', $product->IMAGE);
            }

            if (in_array('product_description', $fields_to_integrate) && !empty($product->DESCRIPTION)) {
                $productXml->addChild('DESCRIPTION', SambaHelper::sanitizeForXml($product->DESCRIPTION));
            }

            if (in_array('product_brand', $fields_to_integrate) && !empty($product->BRAND)) {
                $productXml->addChild('BRAND', $product->BRAND);
            }

            if (in_array('product_stock', $fields_to_integrate) && !empty($product->STOCK)) {
                $productXml->addChild('STOCK', (string) $product->STOCK);
            }

            if (in_array('product_price_before_discount', $fields_to_integrate) && !empty($product->PRICE_BEFORE_DISCOUNT)) {
                $productXml->addChild('PRICE_BEFORE_DISCOUNT', (string) $product->PRICE_BEFORE_DISCOUNT);
            }

            if (in_array('product_category', $fields_to_integrate) && !empty($product->CATEGORYTEXT)) {
                $productXml->addChild('CATEGORYTEXT', SambaHelper::sanitizeForXml($product->CATEGORYTEXT));
            }

            if (in_array('product_parameters', $fields_to_integrate)) {
                $parameters = unserialize($product->PARAMETERS);

                if (!empty($parameters)) {
                    $parametersXml = $productXml->addChild('PARAMETERS');

                    foreach ($parameters as $parameter) {
                        $parameterXml = $parametersXml->addChild('PARAMETER');
                        // $parameterXml->addChild($parameter['NAME'], $parameter['VALUE']);

                        foreach ($parameter as $index => $value) {
                            if (is_string($value)) {
                                $value = SambaHelper::sanitizeForXml($value);
                            }
                       	    $parameterXml->addChild($index, $value);
                        }
                    }
                }
            }

            if (in_array('product_variants', $fields_to_integrate) && !empty($product->VARIANT)) {
                $variants = $product->getVariants();

                if (!empty($variants)) {
                    foreach ($variants as $variant) {
                        $variantXml = $productXml->addChild('VARIANT');

                        $variantXml->addChild('PRODUCT_ID', (string) $variant['PRODUCT_ID']);
                        $variantXml->addChild('TITLE', SambaHelper::sanitizeForXml($variant['TITLE']));
                        $variantXml->addChild('URL', $variant['URL']);
                        $variantXml->addChild('PRICE', (string) $variant['PRICE']);

                        if (in_array('product_image', $fields_to_integrate) && !empty($variant['IMAGE'])) {
                            $variantXml->addChild('IMAGE', $variant['IMAGE']);
                        }

                        if (in_array('product_description', $fields_to_integrate) && !empty($variant['DESCRIPTION'])) {
                            $variantXml->addChild('DESCRIPTION', SambaHelper::sanitizeForXml($variant['DESCRIPTION']));
                        }

                        if (in_array('product_stock', $fields_to_integrate) && !empty($variant['STOCK'])) {
                            $variantXml->addChild('STOCK', (string) $variant['STOCK']);
                        }

                        if (in_array('product_price_before_discount', $fields_to_integrate) && !empty($variant['PRICE_BEFORE_DISCOUNT'])) {
                            $variantXml->addChild('PRICE_BEFORE_DISCOUNT', (string) $variant['PRICE_BEFORE_DISCOUNT']);
                        }

                        if (in_array('product_parameters', $fields_to_integrate) && !empty($variant['PARAMETERS'])) {
                            $parameters = unserialize($variant['PARAMETERS']);

                            if (!empty($parameters)) {
                                $parametersXml = $variantXml->addChild('PARAMETERS');

                                foreach ($parameters as $parameter) {
                                    $parameterXml = $parametersXml->addChild('PARAMETER');

                                    foreach ($parameter as $index => $value) {
                                        if (is_string($value)) {
                                            $value = SambaHelper::sanitizeForXml($value);
                                        }
                                        $parameterXml->addChild($index, $value);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return $productXml->asXml();
        } catch (Exception $e) {
            echo '>----- ProductXml - getEntity - Error' . PHP_EOL;
            var_dump($e->getMessage());
        }

        return null;
    }

    public static function getFieldsToIntegrate($user)
    {
        $fields_to_integrate = [];

        if ($user->config->get('product_image')) {
            $fields_to_integrate[] = 'product_image';
        }

        if ($user->config->get('product_description')) {
            $fields_to_integrate[] = 'product_description';
        }

        if ($user->config->get('product_brand')) {
            $fields_to_integrate[] = 'product_brand';
        }

        if ($user->config->get('product_stock')) {
            $fields_to_integrate[] = 'product_stock';
        }

        if ($user->config->get('product_price_before_discount')) {
            $fields_to_integrate[] = 'product_price_before_discount';
        }

        if ($user->config->get('product_category')) {
            $fields_to_integrate[] = 'product_category';
        }

        if ($user->config->get('product_parameters')) {
            $fields_to_integrate[] = 'product_parameters';
        }

        if ($user->config->get('product_variants')) {
            $fields_to_integrate[] = 'product_variants';
        }

        return $fields_to_integrate;
    }
}
