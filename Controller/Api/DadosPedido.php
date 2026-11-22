<?php

namespace Controller\Api;

use Model\Cliente\Cliente;
use Model\Pedido\Pedido;
use Model\Pedido\TipoEntrega;
use Utils\Entrada;

trait DadosPedido
{
    private function preencherPedido(Pedido $Pedido, Entrada $Corpo): Pedido
    {
        $tipoEntrega = $Corpo->enum("tipoEntrega", "tipo de entrega", TipoEntrega::class);

        $Cliente = (new Cliente())
            ->setNome($Corpo->texto("nome", "nome", true, 120))
            ->setTelefone($Corpo->telefone("telefone", "telefone"))
            ->setEndereco($Corpo->texto("endereco", "endereço", $tipoEntrega === TipoEntrega::Entrega, 255));

        return $Pedido->setCliente($Cliente)
            ->setTipoEntrega($tipoEntrega)
            ->setFormaPagamento($Corpo->texto("formaPagamento", "forma de pagamento", true, 50))
            ->setItens($this->lerItensPedido($Corpo));
    }

    private function lerItensPedido(Entrada $Corpo): Array
    {
        $itens = [];

        foreach ($Corpo->itens("itens", "itens do pedido") as $indice => $Item) {
            $numero = $indice + 1;
            $idProduto = $Item->inteiro("idProduto", "produto do item {$numero}");
            $quantidade = $Item->inteiro("quantidade", "quantidade do item {$numero}", true, 1, 999);

            $itens[$idProduto] ??= ["idProduto" => $idProduto, "quantidade" => 0];
            $itens[$idProduto]["quantidade"] += $quantidade;
        }

        return array_values($itens);
    }
}
