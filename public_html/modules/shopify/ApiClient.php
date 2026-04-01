<?php
declare(strict_types=1);

namespace app\modules\shopify;

use PHPShopify\ShopifySDK;

class ApiClient
{
    public static function getClient($session)
    {
        $config = [
            'ShopUrl' => $session->getShop(),
            'AccessToken' => $session->getAccessToken(),
            'ApiVersion' => '2025-07',
        ];

        // ShopifySDK::config($config);

        // return new ShopifySDK;

        return new ShopifySDK($config);
    }
}
