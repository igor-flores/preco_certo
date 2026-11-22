<?php

namespace Model\Exception;

class ValidacaoException extends AppException
{
    public function __construct(string $message, ?string $campo = null, int $code = 422)
    {
        parent::__construct($message, $code, $campo);
    }
}
