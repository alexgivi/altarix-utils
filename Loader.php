<?php

namespace altarix\utils;

use Exception;
use RuntimeException;
use yii\base\BaseObject;

/**
 * Загрузчик данных с клиента. Позволяет снаружи запрашивать данные указав обязательность полей и применяемые
 * валидаторы. Обеспечивает значение по уполчанию для необязателных полей. Обеспечивает сериализацию в заданные
 * форматы. Пример получения обязательного поля:
 *   $form->require('title')->unique($campaign, 'Кампания с таким названием уже существует')->toString();
 * Пример получения необязательного поля:
 *   $form->get('object')->in(Category::objects())->toString();
 *
 * @property mixed $value
 */
class Loader extends BaseObject
{
    use LoaderValidatorsTrait;

    // Массив данных из источника
    public $dataSource;

    // Контекстные переменные (значения могут меняться по ходу выполнения)
    protected $field;
    protected $value;
    protected $isEmpty = null;
    protected $require = false;
    protected $isConvert = false; // флаг - входные данные преобразованны к нужному типу

    protected function getSourceValue($name, &$isSet)
    {
        if (is_object($this->dataSource)) {
            $isSet = isset($this->dataSource->{$name});
            if ($isSet) {
                return $this->dataSource->{$name};
            }
        } else {
            $isSet = isset($this->dataSource[$name]);
            if ($isSet) {
                return $this->dataSource[$name];
            }
        }

        return null;
    }

    /**
     * @param string $message     Тест сообщения об ошибке (ошибка API).
     * @param string $userMessage Тест сообщения для пользователя (ошибка клиента).
     *
     * @throws ApiException
     *
     * @throws ClientException
     */
    protected function error($message, $userMessage = null)
    {
        if ($userMessage) {
            throw new ClientException($userMessage);
        }

        throw new ApiException($message);
    }


    /**
     * Метод через замыкание определяет является ли параметр пустым.
     * По умолчанию использует результат функции empty(). Поэтому вызов этого метода может потребоваться в ситуации
     * когда входной параметр является обязательным, но при этом его значение это булев тип. Или null. Т.е. все те
     * варианты при которых empty() возвращает true. В анонимную функцию передается один аргумент - входной параметр.
     * Пример:
     *   $isActive = $form->getBool('isActive', true)->checkIsEmpty(function($value) {
     *       return gettype($value) != 'boolean';
     *   })->return();
     * @param callable $callback Функция определяющая что есть "пусто".
     *
     * @return self
     *
     * @see http://php.net/manual/ru/function.empty.php
     *
     * @throws Exception
     * @throws RuntimeException
     */
    public function checkIsEmpty($callback = null): self
    {
        if (!$this->isConvert) {
            throw new Exception('Параметр не приведен ни к одному типу (сначала следует вызвать один из get*() методов)');
        }

        if (is_callable($callback)) {
            $checkResult = $callback($this->value);
            if (!is_bool($checkResult)) {
                throw new RuntimeException('Замыкание должно возвращать boolean, а вернула ' . gettype($checkResult));
            }
            $this->isEmpty = $checkResult;
        } elseif ($callback === null) {
            $this->isEmpty = empty($this->value);
        } else {
            throw new RuntimeException('Входной параметр должен быть функцией обратного вызова');
        }

        return $this;
    }

    /**
     * Привести к сущности. Поля массива должны быть проверены и присвоены внутри $callback.
     * Пример проверки существования во входном массиве заданного ключа:
     *   $loader = new Loader(['dataSource' => [
     *       'user' => ['group' => 'admin'],
     *   ]]);
     *   $user = $loader->getEntity('user', function (UserModel $entity, Loader $formEntity) {
     *       $entity->group = $formEntity->getString('group')->require()->in(User::$allGroups)->return();
     *       return $entity;
     *   });
     *
     * @param string       $name
     * @param callable     $callback
     * @param array|object $default
     * @param bool         $isRequired
     *
     * @return self
     *
     * @throws ApiException
     */
    public function getEntity($name, $callback, $default = [], $isRequired = false): self
    {
        $this->require = $isRequired;

        $sourceValue = $this->getSourceValue($name, $isSet);
        if (!is_object($default) && !is_array($default)) {
            throw new ApiException('Некорректное значение по умолчанию, требуется объект или массив');
        }
        if (!is_callable($callback)) {
            throw new RuntimeException('Анонимная функция не определена');
        }

        // Особый случай: параметр не обязательный и его нет, то вызывать $callback нет смысла
        if (!$this->require && !$isSet) {
            $this->field = $name;
            $this->value = $default;
            $this->isConvert = true;
            return $this;
        }

        $value = $isSet ? $sourceValue : $default;
        if (!is_object($value) && !is_array($value)) {
            throw new RuntimeException('Некорректное значение, требуется объект или массив, а задан ' . gettype($value));
        }

        $formEntity = new self(['dataSource' => $value]);
        $entity = $default;
        $entityResult = $callback($entity, $formEntity);

        if (is_array($entity) && !is_array($entityResult)) {
            throw new RuntimeException('Анонимная функция должна вернуть переданный в неё массив');
        }
        if (is_object($entity) && !is_object($entityResult)) {
            throw new RuntimeException('Анонимная функция должна вернут экземпляр класса ' . get_class($entity));
        }

        $this->field = $name;
        $this->value = $entityResult;
        $this->isConvert = true;

        return $this;
    }

    /**
     * Привести к строке.
     * @param string $name
     * @param bool   $isRequired
     * @param string $default
     *
     * @return Loader
     *
     * @throws Exception
     */
    public function getString($name, $isRequired = false, $default = null): self
    {
        if ($default !== null && !is_string($default)) {
            throw new Exception('Некорректное значение по умолчанию, требуется строка, а передан ' . gettype($default));
        }

        $sourceValue = $this->getSourceValue($name, $isSet);
        if ($isRequired && !$isSet) {
            throw new ApiException("Отсутствует обязательный параметр {$name}");
        }

        $this->field = $name;
        $this->value = $isSet ? (string)$sourceValue : $default;
        $this->isEmpty = null;
        $this->require = $isRequired;
        $this->isConvert = true;

        return $this;
    }


    /**
     * Привести к целому.
     * @param string  $name
     * @param bool    $isRequired
     * @param integer $default
     *
     * @return self
     *
     * @throws Exception
     */
    public function getInt($name, $isRequired = false, $default = null): self
    {
        if ($default !== null && !is_int($default)) {
            throw new Exception('Некорректное значение по умолчанию, требуется целое, а передан ' . gettype($default));
        }

        $sourceValue = $this->getSourceValue($name, $isSet);
        if ($isRequired && !$isSet) {
            throw new ApiException("Отсутствует обязательный параметр {$name}");
        }

        $this->field = $name;
        $this->value = $isSet ? filter_var($sourceValue, FILTER_VALIDATE_INT) : $default;
        $this->isEmpty = null;
        $this->require = $isRequired;
        $this->isConvert = true;

        return $this;
    }

    /**
     * Привести к булевому.
     * @param string  $name
     * @param bool    $isRequired
     * @param boolean $default
     *
     * @return self
     *
     * @throws Exception
     */
    public function getBool($name, $isRequired = false, $default = null): self
    {
        if ($default !== null && !is_bool($default)) {
            throw new Exception('Некорректное значение по умолчанию, требуется булев, а передан ' . gettype($default));
        }

        $sourceValue = $this->getSourceValue($name, $isSet);
        if ($isRequired && !$isSet) {
            throw new ApiException("Отсутствует обязательный параметр {$name}");
        }

        $this->field = $name;
        $this->value = $isSet ? filter_var($sourceValue, FILTER_VALIDATE_BOOLEAN) : $default;
        $this->isEmpty = !$isSet;
        $this->require = $isRequired;
        $this->isConvert = true;

        return $this;
    }

    /**
     * Привести к массиву.
     * @param string $name
     * @param bool   $isRequired
     * @param array  $default
     *
     * @return self
     *
     * @throws Exception
     */
    public function getArray($name, $isRequired = false, $default = null): self
    {
        if ($default !== null && !is_array($default)) {
            throw new Exception('Некорректное значение по умолчанию, должен быть массив, а передан ' . gettype($default));
        }

        $sourceValue = $this->getSourceValue($name, $isSet);
        if ($isRequired && !$isSet) {
            throw new ApiException("Отсутствует обязательный параметр {$name}");
        }

        $this->field = $name;
        $this->value = $isSet ? (array)$sourceValue : $default;
        $this->isEmpty = null;
        $this->require = $isRequired;
        $this->isConvert = true;

        return $this;
    }

    /**
     * Вернет конечное значение (после всех проверок и преобразований) параметра.
     *
     * @return string|bool|int|array|object
     *
     * @throws ApiException
     * @throws Exception
     */
    public function return()
    {
        if (!$this->isConvert) {
            throw new Exception('Параметр не приведен ни к одному типу (сначала следует вызвать один из get*() методов)');
        }

        if ($this->isEmpty === null) {
            $this->checkIsEmpty();
        }

        if ($this->require && $this->isEmpty) {
            throw new ApiException("Обязательный параметр {$this->field} пустой");
        }

        return $this->value;
    }
}
