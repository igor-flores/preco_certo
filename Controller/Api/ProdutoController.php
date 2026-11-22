<?php

namespace Controller\Api;

use Model\Exception\AppException;
use Model\Exception\ValidacaoException;
use Model\MateriaPrima\UnidadeMedida;
use Model\Produto\Produto;
use Utils\Entrada;
use Utils\FiltrosPadrao;

class ProdutoController extends ApiController
{
    protected function get(): Array|Object
    {
        $Query = $this->query();
        $Produto = (new Produto())->setIdUsuario($this->idUsuario());

        if ($Query->tem("id")) {
            return $this->buscar($Produto->setId($this->idDaQuery()));
        }

        $FiltrosPadrao = (new FiltrosPadrao())
            ->setEspecifico("ativo", $Query->tem("ativo") ? $Query->booleano("ativo", "ativo", true) : null)
            ->setEspecifico("nome", $Query->texto("busca", "busca", false, 120));

        return $Produto->getAll($FiltrosPadrao)->data;
    }

    protected function post(): Object
    {
        $Produto = $this->preencher(new Produto(), $this->corpo());
        $Produto->insert();

        return $this->criado($this->buscar($Produto));
    }

    protected function put(): Object
    {
        $Corpo = $this->corpo();
        $Produto = $this->preencher(new Produto($this->idDaQuery()), $Corpo);
        $Produto->setAtivo($Corpo->booleano("ativo", "ativo", true))->update();

        return $this->buscar($Produto);
    }

    protected function delete(): Object
    {
        $Produto = (new Produto($this->idDaQuery()))->setIdUsuario($this->idUsuario());
        $Produto->desativar();

        return $this->buscar($Produto);
    }

    private function buscar(Produto $Produto): Object
    {
        $produto = $Produto->get();

        if (!isset($produto->id)) {
            throw new AppException("Produto não encontrado.", 404);
        }

        return $produto;
    }

    private function preencher(Produto $Produto, Entrada $Corpo): Produto
    {
        return $Produto->setIdUsuario($this->idUsuario())
            ->setNome($Corpo->texto("nome", "nome", true, 120))
            ->setDescricao($Corpo->texto("descricao", "descrição", false, 2000))
            ->setRendimento($Corpo->decimal("rendimento", "rendimento", true, false, 9999999.999))
            ->setPrecoVenda($Corpo->decimal("precoVenda", "preço de venda"))
            ->setMateriasPrimas($this->lerMateriasPrimas($Corpo));
    }

    private function lerMateriasPrimas(Entrada $Corpo): Array
    {
        $materiasPrimas = [];

        foreach ($Corpo->itens("materiasPrimas", "matérias-primas") as $indice => $Item) {
            $numero = $indice + 1;
            $idMateriaPrima = $Item->inteiro("idMateriaPrima", "matéria-prima do item {$numero}");

            if (isset($materiasPrimas[$idMateriaPrima])) {
                throw new ValidacaoException(
                    "A mesma matéria-prima foi informada mais de uma vez. Remova o item repetido.",
                    "materiasPrimas[{$indice}].idMateriaPrima"
                );
            }

            $materiasPrimas[$idMateriaPrima] = [
                "idMateriaPrima" => $idMateriaPrima,
                "quantidade" => $Item->decimal("quantidade", "quantidade do item {$numero}", true, false, 9999999.999),
                "unidadeMedida" => $Item->enum("unidadeMedida", "unidade de medida do item {$numero}", UnidadeMedida::class),
            ];
        }

        return array_values($materiasPrimas);
    }
}
