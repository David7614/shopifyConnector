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
        Queue::prepareQueue(XmlFeed::PRODUCT);
        Queue::prepareQueue(XmlFeed::CUSTOMER);
        Queue::prepareQueue(XmlFeed::ORDER);
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
}
