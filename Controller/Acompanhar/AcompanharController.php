<?php

namespace Controller\Acompanhar;

use Controller\Controller;
use Render\Render;

class AcompanharController extends Controller
{
    public function index(): Render
    {
        return $this->pagina("acompanhar", "Acompanhar pedido", "_publico");
    }
}
