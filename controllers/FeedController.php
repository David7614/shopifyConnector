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

        $feeds = [];

        foreach (array_keys(XmlFeed::$recordTagNames) as $type) {
            $url = Url::home(true) . 'xml/' . $user->uuid . '/' . $type . '.xml';

            $feeds[$type] = [
                'url'     => $url,
                'all'     => $user->countDatabaseElements($type),
                'status'  => 'Not ready',
                'current' => '0',
            ];

            $count = XmlFeed::countFeedRecords($user->uuid, $type);

            if ($count !== null) {
                $feeds[$type]['status']  = 'Ready';
                $feeds[$type]['current'] = (string) $count;
            }
        }

        return $feeds;
    }
}
