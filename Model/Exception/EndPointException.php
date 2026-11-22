<?php

namespace Model\Exception;

class EndPointException extends AppException
{
    public function __construct(string $message = "Endpoint não encontrado.")
    {
        parent::__construct($message, 404);
    }
}
