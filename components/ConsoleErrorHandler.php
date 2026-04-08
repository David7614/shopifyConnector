<?php
declare(strict_types=1);

namespace app\components;

/**
 * Extends Yii2 console error handler to suppress E_DEPRECATED notices.
 *
 * phpclassic/php-shopify calls curl_close() which is deprecated in PHP 8.4+.
 * Without this, Yii2 converts the deprecation notice to an exception.
 */
class ConsoleErrorHandler extends \yii\console\ErrorHandler
{
    public function handleError($code, $message, $file, $line): bool
    {
        if ($code === E_DEPRECATED || $code === E_USER_DEPRECATED) {
            return false;
        }

        return parent::handleError($code, $message, $file, $line);
    }
}
