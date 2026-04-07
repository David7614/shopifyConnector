<?php
namespace app\modules\xml_generator\controllers;

use app\models\User;
use app\modules\xml_generator\src\XmlFeed;
use yii\web\Controller;

class OrdersController extends Controller
{
    public function actionGenerate($uuid)
    {

        if(($_user = User::findByUUID($uuid)) === null)
        {
            return "User not found";
        }
        try {
            $orders = new XmlFeed();
            $orders->setType(XmlFeed::ORDER);
            $orders->setUser($_user);
            $orders_file_path = $orders->getFile(true);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        $filename='orders.xml';
        header('Content-type: application/xml; charset=utf-8');
        // echo $orders_file_path;
        header("Content-Length: ".filesize(trim($orders_file_path)));
                header("Content-Disposition: attachment; filename=\"$filename\"");
                // Force the download           
                header("Content-Transfer-Encoding: binary");            
                @readfile($orders_file_path);       
        die;
    }
}