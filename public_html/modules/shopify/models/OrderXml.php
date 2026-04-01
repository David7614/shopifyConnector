<?php
declare(strict_types=1);

namespace app\modules\shopify\models;

use app\models\Orders;
use SimpleXmlElement;
use app\models\User;
use app\modules\xml_generator\helper\SambaHelper;
use Exception;

class OrderXml
{
    /**
     * @param Orders $order
     * @param User $user
     */
    public static function getEntity($order)
    {
        try {
            $positions = $order->getPositions();

            if (empty($positions)) {
                return null;
            }

            $ordersXml = new SimpleXmlElement('<ORDERS/>');

            $orderXml = $ordersXml->addChild('ORDER');

            $orderXml->addChild('ORDER_ID', (string) $order->order_id);

            if (!empty($order->customer_id)) {
                $orderXml->addChild('CUSTOMER_ID', (string) $order->customer_id);
            }

            $orderXml->addChild('CREATED_ON', SambaHelper::getCorrectSambaDate($order->created_on));

            if ($order->status === 'finished' && !empty($order->finished_on)) {
                $orderXml->addChild('FINISHED_ON', SambaHelper::getCorrectSambaDate($order->finished_on));
            }

            $orderXml->addChild('STATUS', $order->status);

            if (!empty($order->email)) {
                $orderXml->addChild('EMAIL', $order->email);
            }

            if (!empty($order->phone)) {
                $orderXml->addChild('PHONE', str_replace(' ', '', $order->phone));
            }

            if (!empty($order->zip_close)) {
                $orderXml->addChild('ZIP_CODE', $order->zip_code);
            }

            if (!empty($order->country_code)) {
                $orderXml->addChild('COUNTRY_CODE', $order->country_code);
            }

            $orderItems = $orderXml->addChild('ITEMS');

            foreach ($positions as $product) {
                $orderItem = $orderItems->addChild('ITEM');
                $orderItem->addChild('PRODUCT_ID', $product['product_id']);
                $orderItem->addChild('AMOUNT', (string) $product['amount']);
                $orderItem->addChild('PRICE', (string) $product['price']);
            }

            return $orderXml->asXml();
        } catch (Exception $e) {
            echo '>----- OrderXml - getEntity - Error:' . PHP_EOL;
            var_dump($e->getMessage());
        }

        return null;
    }
}
