<?php

namespace altarix\utils;

use yii\db\ActiveRecord;
use yii\db\Connection;
use yii\helpers\ArrayHelper;

/**
 * Class BaseModel
 * @package app\core
 * @property mixed  $id
 * @property string dateCreated
 * @property string dateUpdated
 * @property string $modelType
 */
class BaseModel extends ActiveRecord
{
    const FIXTURE_CONTACT_FIRST_NAME = 'Fixture';
    const FIXTURE_CONTACT_MID_NAME = 'Fixtures';

    protected $isDeleteRecord = false;

    /**
     * @var array $snapshotList
     */
    protected $snapshotList = [];

    /**
     * {@inheritdoc}
     */
    public function afterFind()
    {
        parent::afterFind();
        $this->snapshotList[] = $this->attributes;
    }

    /**
     * Аналог для self::isAttributeChanged(). Позволяет точно сказать изменился ли атрибут модели от момента получения
     * из базы и до текущего.
     *
     * @param string $name Имя атрибута
     *
     * @return bool
     */
    public function isAttributeRealChanged($name): bool
    {
        $slice = ArrayHelper::getColumn($this->snapshotList, $name);

        return count(array_unique($slice)) > 1;
    }

    /**
     * {@inheritdoc}
     */
    public function afterDelete()
    {
        $this->isDeleteRecord = true;

        parent::afterDelete();
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert && $this->hasAttribute('dateCreated')) {
            $this->updateAttributes(['dateCreated' => date('Y-m-d H:i:s')]);
        }

        if ($this->hasAttribute('dateUpdated')) {
            $this->updateAttributes(['dateUpdated' => date('Y-m-d H:i:s')]);
        }

        $this->snapshotList[] = $this->attributes;
    }

    /**
     * Пытается сохранить модель, в случае ошибки выбрасывает исключение
     * @throws \Exception
     */
    public function trySave()
    {
        if ($this->isDeleteRecord) {
            throw new \Exception('Попытка сохранить удаленную модель ' . static::class . " #$this->id:" . Helper::jsonPrettyPrint($this->errors));
        }
        if (!$this->save()) {
            throw new \Exception('Failed to save model ' . static::class . ' #' . $this->id . ':' . Helper::jsonPrettyPrint($this->errors));
        }
        $this->refresh();
    }

    /**
     * @return string
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function getModelType()
    {
        return $this->formName();
    }

    /**
     * @param array $attributes
     *
     * @return static
     */
    public static function findOrNew($attributes)
    {
        $model = static::find()->where($attributes)->one();

        if (empty($model)) {
            $model = new static();
            foreach ($attributes as $attribute => $value) {
                if ($model->canSetProperty($attribute)) {
                    $model->$attribute = $value;
                }
            }
        }

        return $model;
    }

    /**
     * Удаляет все записи, найденные по переданные условию
     * Удаляют каждую записать, как запись AR, т.е. выполняя все beforeDelete и AfterDelete
     * @param array           $condition
     * @param Connection|null $db база. если null - используется Yii::$app->db
     *
     * @return boolean
     *
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public static function deleteEach($condition = [], $db = null)
    {
        if (empty($db)) {
            $db = \Yii::$app->db;
        }

        $transaction = $db->beginTransaction();

        $models = self::find()->where($condition)->all();

        foreach ($models as $model) {
            if ($model->delete() === false) {
                $transaction->rollBack();
                return false;
            }
        }

        $transaction->commit();

        return true;
    }
}
