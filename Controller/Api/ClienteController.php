<?php

namespace Controller\Api;

use Model\Cliente\Cliente;
use Utils\FiltrosPadrao;

class ClienteController extends ApiController
{
    protected function get(): Array
    {
        $FiltrosPadrao = (new FiltrosPadrao())
            ->setEspecifico("busca", $this->query()->texto("busca", "busca", false, 120));

        return (new Cliente())->setIdUsuario($this->idUsuario())->getAll($FiltrosPadrao)->data;
    }
}
