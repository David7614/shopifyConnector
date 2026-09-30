<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "disabled_feeds".
 *
 * @property int $id
 * @property int $user_id
 * @property string $integration_type
 *
 * @property User $user
 */
class DisabledFeeds extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'disabled_feeds';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'integration_type'], 'required'],
            [['user_id'], 'integer'],
            [['integration_type'], 'string', 'max' => 255],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'integration_type' => 'Integration Type',
        ];
    }

    const BY_AUTO  = 'auto';
    const BY_ADMIN = 'admin';

    /**
     * Switches a feed off for one user, or refreshes the reason if it is off
     * already. Queue::checkQueueConstraints() reads this table, so an entry
     * here stops the queue from running at all.
     *
     * @return bool True when the feed was not disabled before.
     */
    public static function disable(int $userId, string $type, string $reason, string $by = self::BY_AUTO): bool
    {
        $existing = self::findOne(['user_id' => $userId, 'integration_type' => $type]);
        $isNew = $existing === null;

        $record = $existing ?: new self();
        $record->user_id          = $userId;
        $record->integration_type = $type;
        $record->reason           = $reason;
        $record->disabled_at      = date('Y-m-d H:i:s');
        $record->disabled_by      = $by;
        $record->save(false);

        return $isNew;
    }

    public static function enable(int $userId, string $type): bool
    {
        $record = self::findOne(['user_id' => $userId, 'integration_type' => $type]);

        if (!$record) {
            return false;
        }

        return (bool) $record->delete();
    }

    public static function isDisabled(int $userId, string $type): bool
    {
        return self::find()->where(['user_id' => $userId, 'integration_type' => $type])->exists();
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }
}
