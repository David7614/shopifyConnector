<?php
namespace app\modules\xml_generator\controllers;

use app\models\User;
use app\modules\xml_generator\src\XmlFeed;
use yii\web\Controller;

class CustomersController extends Controller
{
    public function actionGenerate($uuid)
    {
        if(($_user = User::findByUUID($uuid)) === null)
        {
            return 'User not found';
        }
        try {
            $customers = new XmlFeed();
            $customers->setType(XmlFeed::CUSTOMER);
            $customers->setUser($_user);
            $customers_file_path = $customers->getFile(true);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        header('Content-type: application/xml; charset=utf-8');
        header('Content-type: application/xml; charset=utf-8');
        // echo $products_file;
        $filename='customers.xml';
        header('Content-type: application/xml; charset=utf-8');
        // echo $customers_file_path;
        header("Content-Length: ".filesize(trim($customers_file_path)));
                header("Content-Disposition: attachment; filename=\"$filename\"");
                // Force the download           
                header("Content-Transfer-Encoding: binary");            
                @readfile($customers_file_path);    
        die;
    }
}