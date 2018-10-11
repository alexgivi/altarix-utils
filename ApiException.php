<?php

namespace altarix\utils;

use yii\web\HttpException;

class ApiException extends HttpException
{
    public function __construct($message = null)
    {
        $message = is_string($message) ? $message : json_encode($message, JSON_UNESCAPED_UNICODE);

        parent::__construct(400, $message);
    }
}
