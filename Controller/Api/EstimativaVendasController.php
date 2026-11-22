<?php

namespace Controller\Api;

use Model\Relatorio\EstimativaVendas;
use Model\Relatorio\Periodo;

class EstimativaVendasController extends ApiController
{
    protected function get(): Array
    {
        $Query = $this->query();
        $periodo = $Query->enum("periodo", "período", Periodo::class, false) ?? Periodo::Mes;
        $quantidadePeriodos = $Query->inteiro("periodos", "quantidade de períodos", false, 2, 24) ?? 6;

        return (new EstimativaVendas())->setIdUsuario($this->idUsuario())->gerar($periodo, $quantidadePeriodos);
    }
}
