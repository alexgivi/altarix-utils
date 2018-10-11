<?php

namespace altarix\utils;

use yii\helpers\Json;
use yii\web\HttpException;

class ClientException extends HttpException
{
    public $userMessage;

    public function __construct($message)
    {
        $this->userMessage = $message;

        parent::__construct(422, Json::encode($message), 422);
    }
}
