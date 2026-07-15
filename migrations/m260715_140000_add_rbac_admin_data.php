<?php

use yii\base\InvalidConfigException;
use yii\db\Migration;
use yii\rbac\DbManager;

/**
 * Recreates the 'admin' RBAC role and its assignments after the switch from
 * PhpManager (rbac/items.php + rbac/assignments.php) to DbManager.
 */
class m260715_140000_add_rbac_admin_data extends Migration
{
    private const ADMIN_USER_IDS = [122, 166, 141];

    private function getAuthManager(): DbManager
    {
        $authManager = Yii::$app->getAuthManager();
        if (!$authManager instanceof DbManager) {
            throw new InvalidConfigException('authManager must be configured as DbManager before running this migration.');
        }

        return $authManager;
    }

    public function safeUp()
    {
        $auth = $this->getAuthManager();

        $admin = $auth->getRole('admin');
        if ($admin === null) {
            $admin = $auth->createRole('admin');
            $auth->add($admin);
        }

        foreach (self::ADMIN_USER_IDS as $userId) {
            if ($auth->getAssignment('admin', $userId) === null) {
                $auth->assign($admin, $userId);
            }
        }
    }

    public function safeDown()
    {
        $auth = $this->getAuthManager();

        foreach (self::ADMIN_USER_IDS as $userId) {
            $auth->revoke($auth->getRole('admin'), $userId);
        }
    }
}
