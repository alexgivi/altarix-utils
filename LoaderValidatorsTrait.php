<?php

namespace altarix\utils;

use thamtech\uuid\helpers\UuidHelper;
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
     * @param array $array Список допустимых значений
     * @param string $userMessage Тест сообщения для пользователя (ошибка клиента).
     * @return $this
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
     * Проверить наличие значения в каком-либо хранилище.
     * Например, так:
     * ```
     * $loader->getString('ssoid')->fieldInSource(Contact::find(), 'ssoid')->return();
     * ```
     * Тут будет осуществлена проверка, что в clickhouse в таблице contact есть запись,
     * у которой значение ssoid равно загружаемому значению.
     *
     * @param QuerySourceInterface $source
     * @param string $field
     * @param null|string $userMessage
     *
     * @return Loader
     */
    public function fieldInSource(QuerySourceInterface $source, $field, $userMessage = null): self
    {
        if (!$this->require && empty($this->value)) {
            return $this;
        }

        if (!$source->where($field, '=', $this->value)->count()) {
            $this->error("Запись с указанным значением $field не найдена.", $userMessage);
        }

        return $this;
    }

    /**
     * Проверяет уникальность значения поля для ActiveRecord модели.
     *
     * @param BaseModel $model Класс Active Record
     * @param string $userMessage Тест сообщения для пользователя (ошибка клиента).
     * @param string $relationAttribute Дополнительное поле для фильтра.
     *   Если подставить `date`, то поиск будет проиходить только в моделях с полем `date =
     *     $model->date`
     * @return Loader
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
     * @param $model
     * @param QuerySourceInterface $source
     * @param string $userMessage Тест сообщения для пользователя (ошибка клиента).
     * @param string $relationAttribute Дополнительное поле для фильтра.
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
     * @param string $format Требуемый формат задания даты
     * @param string $userMessage Тест сообщения для пользователя (ошибка клиента).
     * @return $this
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
     * @param $class
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

    /**
     * Максимальное значение - для чисел.
     *
     * @param int $value
     * @param null $userMessage
     * @return LoaderValidatorsTrait
     */
    public function max(int $value, $userMessage = null): self
    {
        if (is_numeric($this->value)) {
            $this->error("Значение {$this->field} не является числом");
        }
        if (intval($this->value) > $value) {
            $this->error("Значение {$this->field} не может быть больше {$value}", $userMessage);
        }

        return $this;
    }

    /**
     * Минимальное значение - для чисел.
     *
     * @param int $value
     * @param null $userMessage
     * @return LoaderValidatorsTrait
     */
    public function min(int $value, $userMessage = null): self
    {
        if (is_numeric($this->value)) {
            $this->error("Значение {$this->field} не является числом");
        }
        if (intval($this->value) < $value) {
            $this->error("Значение {$this->field} не может быть больше {$value}", $userMessage);
        }

        return $this;
    }

    /**
     * Соответствие регулярному выражению - для строк.
     *
     * @param string $expression
     * @param null $userMessage
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

    /**
     * Является uuid-строкой.
     *
     * @param string|null $userMessage
     * @return Loader
     */
    public function uuid($userMessage = null): self
    {
        if (!$this->require && empty($this->value)) {
            return $this;
        }

        if (!UuidHelper::isValid($this->value)) {
            $this->error("Некорректное значение {$this->field}", $userMessage);
        }

        return $this;
    }

    /**
     * Минимальная длина - для строк.
     *
     * @param int $value
     * @param null $userMessage
     * @return self
     */
    public function minLength(int $value, $userMessage = null): self
    {
        if (!is_string($this->value)) {
            $this->error("Значение {$this->field} не является строкой");
        }

        if (mb_strlen($this->value, 'UTF-8') < $value) {
            $unit = Helper::plural($value, 'символа', 'символов', 'символов');
            $this->error("Значение {$this->field} не может быть меньше {$value} {$unit}", $userMessage);
        }

        return $this;
    }

    /**
     * Максимальная длина - для строк.
     *
     * @param int $value
     * @param null $userMessage
     * @return self
     */
    public function maxLength(int $value, $userMessage = null): self
    {
        if (!is_string($this->value)) {
            $this->error("Значение {$this->field} не является строкой");
        }

        if (mb_strlen($this->value, 'UTF-8') > $value) {
            $unit = Helper::plural($value, 'символа', 'символов', 'символов');
            $this->error("Значение {$this->field} не может быть больше {$value} {$unit}", $userMessage);
        }

        return $this;
    }

    /**
     * Максимальный размер - для base64 контента.
     *
     * @param int $size
     * @param string|null $userMessage
     * @return self
     */
    public function maxSize(int $size, $userMessage = null): self
    {
        if (strlen($this->value) > $size) {
            $unit = Helper::plural($size, 'байта', 'байт', 'байт');
            $this->error("Размер $this->field не дожен быть больше $size $unit", $userMessage);
        }

        return $this;
    }

    /**
     * Проверить mime-тип - для base64 контента.
     *
     * @param string[] $types
     * @param null $userMessage
     * @return LoaderValidatorsTrait
     */
    public function mimeTypeIn($types, $userMessage = null): self
    {
        if (!$this->require && empty($this->value)) {
            return $this;
        }

        $finfo = new \finfo();
        $mimeType = $finfo->buffer($this->value, FILEINFO_MIME_TYPE);
        $types = (array)$types;
        if (!in_array($mimeType, $types)) {
            $types = implode(', ', $types);
            $this->error("Некорректный mime-тип. Допустимо: $types.", $userMessage);
        }

        return $this;
    }

    /**
     * Проверить ширину изображения - для base64 контента, только для изображений.
     *
     * @param integer $width
     * @param null $userMessage
     * @return LoaderValidatorsTrait
     */
    public function imageWidthEquals($width, $userMessage = null): self
    {
        if (empty($this->value)) {
            return $this;
        }
        $data = getimagesizefromstring($this->value);

        // @HACK - допускаем погрешность размеров на 1px, т.к. кроппер на фронте делает неточный resize
        if (!$data || abs($data[0] - $width) > 1) {
            $this->error("Неверная ширина изображения. Требуется: $width px.", $userMessage);
        }

        return $this;
    }

    /**
     * Проверить высоту изображения - для base64 контента, только для изображений.
     *
     * @param integer $height
     * @param null $userMessage
     * @return LoaderValidatorsTrait
     */
    public function imageHeightEquals($height, $userMessage = null): self
    {
        if (empty($this->value)) {
            return $this;
        }
        $data = getimagesizefromstring($this->value);

        // @HACK - допускаем погрешность размеров на 1px, т.к. кроппер на фронте делает неточный resize
        if (!$data || abs($data[1] - $height) > 1) {
            $this->error("Неверная высота изображения. Требуется: $height px.", $userMessage);
        }

        return $this;
    }

    /**
     * Произвольная валидация.
     * Принимает функцию с аргументами значения, обязательности и названия атрибута, возвращающую bool.
     * Может кидать исключения, описывающие ошибки.
     * ```
     * function ($value, $required, $field) {
     *   if (...) {
     *     throw new ClientException('Некорректное значение!');
     *   }
     *
     *   return true;
     * }
     * ```
     *
     * @param callable $callback функция проверки
     * @param null $userMessage
     * @return LoaderValidatorsTrait
     */
    public function customValidation($callback, $userMessage = null): self
    {
        if (!$callback($this->value, $this->require, $this->field)) {
            $this->error('Произвольная валидация прошла неуспешно', $userMessage);
        }

        return $this;
    }

    public function notEmpty($userMessage = null)
    {
        if (empty($this->value) || $this->value === []) {
            $this->error("Поле {$this->field} не может быть пустым", $userMessage);
        }
        return $this;
    }
}
