<?php

/**
 * This is the model base class for the table "game".
 * DO NOT MODIFY THIS FILE! It is automatically generated by giix.
 * If any changes are necessary, you must set or override the required
 * property or method in class "Game".
 *
 * Columns in table "game" available as properties of the model,
 * followed by relations of table "game" available as properties of the model.
 *
 * @property integer $id
 * @property integer $active
 * @property integer $number_played
 * @property string $unique_id
 * @property string $created
 * @property string $modified
 *
 * @property GamePartner[] $gamePartners
 * @property Collection[] $collections
 * @property Plugin[] $plugins
 * @property PlayedGame[] $playedGames
 * @property User[] $users
 */
abstract class BaseGame extends GxActiveRecord {

	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public function tableName() {
		return 'game';
	}

	public static function label($n = 1) {
		return Yii::t('app', 'Game|Games', $n);
	}

	public static function representingColumn() {
		return 'unique_id';
	}

	public function rules() {
		return array(
			array('unique_id, created, modified', 'required'),
			array('active, number_played', 'numerical', 'integerOnly'=>true),
			array('unique_id', 'length', 'max'=>45),
			array('active, number_played', 'default', 'setOnEmpty' => true, 'value' => null),
			array('id, active, number_played, unique_id, created, modified', 'safe', 'on'=>'search'),
		);
	}

	public function relations() {
		return array(
			'gamePartners' => array(self::HAS_MANY, 'GamePartner', 'game_id'),
			'collections' => array(self::MANY_MANY, 'Collecion', 'game_to_collection(game_id, collection_id)'),
			'plugins' => array(self::MANY_MANY, 'Plugin', 'game_to_plugin(game_id, plugin_id)'),
			'playedGames' => array(self::HAS_MANY, 'PlayedGame', 'game_id'),
			'users' => array(self::MANY_MANY, 'User', 'user_to_game(game_id, user_id)'),
		);
	}

	public function pivotModels() {
		return array(
			'collections' => 'GameToCollection',
			'plugins' => 'GameToPlugin',
			'users' => 'UserToGame',
		);
	}

	public function attributeLabels() {
		return array(
			'id' => Yii::t('app', 'ID'),
			'active' => Yii::t('app', 'Active'),
			'number_played' => Yii::t('app', 'Number Played'),
			'unique_id' => Yii::t('app', 'Unique'),
			'created' => Yii::t('app', 'Created'),
			'modified' => Yii::t('app', 'Modified'),
			'gamePartners' => null,
			'collections' => null,
			'plugins' => null,
			'playedGames' => null,
			'users' => null,
		);
	}

	public function search() {
		$criteria = new CDbCriteria;

		$criteria->compare('id', $this->id);
		$criteria->compare('active', $this->active);
		$criteria->compare('number_played', $this->number_played);
		$criteria->compare('unique_id', $this->unique_id, true);
		$criteria->compare('created', $this->created, true);
		$criteria->compare('modified', $this->modified, true);

		return new CActiveDataProvider($this, array(
			'criteria' => $criteria,
			'pagination'=>array(
        'pageSize'=>Yii::app()->fbvStorage->get("settings.pagination_size"),
      ),
		));
	}
}