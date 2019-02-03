<?php

/**
 * Class m150829_200000_CREATE_remember_me
 */
class m150829_200000_CREATE_remember_me extends \yii\db\Migration
{
    /**
     * @var string
     */
    public $tableName = '{{%remember_me}}';

    /**
     * {@inheritdoc}
     */
    public function up()
    {
        $this->createTable($this->tableName, [
            'selector' => "VARCHAR(32) NOT NULL PRIMARY KEY",
            'user_id' => "INT(11) UNSIGNED NULL DEFAULT NULL",
            'token_hash' => "VARCHAR(128) NOT NULL DEFAULT ''",
            'date_expires' => "TIMESTAMP NOT NULL DEFAULT NOW()",
        ]);
        $this->createIndex('U_selector', $this->tableName, 'selector', TRUE);
        $this->createIndex('K_date_expires', $this->tableName, 'date_expires');
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->dropTable($this->tableName);
    }
}
