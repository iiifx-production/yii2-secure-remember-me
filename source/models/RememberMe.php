<?php

namespace iiifx\yii2\SecureRememberMe\models;

use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%remember_me}}".
 *
 * @property string $selector
 * @property int    $user_id
 * @property string $token_hash
 * @property string $date_expires
 */
class RememberMe extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%remember_me}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['selector'], 'required'],
            [['user_id'], 'integer'],
            [['date_expires'], 'safe'],
            [['selector'], 'string', 'max' => 32],
            [['token_hash'], 'string', 'max' => 128],
            [['selector'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'selector' => 'Selector',
            'user_id' => 'User ID',
            'token_hash' => 'Token Hash',
            'date_expires' => 'Date Expires',
        ];
    }
}
