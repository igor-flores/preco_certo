<?php

namespace Controller\Entrar;

use Controller\Controller;
use Render\Render;

class EntrarController extends Controller
{
    public function index(): Render
    {
        return $this->pagina("entrar", "Entrar", "_acesso");
    }
}
