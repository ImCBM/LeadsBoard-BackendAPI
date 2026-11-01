<?php

namespace App\Exceptions;

use Exception;

class TierUnauthorizedException extends Exception
{
    public function __construct(string $message = 'You are not authorized to assign this tier to your API key.')
    {
        parent::__construct($message, 403);
    }
}
