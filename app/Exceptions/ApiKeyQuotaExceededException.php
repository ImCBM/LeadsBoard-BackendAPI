<?php

namespace App\Exceptions;

use Exception;

class ApiKeyQuotaExceededException extends Exception
{
    public function __construct(string $message = 'Maximum API key limit reached for your subscription tier.')
    {
        parent::__construct($message, 403);
    }
}
