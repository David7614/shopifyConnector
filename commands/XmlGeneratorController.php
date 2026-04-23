<?php
declare(strict_types=1);

namespace app\commands;

use app\models\Queue;
use app\modules\xml_generator\src\XmlFeed;
use yii\console\Controller;

class XmlGeneratorController extends Controller
{
    public $what;

    public function options($actionsID)
    {
        return ['what'];
    }

    public function actionPrepareQueue()
    {
        Queue::prepareQueue(XmlFeed::CUSTOMER);
        Queue::prepareQueue(XmlFeed::PRODUCT);
        Queue::prepareQueue(XmlFeed::ORDER);

        Queue::cleanupOldQueues(); // usuwanie staroci

        
        echo "OK";
    }

    public function actionGenerateProducts($forceId = 0, $forcePage = null)
    {
        return XmlGeneratorService::executeQueue(
            XmlFeed::PRODUCT,
            [
                'shop_type' => 'shopify',
                'forceId' => $forceId,
                'forcePage' => $forcePage
            ]
        );
    }

    public function actionGenerateCustomers($forceId = 0)
    {
        return XmlGeneratorService::executeQueue(
            XmlFeed::CUSTOMER,
            [
                'shop_type' => 'shopify',
                "forceId" => $forceId
            ]
        );
    }

    public function actionGenerateOrders($forceId = 0)
    {
        return XmlGeneratorService::executeQueue(
            XmlFeed::ORDER,
            [
                'shop_type' => 'shopify',
                "forceId" => $forceId
            ]
        );
    }

    public function actionLoopProducts()
    {
        return XmlGeneratorService::loopQueue(XmlFeed::PRODUCT, ['shop_type' => 'shopify']);
    }

    public function actionLoopCustomers()
    {
        return XmlGeneratorService::loopQueue(XmlFeed::CUSTOMER, ['shop_type' => 'shopify']);
    }

    public function actionLoopOrders()
    {
        return XmlGeneratorService::loopQueue(XmlFeed::ORDER, ['shop_type' => 'shopify']);
    }
}
