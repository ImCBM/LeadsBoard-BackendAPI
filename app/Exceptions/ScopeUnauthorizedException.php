<?php

namespace App\Exceptions;

use Exception;

class ScopeUnauthorizedException extends Exception
{
    public function __construct(string $message = 'One or more requested scopes are not permitted by your subscription tier.')
    {
        parent::__construct($message, 403);
    }
}
