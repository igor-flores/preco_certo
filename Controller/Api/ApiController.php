<?php

namespace Controller\Api;

use Model\Exception\AppException;
use Utils\Entrada;
use Utils\Sessao;

class ApiController
{
    private const METODOS_SUPORTADOS = ["get", "post", "put", "delete"];

    protected bool $publico = false;
    private ?int $idUsuario = null;

    public function __construct()
    {
        if ($this->publico) {
            return;
        }

        $idUsuario = Sessao::idUsuario();

        if ($idUsuario === null) {
            throw new AppException("Sessão expirada ou inexistente. Entre no sistema novamente.", 401);
        }

        $this->idUsuario = $idUsuario;
    }

    public function index(): Array|Object|string|null
    {
        $requestMethod = strtolower($_SERVER["REQUEST_METHOD"] ?? "get");

        if (!in_array($requestMethod, self::METODOS_SUPORTADOS, true) || !method_exists($this, $requestMethod)) {
            $metodo = strtoupper($requestMethod);
            throw new AppException("O método {$metodo} não é suportado neste endpoint.", 405);
        }

        return $this->$requestMethod();
    }

    protected function idUsuario(): int
    {
        if ($this->idUsuario === null) {
            throw new AppException("Entre no sistema para continuar.", 401);
        }

        return $this->idUsuario;
    }

    protected function corpo(): Entrada
    {
        return Entrada::doCorpo();
    }

    protected function query(): Entrada
    {
        return Entrada::daQuery();
    }

    protected function idDaQuery(): int
    {
        return $this->query()->inteiro("id", "id");
    }

    protected function criado(Array|Object $dados): Array|Object
    {
        http_response_code(201);

        return $dados;
    }
}
