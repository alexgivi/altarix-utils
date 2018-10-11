<?php

namespace altarix\utils;

use OutOfBoundsException;
use InvalidArgumentException;
use Exception;

/**
 * Базовый класс для работы с перечислениями. При расширении следует в потомках:
 *   1) создать публичные статичные переменные, одна переменная порождает одно значение для перечисления;
 *   2) в файле с классом тут же их инициализировать.
 * Пример:
 * class Scenario extends Enum
 * {
 *     public static $none;
 *     public static $md5;
 * }
 * Scenario::$none = new Scenario('none', 'Без сценария');
 * Scenario::$md5 = new Scenario('md5', 'MD5');
 */
abstract class Enum
{
    protected static $valueMap = [];

    private $value;
    private $title;

    /**
     * Создание одного значения для перечисления.
     * @param integer|float|string|boolean $value Величина значения
     * @param string $title Текст метки для значения $value
     * @throws InvalidArgumentException
     */
    public function __construct($value, $title)
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Значения в перечислении могут только скалярного типа');
        }
        if (!is_string($title)) {
            throw new InvalidArgumentException('Метка title должна быть текстом');
        }
        $this->value = $value;
        $this->title = $title;
    }

    /**
     * Величина значения.
     * @return bool|float|int|string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Метка для значения.
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return bool|float|int|string
     */
    public function __toString()
    {
        return $this->value;
    }

    /**
     * Получить все возможные значения из перечисления.
     * @return string[]|integer[]
     * @throws \Exception
     */
    public static function getValueList()
    {
        static::init();
        $className = get_called_class();
        return array_keys(self::$valueMap[$className]);
    }

    /**
     * Получить по значения $value связанный с ним объект перечисления.
     * @param bool|float|int|string $value
     * @return static
     * @throws Exception
     */
    public static function getEnumObject($value)
    {
        if (empty($value)) {
            return null;
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Значение должно быть скалярным типом');
        }
        static::init();

        $className = get_called_class();
        $key = self::valueToKey($value);
        if (!isset(self::$valueMap[$className][$key])) {
            throw  new OutOfBoundsException("В перечислении {$className} нет значения {$value}");
        }
        return self::$valueMap[$className][$key];
    }

    /**
     * Получить перечисление в виде массива объектов.
     * @return Enum[]
     * @throws Exception
     */
    public static function getAll()
    {
        static::init();
        $className = get_called_class();
        return self::$valueMap[$className];
    }

    /**
     * Инициализация перечисления списком значений заданных статическими переменными класса.
     * @return void
     * @throws \Exception
     */
    public static function init()
    {
        $className = get_called_class();
        $class = new \ReflectionClass($className);

        if (array_key_exists($className, self::$valueMap)) {
            return;
        }
        self::$valueMap[$className] = [];

        /** @var Enum[] $enumValueList */
        $enumValueList = array_filter($class->getStaticProperties(), function ($property) {
            return $property instanceof Enum;
        });
        if (count($enumValueList) == 0) {
            throw new Exception('Не заданы значения для перечисления ' . $className);
        }

        foreach ($enumValueList as $enum) {
            if (array_key_exists($enum->getValue(), self::$valueMap[$className])) {
                throw new Exception(sprintf('В перечислении %s повторяющиеся значение %s', $className, $enum->getValue()));
            }

            $key = self::valueToKey($enum->getValue());
            self::$valueMap[$className][$key] = $enum;
        }
    }

    /**
     * Преобразовывает значение перечисления в пригодный ключ массива.
     * @param bool|float|int|string $value
     * @return string|integer
     * @throws InvalidArgumentException
     */
    private static function valueToKey($value)
    {
        switch (true) {
            case is_bool($value):
                return $value ? 'true' : 'false';
            case is_float($value):
                return (string)$value;
            case is_int($value):
            case is_string($value):
                return $value;
            default:
                // Сюда ни когда не должны попасть, т.к. проверка типа в конструкторе
                throw new InvalidArgumentException('Неподдерживаемый тип ' . gettype($value));
        }
    }

    /**
     * Добавить новое значение в перечисление. Может использоваться для создания перечисление без привязки к статическим
     * свойствам класса.
     * @param Enum $enum
     */
    public static function push(Enum $enum)
    {
        $className = get_called_class();
        $key = self::valueToKey($enum->getValue());
        self::$valueMap[$className][$key] = $enum;
    }
}
