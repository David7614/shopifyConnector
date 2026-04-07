<?php

namespace app\controllers;

use yii\helpers\Url;
use app\models\User;
use app\modules\xml_generator\src\XmlFeed;
use yii\filters\ContentNegotiator;
use yii\web\Response;
use yii\rest\Controller;

class FeedController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'contentNegotiator' => [
                'class' => ContentNegotiator::className(),
                'formats' => [
                    'application/json' => Response::FORMAT_JSON,
                ],
            ],
        ];
    }

    public function actionIndex($id)
    {
        $user = User::findByUUID($id);

        $xmlGenerator = new XmlFeed();
        $xmlGenerator->setUser($user);

        $urls = [];
        $feeds = [];

        $xmlGenerator->setType('product');
        $urls['products'] = $xmlGenerator->getFile(true, false);

        $xmlGenerator->setType('customer');
        $urls['customers'] = $xmlGenerator->getFile(true, false);

        $xmlGenerator->setType('order');
        $urls['orders'] = $xmlGenerator->getFile(true, false);

        foreach ($urls as $type => $fileName) {
            $feeds[$type] = [
                'status' => 'Ready',
                'url' => Url::home(true) . 'xml/' . $user->uuid . '/' . $type . '.xml',
                'all' => $user->countDatabaseElements($type),
            ];

            if (!is_file($fileName)) {
                $feeds[$type]['status'] = 'Not ready';
                $feeds[$type]['current'] = "0";
            } else {
                $xml = file_get_contents($fileName);
                $tagName = strtoupper($type);

                if ($type == 'products') {
                    $tagName = 'PRODUCT';
                } else if ($type == 'orders') {
                    $tagName = 'ORDER';
                } else if ($type == 'customers') {
                    $tagName = 'CUSTOMER';
                }

                $tagCount = substr_count($xml, "<" . $tagName . ">");

                $feeds[$type]['current'] = (string) $tagCount;
            }
        }

        return $feeds;
    }
}
