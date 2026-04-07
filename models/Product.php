<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "product".
 *
 * @property int $PRODUCT_ID
 * @property string $URL
 * @property string $TITLE
 * @property float $PRICE
 * @property string $BRAND
 * @property string $DESCRIPTION
 * @property float $PRICE_BEFORE_DISCOUNT
 * @property float $PRICE_BUY
 * @property string $IMAGE
 * @property string $CATEGORYTEXT
 * @property string $SHOW
 * @property string $PRODUCT_LINE
 * @property string $PARAMETERS
 * @property string $VARIANT
 * @property int $STOCK
 * @property string $response
 * @property string $params_hash
 * @property int $user_id
 */
class Product extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'product';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['PRICE', 'PRICE_BEFORE_DISCOUNT', 'PRICE_WHOLESALE'], 'number'],
            [['DESCRIPTION', 'CATEGORYTEXT', 'PARAMETERS', 'VARIANT', 'PRODUCT_LINE', 'SHOW', 'response', 'PRICES'], 'string'],
            [['STOCK', 'user_id'], 'integer'],
            [['TITLE', 'BRAND', 'IMAGE', 'PRODUCT_LINE'], 'string', 'max' => 250],
            [['URL'], 'string', 'max' => 550],
            [['params_hash'], 'string', 'max' => 50],
            [['translation'], 'string', 'max' => 5],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'PRODUCT_ID' => 'Product ID',
            'URL' => 'Url',
            'TITLE' => 'Title',
            'PRICE' => 'Price',
            'BRAND' => 'Brand',
            'DESCRIPTION' => 'Description',
            'PRICE_BEFORE_DISCOUNT' => 'Price Before Discount',
            'IMAGE' => 'Image',
            'CATEGORYTEXT' => 'Categorytext',
            'SHOW' => 'Show',
            'PARAMETERS' => 'Parameters',
            'VARIANT' => 'Variant',
            'STOCK' => 'Stock',
            'PRODUCT_LINE' => 'Product line',
            'response' => 'Response',
            'params_hash' => 'Params Hash',
        ];
    }

    public function getXmlEntity($settings, $user) {
        // echo "getXmlEntity start ".$this->PRODUCT_ID.PHP_EOL;

        if (
            $settings['aggregate_groups_as_variants'] && 
            $this->parent_id != 0 && 
            $this->parent_id != $this->PRODUCT_ID
        ) {
            return '';
        }

        $fields_to_integrate = [];

        if ($user->config->get('product_image')) {
            $fields_to_integrate[] = 'product_image';
        }

        if ($user->config->get('product_description')) {
            $fields_to_integrate[] = 'product_description';
        }

        $products = new \SimpleXMLElement('<PRODUCTS/>');

        $product = $products->addChild('PRODUCT');

        $product->addChild('PRODUCT_ID', $this->PRODUCT_ID);
        $product->addChild('URL', $this->URL);
        $product->addChild('TITLE', $this->TITLE);
        $product->addChild('PRICE', $this->PRICE);

        if (in_array('product_image', $fields_to_integrate) && !empty($this->IMAGE)) {
            $product->addChild('IMAGE', $this->IMAGE);
        }

        if (in_array('product_description', $fields_to_integrate) && !empty($this->DESCRIPTION)) {
            $product->addChild('DESCRIPTION', $this->DESCRIPTION);
        }

        return $product->asXml();
    }

    public function getUser(){
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    public function getVariants()
    {
        return unserialize($this->VARIANT);
    }
}
