<?php

namespace app\controllers;

use yii\helpers\Url;
use app\models\User;
use app\modules\xml_generator\src\XmlFeed;
use app\services\FeedStorageService;
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

    private static array $tagNames = [
        'products'  => 'PRODUCT',
        'customers' => 'CUSTOMER',
        'orders'    => 'ORDER',
    ];

    public function actionIndex($id)
    {
        $user = User::findByUUID($id);

        $feeds = [];

        foreach (self::$tagNames as $type => $tagName) {
            $url = Url::home(true) . 'xml/' . $user->uuid . '/' . $type . '.xml';

            $feeds[$type] = [
                'url'     => $url,
                'all'     => $user->countDatabaseElements($type),
                'status'  => 'Not ready',
                'current' => '0',
            ];

            $xml = $this->getFeedContent($user->uuid, $type);

            if ($xml !== null) {
                $feeds[$type]['status']  = 'Ready';
                $feeds[$type]['current'] = (string) substr_count($xml, "<{$tagName}>");
            }
        }

        return $feeds;
    }

    private function getFeedContent(string $uuid, string $type): ?string
    {
        $singular = rtrim($type, 's'); // products→product, customers→customer, orders→order

        if (FeedStorageService::isConfigured()) {
            try {
                $storage = FeedStorageService::create();
                $key = $singular . '/' . $uuid . '/' . $singular . '.xml';
                return $storage->exists($key) ? $storage->get($key) : null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        $path = XmlFeed::getFeedsBasePath() . '/' . $singular . '/' . $uuid . '/' . $singular . '.xml';

        return is_file($path) ? file_get_contents($path) : null;
    }
}
