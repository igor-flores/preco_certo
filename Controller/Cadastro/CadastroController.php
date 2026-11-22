<?php

namespace Controller\Cadastro;

use Controller\Controller;
use Render\Render;

class CadastroController extends Controller
{
    public function index(): Render
    {
        return $this->pagina("cadastro", "Criar conta", "_acesso");
    }
}
