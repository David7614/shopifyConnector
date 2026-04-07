<?php
namespace app\modules\xml_generator\controllers;

use app\models\User;
use app\modules\xml_generator\src\XmlFeed;
use app\services\FeedStorageService;
use yii\web\Controller;

class OrdersController extends Controller
{
    public function actionGenerate($uuid)
    {
        if (($_user = User::findByUUID($uuid)) === null) {
            return 'User not found';
        }

        if (FeedStorageService::isConfigured()) {
            try {
                $storage = FeedStorageService::create();
                $key     = 'order/' . $_user->uuid . '/order.xml';

                if (!$storage->exists($key)) {
                    header('Content-type: application/xml; charset=utf-8');
                    echo '<?xml version="1.0"?><INFO><NOTICE>Feed is generating. Please try later.</NOTICE></INFO>';
                    die;
                }

                $content = $storage->get($key);
                header('Content-type: application/xml; charset=utf-8');
                header('Content-Disposition: attachment; filename="orders.xml"');
                header('Content-Length: ' . strlen($content));
                echo $content;
                die;
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        }

        try {
            $orders = new XmlFeed();
            $orders->setType(XmlFeed::ORDER);
            $orders->setUser($_user);
            $orders_file_path = $orders->getFile(true);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $filename = 'orders.xml';
        header('Content-type: application/xml; charset=utf-8');
        header("Content-Length: " . filesize(trim($orders_file_path)));
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-Transfer-Encoding: binary");
        @readfile($orders_file_path);
        die;
    }
}