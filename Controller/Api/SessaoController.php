<?php

namespace Controller\Api;

use Model\Exception\AppException;
use Model\Usuario\Usuario;
use Utils\Sessao;

class SessaoController extends ApiController
{
    protected bool $publico = true;

    protected function post(): Object
    {
        $Corpo = $this->corpo();
        $email = $Corpo->email("email", "e-mail");
        $senha = $Corpo->senha("senha", "senha");

        $Usuario = new Usuario();

        if (!$Usuario->autenticar($email, $senha)) {
            throw new AppException("E-mail ou senha inválidos.", 401);
        }

        Sessao::entrar($Usuario->getId());

        return $Usuario->get();
    }

    protected function get(): Object
    {
        $idUsuario = Sessao::idUsuario();

        if ($idUsuario === null) {
            throw new AppException("Sessão expirada ou inexistente. Entre no sistema novamente.", 401);
        }

        $usuario = (new Usuario())->setId($idUsuario)->get();

        if (!isset($usuario->id)) {
            throw new AppException("Usuário não encontrado.", 404);
        }

        return $usuario;
    }

    protected function delete(): Array
    {
        Sessao::sair();

        return ["message" => "Sessão encerrada."];
    }
}
