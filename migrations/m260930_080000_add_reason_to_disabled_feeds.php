<?php

use yii\db\Migration;

/**
 * Records why a feed was disabled and when.
 *
 * Until now disabled_feeds held only the pair (user, type) and nothing ever
 * wrote to it - the table was read by Queue::checkQueueConstraints() and filled
 * by hand, if at all. With feeds being switched off automatically on permanent
 * failures, an entry has to carry its own justification, otherwise nobody can
 * tell an automatic shutdown from a deliberate one.
 */
class m260930_080000_add_reason_to_disabled_feeds extends Migration
{
    public function safeUp()
    {
        $this->addColumn('disabled_feeds', 'reason', $this->text()->null());
        $this->addColumn('disabled_feeds', 'disabled_at', $this->dateTime()->null());
        $this->addColumn('disabled_feeds', 'disabled_by', $this->string(32)->null()
            ->comment('auto|admin - who switched the feed off'));
    }

    public function safeDown()
    {
        $this->dropColumn('disabled_feeds', 'disabled_by');
        $this->dropColumn('disabled_feeds', 'disabled_at');
        $this->dropColumn('disabled_feeds', 'reason');
    }
}
