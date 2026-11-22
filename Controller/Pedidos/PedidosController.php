<?php

namespace Controller\Pedidos;

use Controller\Controller;
use Render\Render;

class PedidosController extends Controller
{
    public function index(): Render
    {
        return $this->pagina("pedidos", "Pedidos", "_painel");
    }
}
