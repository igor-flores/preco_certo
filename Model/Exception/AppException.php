<?php

namespace Model\Exception;

use Exception;

class AppException extends Exception
{
    public function __construct(
        string $message = "Requisição inválida.",
        int $code = 400,
        private ?string $campo = null
    ) {
        parent::__construct($message, $code);
    }

    public function getCampo(): ?string
    {
        return $this->campo;
    }

    public function toArray(): array
    {
        $resposta = ["error" => $this->getMessage()];

        if ($this->campo !== null) {
            $resposta["campo"] = $this->campo;
        }

        return $resposta;
    }
}
