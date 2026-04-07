<?php
namespace app\models;

use yii\db\ActiveRecord;
// use Yii;

/**
 * This is the model class for table "Session".
 *
 * @property int $id
 * @property string $shop
 * @property string $state
 * @property int $isOnline
 * @property string $scope
 * @property string $expires
 * @property string $accessToken
 * @property string $userId
 * @property string $firstName
 * @property string $lastName
 * @property string $email
 * @property string $accountOwner
 * @property string $locale
 * @property string $collaborator
 * @property string $emailVerified
 */
class Session extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'Session';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'shop', 'accessToken'], 'required'],
            [['id'], 'string', 'max' => 255],
            [['shop'], 'string', 'max' => 255],
            [['state'], 'string', 'max' => 255],
            [['isOnline'], 'integer'],
            [['scope'], 'string', 'max' => 255],
            [['expires'], 'string', 'max' => 255],
            [['accessToken'], 'string', 'max' => 255],
            [['userId'], 'integer'],
            [['firstName'], 'string', 'max' => 255],
            [['lastName'], 'string', 'max' => 255],
            [['email'], 'string', 'max' => 255],
            [['accountOwner'], 'integer'],
            [['locale'], 'string', 'max' => 255],
            [['collaborator'], 'integer'],
            [['emailVerified'], 'integer'],
            // [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'shop' => 'Shop',
            'state' => 'State',
            'isOnline' => 'Is online',
            'scope' => 'Scope',
            'expires' => 'Expires',
            'accessToken' => 'Access token',
            'userId' => 'User id',
            'firstName' => 'First name',
            'lastName' => 'Last name',
            'email' => 'Email',
            'accountOwner' => 'Account owner',
            'locale' => 'Locale',
            'collaborator' => 'Collaborator',
            'emailVerified' => 'Email verified',
        ];
    }

    public function getShop()
    {
        return $this->shop;
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    // /**
    //  * Gets query for [[User]].
    //  *
    //  * @return \yii\db\ActiveQuery
    //  */
    // public function getUser()
    // {
    //     return $this->hasOne(User::className(), ['id' => 'user_id']);
    // }
}
