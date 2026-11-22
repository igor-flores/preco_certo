<?php

namespace Controller\Loja;

use Controller\Controller;
use Render\Render;

class LojaController extends Controller
{
    public function index(): Render
    {
        return $this->pagina("loja", "Cardápio", "_publico");
    }
}
