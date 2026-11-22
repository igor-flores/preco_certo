<?php

namespace Model\Pedido;

enum TipoEntrega: string
{
    case Retirada = "retirada";
    case Entrega = "entrega";
}
