<?php

namespace altarix\utils;

use Exception;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

/**
 * Загрузчик данных с клиента. Позволяет снаружи запрашивать данные указав обязательность полей и применяемые валидаторы.
 * Обеспечивает значение по уполчанию для необязателных полей. Обеспечивает сериализацию в заданные форматы.
 * Пример получения обязательного поля:
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

    // Массив вложенности лоадера (используется для вывода сообщения об отсутствии ключа)
    public $index = [];

    // Контекстные переменные (значения могут меняться по ходу выполнения)
    protected $field;
    protected $value;
    protected $require = false;
    protected $isConvert = false; // флаг - входные данные преобразованны к нужному типу

    protected function getSourceValue($name, &$isSet)
    {
        try {
            $value = ArrayHelper::getValue($this->dataSource, $name);
            $isSet = true;
            if ($value === null) {
                $isSet = false;
            }

            return $value;
        } catch (\Exception $e) {
            $isSet = false;

            return null;
        }
    }

    /**
     * Возвращает множество валидаторов для валидации массива - по одному валиадтора на каждый элемент массив
     * @param array $data
     * @return Loader[]
     */
    public static function loadArray(array $data)
    {
        $res = [];
        foreach ($data as $datum) {
            $res[] = new static(['dataSource' => $datum]);
        }
        return $res;
    }

    /**
     * @param string $message Тест сообщения об ошибке (ошибка API).
     * @param string $userMessage Тест сообщения для пользователя (ошибка клиента).
     * @throws ApiException
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
     * Привести к сущности. Поля массива должны быть проверены и присвоены внутри $callback.
     * Пример проверки существования во входном массиве заданного ключа:
     * ```
     * $loader = new Loader(['dataSource' => [
     *     'user' => ['group' => 'admin'],
     * ]]);
     * $user = $loader->getEntity('user', function (UserModel $entity, Loader $formEntity) {
     *     $entity->group = $formEntity->getString('group')->require()->in(User::$allGroups)->return();
     *     return $entity;
     * });
     * ```
     *
     * Если не требуется использование именно этого метода,
     * то лучше использовать получение отдельных полей массива через точку:
     * ```
     * $user = ['group' => $loader->getString('user.group')->in(User::$allGroups)->return()];
     * ```
     * Либо произвольную валидацию:
     * ```
     * $user = $loader->getArray('user')->customValidation(function ($user) {
     *   if (empty($user['group']) || !in_array($user['group'], User::$allGroups)) {
     *      throw new ApiException('Неверная группа');
     *   }
     *   return true;
     * })->return();
     * ```
     *
     * @param string       $name
     * @param callable     $callback
     * @param array|object $default
     * @param bool         $isRequired
     * @return self
     * @throws ApiException
     * @throws Exception
     */
    public function getEntity($name, $callback, $default = [], $isRequired = false): self
    {
        $this->require = $isRequired;
        $sourceValue = $this->getSourceValue($name, $isSet);
        if (!is_object($default) && !is_array($default)) {
            $this->throwDefaultException($name, 'объект или массив', gettype($default));
        }
        if (!is_callable($callback)) {
            throw new Exception('Анонимная функция не определена');
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
            $this->throwTypeException($name, 'объект или массив', gettype($value));
        }
        $formEntity = new self(['dataSource' => $value, 'index' => array_merge($this->index, [$name])]);
        $entity = $default;
        $entityResult = $callback($entity, $formEntity);
        if (is_array($entity) && !is_array($entityResult)) {
            throw new Exception('Анонимная функция должна вернуть переданный в неё массив');
        }
        if (is_object($entity) && !is_object($entityResult)) {
            throw new Exception('Анонимная функция должна вернут экземпляр класса ' . get_class($entity));
        }

        $this->field = $name;
        $this->value = $entityResult;
        $this->isConvert = true;

        return $this;
    }

    /**
     * Привести к строке.
     * @param string $name
     * @param bool $isRequired
     * @param string $default
     * @return Loader
     * @throws Exception
     */
    public function getString($name, $isRequired = false, $default = null): self
    {
        if ($default !== null && !is_string($default)) {
            $this->throwDefaultException($name, 'строка', gettype($default));
        }

        $sourceValue = $this->getSourceValue($name, $isSet);
        if ($isRequired && !$isSet) {
            $this->throwRequiredException($name);
        }

        $this->field = $name;
        $this->value = $isSet ? (string)$sourceValue : $default;
        $this->require = $isRequired;
        $this->isConvert = true;

        if ($isRequired) {
            $this->minLength(1);
        }

        return $this;
    }

    protected function getCurrentIndex($name)
    {
        return !empty($this->index) ? "[" . implode('][', $this->index) . "][$name]" : $name;
    }

    /**
     * @param string $name
     * @throws ApiException
     */
    protected function throwRequiredException($name)
    {
        $index = $this->getCurrentIndex($name);

        throw new ApiException("Отсутствует обязательный параметр {$index}");
    }

    /**
     * @param string $name
     * @param string $requiredType
     * @param string $expectedType
     * @throws ApiException
     */
    protected function throwTypeException($name, $requiredType, $expectedType)
    {
        $index = $this->getCurrentIndex($name);

        throw new ApiException("Некорректное значение {$index}, требуется {$requiredType}, а задан {$expectedType}");
    }

    /**
     * @param string $name
     * @param string $requiredType
     * @param string $expectedType
     * @throws Exception
     */
    protected function throwDefaultException($name, $requiredType, $expectedType)
    {
        $index = $this->getCurrentIndex($name);

        throw new Exception("Некорректное значение по умолчанию {$index}, требуется {$requiredType},  а задан {$expectedType}");
    }

    /**
     * Привести к строке, содержащей контент файла, декодированный из base64.
     *
     * @param string $name
     * @param bool $isRequired
     * @param null $default
     * @return Loader
     * @throws Exception
     */
    public function getBase64File($name, $isRequired = false, $default = null): self
    {
        $this->getString($name, $isRequired, $default);
        $this->value = base64_decode($this->value);
        if ($this->value === false) {
            throw new ApiException('Некорректное содержимое base64 строки. Не удалось декодировать.');
        }

        return $this;
    }

    /**
     * Привести к целому.
     * @param string $name
     * @param bool $isRequired
     * @param integer $default
     * @return self
     * @throws Exception
     */
    public function getInt($name, $isRequired = false, $default = null): self
    {
        if ($default !== null && !is_int($default)) {
            $this->throwDefaultException($name, 'целое число', gettype($default));
        }
        $sourceValue = $this->getSourceValue($name, $isSet);
        if ($isRequired && !$isSet) {
            $this->throwRequiredException($name);
        }

        $this->field = $name;
        $this->value = $isSet ? filter_var($sourceValue, FILTER_VALIDATE_INT) : $default;
        $this->require = $isRequired;
        $this->isConvert = true;

        return $this;
    }

    /**
     * Привести к булевому.
     * @param string $name
     * @param bool $isRequired
     * @param boolean $default
     * @return self
     * @throws Exception
     */
    public function getBool($name, $isRequired = false, $default = null): self
    {
        if ($default !== null && !is_bool($default)) {
            $this->throwDefaultException($name, 'булевое значение', gettype($default));
        }
        $sourceValue = $this->getSourceValue($name, $isSet);
        if ($isRequired && !$isSet) {
            $this->throwRequiredException($name);
        }

        $this->field = $name;
        $this->value = $isSet ? filter_var($sourceValue, FILTER_VALIDATE_BOOLEAN) : $default;
        $this->require = $isRequired;
        $this->isConvert = true;

        return $this;
    }

    /**
     * Привести к массиву.
     * @param string $name
     * @param bool $isRequired
     * @param array $default
     * @return self
     * @throws Exception
     */
    public function getArray($name, $isRequired = false, $default = null): self
    {
        if ($default !== null && !is_array($default)) {
            $this->throwDefaultException($name, 'массив', gettype($default));
        }
        $sourceValue = $this->getSourceValue($name, $isSet);
        if ($isRequired && !$isSet) {
            $this->throwRequiredException($name);
        }

        $this->field = $name;
        $this->value = $isSet ? (array)$sourceValue : $default;
        if (!is_array($this->value) && $this->value !== null) {
            throw new ApiException("{$this->field} - параметр должен быть массивом.");
        }
        $this->require = $isRequired;
        $this->isConvert = true;

        return $this;
    }

    /**
     * Вернет конечное значение (после всех проверок и преобразований) параметра.
     * @return string|bool|int|array|object
     * @throws Exception
     */
    public function return()
    {
        if (!$this->isConvert) {
            throw new Exception('Параметр не приведен ни к одному типу (сначала следует вызвать один из get*() методов)');
        }

        return $this->value;
    }

    /**
     * Возвращает массив с объектами произвольной структуры
     * @param \Closure $callback Фунция-обработчик. Функция принимает 1 параметр $loader - загрузчик, который содержит 1 элемент массива для валидации
     * Фунция должна вернуть **один** элемент - т.е. callback будет вызван на каждый элемент массива
     * @return array Возвращает полученную коллекцию объектов
     * @throws ApiException
     */
    public function returnCustomArray($callback)
    {
        if (!is_array($this->value)) {
            throw new ApiException("{$this->field} - параметр должен быть массивом.");
        }
        $res = [];
        foreach ($this->value as $key => $value) {
            $loader = new self(['dataSource' => $value, 'index' => array_merge($this->index, [$this->field, $key])]);
            $res[] = $callback($loader);
        }
        return $res;
    }
}
