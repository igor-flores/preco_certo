<?php

namespace Controller\Api;

use Model\Exception\ValidacaoException;
use Model\Usuario\Usuario;
use Utils\Password;
use Utils\Sessao;

class CadastroController extends ApiController
{
    protected bool $publico = true;

    protected function post(): Object
    {
        $Corpo = $this->corpo();

        $nomeNegocio = $Corpo->texto("nomeNegocio", "nome do negócio", true, 120);
        $nomeResponsavel = $Corpo->texto("nomeResponsavel", "nome do responsável", true, 120);
        $email = $Corpo->email("email", "e-mail");
        $senha = $Corpo->senha("senha", "senha");

        $Password = new Password();
        if (strlen($senha) < 8 || !$Password->setPassword($senha)->isStronger()) {
            throw new ValidacaoException(
                "A senha deve ter pelo menos 8 caracteres e combinar letras maiúsculas, minúsculas, números e símbolos.",
                "senha"
            );
        }

        $Usuario = new Usuario();
        $Usuario->setNomeNegocio($nomeNegocio)
            ->setNomeResponsavel($nomeResponsavel)
            ->setEmail($email)
            ->setSenha($senha)
            ->insert();

        Sessao::entrar($Usuario->getId());

        return $this->criado($Usuario->get());
    }
}
