<?php
declare(strict_types=1);

namespace app\modules\shopify\models;

use app\models\Orders;
use Exception;
use app\models\User;

class Order
{
    private $order;

    /**
     * @var User
     */
    private $user;

    public function __construct($order, $user)
    {
        $this->order = $order;
        $this->user = $user;
    }

    private function getId()
    {
        $parts = explode('/', $this->order['id']);
        return (string) end($parts);
    }

    private function getCustomerId()
    {
        if (!$this->order['customer'] || !$this->order['customer']['id']) {
            return '';
        }

        $parts = explode('/', $this->order['customer']['id']);
        return (string) end($parts);
    }

    private function getEmail()
    {
        if (!$this->order['email']) {
            return '';
        }

        return $this->order['email'];
    }

    private function getStatus()
    {
        if ($this->order['cancelledAt']) {
            return 'canceled';
        }

        $statusesMap = [
            'FULFILLED' => 'finished',
            'REQUEST_DECLINED' => 'canceled',
        ];

        return isset($statusesMap[$this->order['displayFulfillmentStatus']]) ? 
            $statusesMap[$this->order['displayFulfillmentStatus']] : 'created';
    }

    private function getZipCode()
    {
        if (!$this->order['billingAddress'] || !$this->order['billingAddress']['zip']) {
            return '';
        }

        return $this->order['billingAddress']['zip'];
    }

    private function getCountryCode()
    {
        if (!$this->order['billingAddress'] || !$this->order['billingAddress']['countryCodeV2']) {
            return '';
        }

        return $this->order['billingAddress']['countryCodeV2'];
    }

    private function getPhone()
    {
        if (!$this->order['billingAddress'] || !$this->order['billingAddress']['phone']) {
            return '';
        }

        return $this->order['billingAddress']['phone'];
    }

    private function getCreatedOn()
    {
        return $this->order['createdAt'] ? 
            date('Y-m-d H:i:s', strtotime($this->order['createdAt'])) : '2000-01-01 00:00:00';
    }

    private function getFinishOn()
    {
        if (!$this->order['closedAt']) {
            return '';
        }

        return $this->getStatus() === 'finished' ? date('Y-m-d H:i:s', strtotime($this->order['closedAt'])) : '';
    }

    private function getPositionId($position)
    {
        if (!$position['variant']) {
            return null;
        }

        $parts = explode('/', $position['variant']['id']);
        return (string) end($parts);
    }

    private function getPositionPrice($position)
    {
        if (
            !$position['discountedTotalSet'] || 
            !$position['discountedTotalSet']['presentmentMoney'] || 
            !$position['discountedTotalSet']['presentmentMoney']['amount']
        ) {
            return null;
        }

        return $position['discountedTotalSet']['presentmentMoney']['amount'];
    }

    private function getPositions()
    {
        if (!$this->order['lineItems'] || !$this->order['lineItems']['nodes']) {
            return serialize([]);
        }

        $positions = [];

        foreach ($this->order['lineItems']['nodes'] as $product) {
            $id = $this->getPositionId($product);
            $price = $this->getPositionPrice($product);

            if (!$id || !$price) {
                continue;
            }

            $positions[] = [
                'product_id' => $id,
                'amount' => $product['quantity'],
                'price' => $price,
            ];
        }

        return serialize($positions);
    }

    public function prepareFromApi($force = false)
    {
        $orderModel = Orders::findOne(['order_id' => $this->getId(), 'user_id' => $this->user->id]);

        if (!$orderModel) {
            $orderModel = new Orders();
        }

        $orderModel->order_id = $this->getId();
        $orderModel->customer_id = $this->getCustomerId();
        $orderModel->user_id = $this->user->id;

        $orderModel->created_on = $this->getCreatedOn();
        $orderModel->finished_on = $this->getFinishOn();

        $orderModel->status = $this->getStatus();
        $orderModel->email = $this->getEmail();
        $orderModel->phone = $this->getPhone();
        $orderModel->zip_code = $this->getZipCode();
        $orderModel->country_code = $this->getCountryCode();
        $orderModel->page = 0;
        $orderModel->order_positions = $this->getPositions();

        try {
            if ($orderModel->save()) {
                echo '>----- order - prepareFromApi - Success' . PHP_EOL;
                return true;
            } else {
                echo '>----- order - prepareFromApi - Error 1:' . PHP_EOL;
                var_dump($orderModel->getErrors());
            }
        } catch (Exception $e) {
            echo '>----- order - prepareFromApi - Error 2:' . PHP_EOL;
            var_dump($e->getMessage());
        }

        return false;
    }
}
