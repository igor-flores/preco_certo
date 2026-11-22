<?php

namespace Model\Pedido;

enum OrigemPedido: string
{
    case Cardapio = "cardapio";
    case Manual = "manual";
}
