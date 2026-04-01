<?php
namespace app\modules\xml_generator\controllers;

use app\models\User;
use app\modules\xml_generator\src\XmlFeed;
use yii\web\Controller;

class CategoriesController extends Controller
{

    public function actionGenerate($uuid)
    {
        if(($_user = User::findByUUID($uuid)) === null)
        {
            return ['error' => 'User not found'];
        }

        try {
            $customers = new XmlFeed();
            $customers->setType(XmlFeed::CATEGORY);
            $customers->setUser($_user);
            $customers_file = $customers->getFile();
        } catch (\Exception $e) {
            return $e;
            // return ['error' => $e->getMessage()];
        }

        header('Content-type: application/xml; charset=utf-8');
        echo $customers_file;
        die;
    }
}