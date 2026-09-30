<?php

namespace App\Exceptions;

use RuntimeException;

class AgiException extends RuntimeException
{
    public function __construct(public readonly string $responseCode, string $message, int $httpStatus)
    {
        parent::__construct($message, $httpStatus);
    }
}
