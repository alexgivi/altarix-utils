<?php

namespace altarix\utils;

use yii\base\DynamicModel;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\db\Query;
use yii\helpers\Inflector;

class Helper
{
    /**
     * Возвращает true, если переменная пуста, но не ровна '0' или '0.0'
     * @param $var
     * @return bool
     */
    public static function isBlank(&$var)
    {
        return empty($var) && $var !== '0' && $var !== '0.0';
    }

    public static function combineUrl($parsed_url)
    {
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    public static function getApiUrl($request, $token = null)
    {
        $urlData = parse_url($request);

        if ($token) {
            if (isset($urlData['query'])) {
                parse_str($urlData['query'], $get);
                $get['token'] = $token;
                $urlData['query'] = http_build_query($get);
            } else {
                $urlData['query'] = "token={$token}";
            }
        }
        $urlData['path'] = '/' . trim($urlData['path'], '/');
        $urlData['host'] = trim(getenv('SERVER_URL'), '/');

        return self::combineUrl($urlData);
    }

    public static function jsonPrettyPrint($data)
    {
        $short = false;
        $workData = $short ? [$data[0], $data[1], $data[2]] : $data;
        array_walk_recursive($workData, function (&$item) {
            if (is_string($item)) {
                if (strlen($item) > 300) {
                    $item = substr($item, 0, 300) . '...';
                }
                $item = mb_encode_numericentity($item, [0x80, 0xffff, 0, 0xffff], 'UTF-8');
            }
        });

        $result = mb_decode_numericentity(json_encode($workData, JSON_PRETTY_PRINT), [
            0x80,
            0xffff,
            0,
            0xffff,
        ], 'UTF-8');

        if ($short) {
            $result = substr_replace($result, PHP_EOL . '...', strlen($result) - 2, 0);
        }

        return $result;
    }

    public static function createDir($folder)
    {
        $folder = \Yii::getAlias($folder);
        if (!file_exists(dirname($folder))) {
            self::createDir(dirname($folder));
        }

        if (file_exists($folder)) {
            return;
        }

        mkdir($folder);
        chmod($folder, 0777);
        exec('chmod g+s ' . $folder);
    }

    /**
     * Возвращает true, если объект является массивом из объектов указанного класса
     * @param mixed  $object
     * @param string $class
     * @return bool
     */
    public static function arrayOfInstances($object, string $class): bool
    {
        if (!is_array($object)) {
            return false;
        }

        foreach ($object as $item) {
            if (!$item instanceof $class) {
                return false;
            }
        }

        return true;
    }

    /**
     * Проверяет явяляется ли переменная простой или массивом из простых элементов
     * @param $var
     * @return bool
     */
    public static function isScalar($var): bool
    {
        if (is_integer($var) || is_bool($var) || is_string($var) || empty($var) || is_double($var)) {
            return true;
        }

        if (is_array($var)) {
            foreach ($var as $item) {
                if (!is_integer($item) && !is_bool($item) && !is_string($item) && !empty($item) || is_double($var)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    public static function getShortClassName($object)
    {
        $parts = explode('\\', get_class($object));

        return array_pop($parts);
    }

    /**
     * На основании исходного кода $phpCode вернет абсолютное имя класса если он в коде присутствует. В противном
     * случае null.
     *
     * @param string $phpCode Исходный код класса
     *
     * @return null|string
     */
    public static function getFQCN($phpCode)
    {
        $tokenList = token_get_all($phpCode);
        $namespacePartList = [];
        $isNamespaceBlock = false;
        $isClassBlock = false;

        foreach ($tokenList as $token) {
            if (is_array($token)) {
                $lexeme = $token[1];
                $tokenId = $token[0];
            } else {
                $lexeme = $token;
                $tokenId = null;
            }
            if ($tokenId == T_NAMESPACE) {
                $isNamespaceBlock = true;
            }
            if ($tokenId == T_CLASS) {
                $isClassBlock = true;
            }
            if ($lexeme == ';') {
                $isNamespaceBlock = false;
            }
            if ($isNamespaceBlock && $tokenId == T_STRING) {
                $namespacePartList[] = $lexeme;
            }
            if ($isClassBlock && $tokenId == T_STRING) {
                $namespacePartList [] = $lexeme;
                return '\\' . implode('\\', $namespacePartList);
            }
        }

        return null;
    }

    /**
     * Проверяет, содержит ли объект $object в своем дереве предков указанные классы $className либо прямо реализует
     * его.
     * @param string|object $object      Имя класса или экземпляр объекта. В случае отсутствия такого класса никакой
     *                                   ошибки сгенерировано не будет.
     * @param string|array  $className   Имя класса предка, может быть массивом
     * @param bool          $allowString Флаг - $object может быть строкой
     *
     * @return bool
     *
     * @throws \Exception
     */
    public static function isSubclassOf($object, $className, bool $allowString = true): bool
    {
        if (!is_array($className)) {
            $className = [$className];
        }

        foreach ($className as $parent) {
            if (!is_string($parent)) {
                throw new \Exception('Имя класса должно быть строкой, а передан ' . gettype($parent));
            }
            if (is_subclass_of($object, $parent, $allowString)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Аналог функции https://lodash.com/docs/4.17.4#ceil
     * @param float $number
     * @param int   $precision
     *
     * @return float
     */
    public static function ceil($number, $precision = 0)
    {
        $pow = pow(10, $precision);

        return (ceil($pow * $number) + ceil($pow * $number - ceil($pow * $number))) / $pow;
    }

    /**
     * @param string $cmd
     * @param bool   $useYii
     * @param bool   $printCommand
     *
     * @throws \Exception
     */
    public static function exec($cmd, $useYii = true, $printCommand = false)
    {
        $yiiPath = realpath(__DIR__ . '/../yii');
        $prefix = ($useYii) ? $yiiPath . ' ' : '';
        $cmd = $prefix . $cmd;
        if ($printCommand) {
            print '|$|' . $cmd . PHP_EOL;
        }
        system($cmd, $return);

        if ($return !== 0) {
            throw new \Exception("Команда {$cmd} завершилась с ошибкой");
        }
    }

    /**
     * строка для записи текущего времени в базу
     */
    public static function nowDateTime(): string
    {
        return (new \DateTime())->format("Y-m-d H:i:s");
    }

    /**
     * Для числа $number вернет корректную форму слова в зависимости от величины этого числа.
     * По заданным вариантам слов в:
     *   $one - для единичного $number (в русском языке 1);
     *   $few - для нескольких $number (в русском языке 2, 3, 4);
     *   $many - для множественного $number (в русском языке для 5, 6, 7...);
     *   $other - значение по умолчанию в случае если значимость величины определить не удалось.
     *
     * Напримеры:
     *   plural(1, 'объект', 'объекта', 'объектов') "1 объект"
     *   plural(3, 'объект', 'объекта', 'объектов') "3 объекта"
     *   plural(5, 'объект', 'объекта', 'объектов') "5 объектов"
     *
     * @link http://intl.rmcreative.ru/site/message-formatting?locale=ru_RU
     *
     * @param $number number Число для которого нужно вернуть корретную форму зависимого слова.
     * @param $one    string Вариант слова для одиничного значения числа
     * @param $few    string Варинат слова для значения числа в несколько величин
     * @param $many   string Вариант слова для множественного значения величины числа
     * @param $other  string Варинат слова для нераспознаного значения величины числа
     *
     * @return string
     */
    public static function plural($number, $one, $few, $many, $other = ''): string
    {
        $formatter = new \MessageFormatter('ru-RU',
            '{number,plural,one{' . $one . '} few{' . $few . '} many{' . $many . '} other{' . $other . '}}');
        return $formatter->format(['number' => $number]);
    }

    /**
     * Валидирует одно поле по заданным правилам
     * @param mixed        $data      данные для валидации
     * @param array|string $rules     массив с правилами валидации или название класса
     * @param mixed        $errors    переменная, в которую будет записан массив с сообщениями об ошибках
     * @param string       $fieldName если его передать, все сообщения об ошибках
     *
     * @return bool
     *
     * @throws ApiException
     */
    public static function validateValue($data, $rules, &$errors = null, $fieldName = 'data')
    {
        if (is_string($rules)) {
            $rules = [[$rules]];
        }

        if (isset($rules[0]) && is_string($rules[0])) {
            $rules = [$rules];
        }

        array_walk($rules, function (&$v) use ($fieldName) {
            array_unshift($v, $fieldName);
        });

        try {
            $model = DynamicModel::validateData([$fieldName => $data], $rules);
        } catch (InvalidConfigException $e) {
            throw new ApiException("Wrong data validation rules: " . $e->getMessage());
        }

        $model->validate($fieldName);
        if ($errors !== null) {
            if ($model->hasErrors()) {
                $errors = $model->errors;
            }
        }

        return !$model->hasErrors();
    }

    /**
     * перечень чисел через запятую
     */
    public static function numbers(int $from, int $lenth): string
    {
        $numbers = [];
        for ($i = $from; $i < ($from + $lenth); $i++) {
            $numbers[] = $i;
        }

        return implode(',', $numbers);
    }

    public static function countFileRows($filename): int
    {
        return (integer)shell_exec("sed -n '$=' " . escapeshellarg($filename));
    }

    /**
     * @param Query           $query
     * @param string          $filename Путь до файла в который будет выгружен CSV.
     * @param bool            $checkRowsCount
     * @param Connection|null $db       содениение с базой. если null - берется Yii::$app->db
     *
     * @throws \Exception
     */
    public static function sqlToCsv(Query $query, $filename, $checkRowsCount = false, $db = null)
    {
        touch($filename);
        chmod($filename, 0777);

        $sql = $query->createCommand()->getRawSql();

        if (empty($db)) {
            $db = \Yii::$app->db;
        }

        $db->createCommand("COPY ($sql) TO '{$filename}' WITH CSV HEADER DELIMITER ';';")->execute();

        if ($checkRowsCount) {
            if ($query->count() !== (self::countFileRows($filename) - 1)) {
                unlink($filename);
                throw new \Exception('Количество строк в выгрузке не совпадает с результатом проверочного запроса');
            }
        }
    }

    public static function zipFile($filename, $humanReadableName = '', $newZipName = '')
    {
        $zipName = !empty($newZipName) ? $newZipName : $filename . '.zip';
        if (!empty($humanReadableName)) {
            $temporaryPath = dirname($filename) . '/' . uniqid() . '/';
            self::createDir($temporaryPath);
            $newFilename = $temporaryPath . $humanReadableName;
            exec('mv ' . escapeshellarg($filename) . ' ' . escapeshellarg($newFilename));
            $filename = $newFilename;
        }

        exec('zip -j ' . escapeshellarg($zipName) . ' ' . escapeshellarg($filename));
        if (isset($temporaryPath)) {
            exec('rm -rf ' . escapeshellarg($temporaryPath));
        }

        return $zipName;
    }

    /**
     * Сжимает несколько файлов
     * @param       $zipPathName
     * @param array $filesToZip
     * @param bool  $delete
     * @throws \Exception
     */
    public static function zipFiles($zipPathName, array $filesToZip, $delete = false)
    {
        foreach ($filesToZip as $file) {
            if (!file_exists($file)) {
                throw new \Exception('Отстствует один или несколько файлов');
            }
        }

        $flags = $delete ? '-jm' : '-j';
        $filesNames = array_reduce($filesToZip, function ($carry, $item) {
            $carry .= " " . escapeshellarg($item);
            return $carry;
        });

        exec('zip ' . $flags . ' ' . escapeshellarg($zipPathName) . ' ' . $filesNames);
    }

    /**
     * @param string $json Строка в JSON формате.
     *
     * @return mixed|null
     *
     * @throws \InvalidArgumentException
     */
    public static function jsonDecode($json)
    {
        if ($json === 'null' || $json === 'NULL') {
            return null;
        }

        $result = json_decode($json, true);
        if ($result === null) {
            $errorCode = json_last_error();
            switch ($errorCode) {
                case JSON_ERROR_DEPTH:
                    $mess = 'Достигнута максимальная глубина стека';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $mess = 'Неверный или некорректный JSON';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $mess = 'Ошибка управляющего символа, возможно неверная кодировка';
                    break;
                case JSON_ERROR_SYNTAX:
                    $mess = 'Синтаксическая ошибка';
                    break;
                case JSON_ERROR_UTF8:
                    $mess = 'Некорректные символы UTF-8, возможно неверная кодировка';
                    break;
                case JSON_ERROR_RECURSION:
                    $mess = 'Одна или несколько зацикленных ссылок в кодируемом значении';
                    break;
                case JSON_ERROR_INF_OR_NAN:
                    $mess = 'Одно или несколько значений NAN или INF в кодируемом значении';
                    break;
                case JSON_ERROR_UNSUPPORTED_TYPE:
                    $mess = 'Передано значение с неподдерживаемым типом';
                    break;
                case JSON_ERROR_INVALID_PROPERTY_NAME:
                    $mess = 'Имя свойства не может быть закодировано';
                    break;
                case JSON_ERROR_UTF16:
                    $mess = 'Некорректный символ UTF-16, возможно некорректно закодирован';
                    break;
                default:
                    $mess = 'Неизвестная ошибка';
                    break;
            }
            throw new \InvalidArgumentException($mess, $errorCode);
        }

        return $result;
    }

    /**
     * Возвращает набор алисов для колонок моделей в формате
     * [
     *   // ...
     *   'camelCase' => 'snake_case',
     *   //...
     * ]
     * @param string[] $fields
     * @return string[]
     */
    public static function snakeCaseAliases($fields)
    {
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = Inflector::underscore($field);
        }

        return $result;
    }

    /**
     * Преобразуем размер в байтах к подходящему порядку более высокой размерности (килобайты, мегабайты и прочее).
     * Порядок выдается согласно ГОСТ 8.417—2002.
     *
     * @param int $size      Размер в байтах
     * @param int $precision Количество знаков после запятой
     *
     * @return string
     */
    public static function prettySize(int $size, int $precision = 3)
    {
        $base = log($size) / log(1024);
        $suffixList = ['Б', 'кбайт', 'Мбайт', 'Гбайт', 'Тбайт', 'Пбайт', 'Эбайт', 'Збайт', 'Ибайт'];
        $newSize = pow(1024, $base - floor($base));
        if (is_nan($newSize)) {
            $newSize = $size;
        }

        return round($newSize, $precision) . ' ' . $suffixList[(string)floor($base)];
    }

    public static function findInArray($array, $columns)
    {
        foreach ($array as $key => $value) {
            $return = true;
            foreach ($columns as $columnName => $columnValue) {
                if ($value[$columnName] !== $columnValue) {
                    $return = false;
                }
            }
            if ($return) {
                return $value;
            }
        }

        return null;
    }

    public static function randomUuid()
    {
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
