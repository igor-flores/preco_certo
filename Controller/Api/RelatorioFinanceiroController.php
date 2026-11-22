<?php

namespace Controller\Api;

use DateTimeImmutable;
use Model\Relatorio\Periodo;
use Model\Relatorio\RelatorioFinanceiro;

class RelatorioFinanceiroController extends ApiController
{
    protected function get(): Array
    {
        $Query = $this->query();
        $periodo = $Query->enum("periodo", "período", Periodo::class, false) ?? Periodo::Mes;
        $referencia = $Query->data("referencia", "data de referência") ?? new DateTimeImmutable();

        return (new RelatorioFinanceiro())->setIdUsuario($this->idUsuario())->gerar($periodo, $referencia);
    }
}
