<?php

namespace Controller\Api;

use Model\Exception\AppException;
use Model\MateriaPrima\MateriaPrima;
use Model\MateriaPrima\UnidadeMedida;
use Utils\Entrada;
use Utils\FiltrosPadrao;

class MateriaPrimaController extends ApiController
{
    protected function get(): Array|Object
    {
        $Query = $this->query();
        $MateriaPrima = (new MateriaPrima())->setIdUsuario($this->idUsuario());

        if ($Query->tem("id")) {
            $materiaPrima = $MateriaPrima->setId($this->idDaQuery())->get();

            if (!isset($materiaPrima->id)) {
                throw new AppException("Matéria-prima não encontrada.", 404);
            }

            return $materiaPrima;
        }

        $FiltrosPadrao = (new FiltrosPadrao())
            ->setEspecifico("nome", $Query->texto("busca", "busca", false, 120));

        return $MateriaPrima->getAll($FiltrosPadrao)->data;
    }

    protected function post(): Object
    {
        $MateriaPrima = $this->preencher(new MateriaPrima(), $this->corpo());
        $MateriaPrima->insert();

        return $this->criado($MateriaPrima->get());
    }

    protected function put(): Object
    {
        $MateriaPrima = $this->preencher(new MateriaPrima($this->idDaQuery()), $this->corpo());
        $MateriaPrima->update();

        return $MateriaPrima->get();
    }

    protected function delete(): Array
    {
        (new MateriaPrima($this->idDaQuery()))->setIdUsuario($this->idUsuario())->delete();

        return ["message" => "Matéria-prima excluída."];
    }

    private function preencher(MateriaPrima $MateriaPrima, Entrada $Corpo): MateriaPrima
    {
        return $MateriaPrima->setIdUsuario($this->idUsuario())
            ->setNome($Corpo->texto("nome", "nome", true, 120))
            ->setUnidadeMedida($Corpo->enum("unidadeMedida", "unidade de medida", UnidadeMedida::class))
            ->setQuantidadeEmbalagem($Corpo->decimal("quantidadeEmbalagem", "quantidade da embalagem", true, false, 9999999.999))
            ->setPrecoEmbalagem($Corpo->decimal("precoEmbalagem", "preço da embalagem", true, true));
    }
}
