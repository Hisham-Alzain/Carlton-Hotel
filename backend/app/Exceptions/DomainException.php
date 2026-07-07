<?php

namespace App\Exceptions;

abstract class DomainException extends \Exception
{
    public function __construct(
        string $message = '',
        private readonly array $ctx = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    abstract public function errorCode(): string;
    abstract public function statusCode(): int;
    public function context(): array { return $this->ctx; }
}
