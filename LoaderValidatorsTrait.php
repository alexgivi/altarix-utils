<?php

namespace altarix\utils;

use yii\base\DynamicModel;
use yii\validators\DateValidator;
use yii\validators\ExistValidator;

/**
 * Trait LoaderValidatorsTrait
 * @package app\core
 */
trait LoaderValidatorsTrait
{
    /**
     * Проверяет, что объект входит в заданный массив $array
     * @param array  $array       Список допустимых значений
     * @param string $userMessage Тест сообщения для пользователя (ошибка клиента).
     *
     * @return $this
     *
     * @throws ApiException
     */
    public function in($array, $userMessage = null): self
    {
        $values = is_array($this->value) ? $this->value : [$this->value];

        foreach ($values as $value) {
            $data = [$this->field => $value];
            $ruleList = [
                $this->field,
                'in',
                'range' => $array,
                'message' => $userMessage,
                'skipOnEmpty' => !$this->require,
                'allowArray' => true,
            ];

            try {
                $validator = DynamicModel::validateData($data, [$ruleList]);
                $validateResult = $validator->validate($this->field);
            } catch (\Exception $e) {
                throw new ApiException($e->getMessage());
            }

            if (!$validateResult) {
                $this->error(current($validator->firstErrors), $userMessage);
            }
        }

        return $this;
    }


    /**
     * @param BaseModel $model                Класс Active Record
     * @param string    $userMessage          Тест сообщения для пользователя (ошибка клиента).
     * @param string    $relationAttribute    Дополнительное поле для фильтра.
     *                                        Если подставить `date`, то поиск будет проиходить только в моделях с полем
     *                                        `date =
     *                                        $model->date`
     *
     * @return Loader
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function unique($model, $userMessage = null, $relationAttribute = null): self
    {
        $data = [$this->field => $this->value];
        $filter = [];

        if ($model->id) {
            $filter = ['<>', 'id', $model->id];
        }

        if (!empty($relationAttribute)) {
            $filter = ['AND', $filter, [$relationAttribute => $model->{$relationAttribute}]];
        }

        $ruleList = [
            $this->field,
            'unique',
            'targetClass' => get_class($model),
            'targetAttribute' => $this->field,
            'message' => $userMessage,
            'filter' => $filter,
            'skipOnEmpty' => !$this->require,
        ];

        $validator = DynamicModel::validateData($data, [$ruleList]);
        $validateResult = $validator->validate($this->field);
        if (!$validateResult) {
            $this->error(current($validator->firstErrors), $userMessage);
        }

        return $this;
    }

    /**
     * Проверяет, что поле является уникальным
     *
     * @param                      $model
     * @param QuerySourceInterface $source
     * @param string               $userMessage       Тест сообщения для пользователя (ошибка клиента).
     * @param string               $relationAttribute Дополнительное поле для фильтра.
     *
     * @return $this
     */
    public function uniqueField($model, QuerySourceInterface $source, $userMessage = null, $relationAttribute = null)
    {
        if ($model->id) {
            $source->where('id', '<>', $model->id);
        }

        if (!empty($relationAttribute)) {
            $source->where($relationAttribute, '=', $model->{$relationAttribute});
        }

        $source->where($this->field, '=', $this->value);

        if ($source->count() > 0) {
            $this->error("Значение «{$this->value}» для «{$this->field}» уже занято.", $userMessage);
        }

        return $this;
    }

    /**
     * Проверяет, что в поле $this->field записана дата в формате $format.
     *
     * @param string $format      Требуемый формат задания даты
     * @param string $userMessage Тест сообщения для пользователя (ошибка клиента).
     *
     * @return $this
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function date($format = null, $userMessage = null): self
    {
        $data = [$this->field => $this->value];
        $ruleList = [
            $this->field,
            DateValidator::class,
            'format' => $format,
            'isEmpty' => function ($value) {
                if ($value === '') {
                    return false;
                }
                return empty($value);
            }
        ];

        $validator = DynamicModel::validateData($data, [$ruleList]);
        $validateResult = $validator->validate($this->field);
        if (!$validateResult) {
            $this->error('', $userMessage ?? current($validator->firstErrors));
        }

        return $this;
    }

    /**
     * Проверяет, что существует модель $class со значением атрибута $attribute равным значению
     * контекстного поля $this->field.
     * @param        $class
     * @param string $attribute
     * @param string $userMessage Тест сообщения для пользователя (ошибка клиента).
     * @return $this
     * @throws \yii\base\InvalidConfigException
     */
    public function exist($class, $attribute = 'id', $userMessage = null): self
    {
        $data = [$this->field => $this->value];
        $ruleList = [
            $this->field,
            ExistValidator::class,
            'targetClass' => $class,
            'targetAttribute' => $attribute,
            'message' => $userMessage,
            'skipOnEmpty' => !$this->require,
        ];

        $validator = DynamicModel::validateData($data, [$ruleList]);
        $validateResult = $validator->validate($this->field);
        if (!$validateResult) {
            $this->error(current($validator->firstErrors), $userMessage);
        }

        return $this;
    }

    public function max(int $value, $userMessage = null): self
    {
        if (is_numeric($this->value)) {
            $this->error("Значение {$this->field} не является числом");
        }

        if (intval($this->value) > $value) {
            $this->error("Значене {$this->field} не может быть больше {$value}", $userMessage);
        }

        return $this;
    }

    public function min(int $value, $userMessage = null): self
    {
        if (is_numeric($this->value)) {
            $this->error("Значение {$this->field} не является числом");
        }

        if (intval($this->value) < $value) {
            $this->error("Значене {$this->field} не может быть больше {$value}", $userMessage);
        }

        return $this;
    }

    /**
     * @param string $expression
     * @param null   $userMessage
     *
     * @return Loader
     */
    public function regExp(string $expression, $userMessage = null): self
    {
        //Данное условие необходимо что бы preg_math не проверяла пустоту в случае если поле не обязательно
        if (!$this->require && empty($this->value)) {
            return $this;
        }

        if (!preg_match($expression, $this->value, $match)) {
            $this->error("Некорректное значение {$this->field}", $userMessage);
        }

        return $this;
    }
}
