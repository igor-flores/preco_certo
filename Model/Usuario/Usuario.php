<?php

namespace Model\Usuario;

use Model\Exception\AppException;
use Model\Exception\ValidacaoException;
use Model\Model;

class Usuario extends Model
{
    private const ERRO_CHAVE_DUPLICADA = 1062;

    protected ?int $id = null;
    protected ?string $nomeNegocio = null;
    protected ?string $nomeResponsavel = null;
    protected ?string $email = null;
    protected ?string $senhaHash = null;
    protected ?string $dataCadastro = null;

    public function get(): Object
    {
        if (empty($this->id)) {
            throw new AppException("Necessário informar o id");
        }

        $sql = "SELECT
                    u.id,
                    u.nome_negocio AS nomeNegocio,
                    u.nome_responsavel AS nomeResponsavel,
                    u.email,
                    u.data_cadastro AS dataCadastro
                FROM
                    usuario u
                WHERE
                    u.id = :id
        ";
        $this->prepare($sql);
        $this->execute([
            ":id" => $this->id
        ]);

        if ($this->rowCount() === 0) {
            return (Object) [];
        }

        $response = $this->fetch();

        return $this->convertData($response);
    }

    public function insert(): bool
    {
        if (empty($this->nomeNegocio)) {
            throw new AppException("Nome do negócio obrigatório");
        }

        if (empty($this->nomeResponsavel)) {
            throw new AppException("Nome do responsável obrigatório");
        }

        if (empty($this->email)) {
            throw new AppException("E-mail obrigatório");
        }

        if (empty($this->senhaHash)) {
            throw new AppException("Senha obrigatória");
        }

        if ($this->getIdPorEmail($this->email) !== null) {
            throw self::emailEmUso();
        }

        $sql = "INSERT INTO usuario (
                    nome_negocio,
                    nome_responsavel,
                    email,
                    senha_hash,
                    data_cadastro
                ) VALUES (
                    :nomeNegocio,
                    :nomeResponsavel,
                    :email,
                    :senhaHash,
                    NOW()
                )
        ";
        $this->prepare($sql);
        $this->execute([
            ":nomeNegocio" => $this->nomeNegocio,
            ":nomeResponsavel" => $this->nomeResponsavel,
            ":email" => $this->email,
            ":senhaHash" => $this->senhaHash
        ]);

        if ($this->hasError()) {
            if (($this->getError()[1] ?? null) === self::ERRO_CHAVE_DUPLICADA) {
                throw self::emailEmUso();
            }

            throw new AppException("Erro ao cadastrar usuário.", 500);
        }

        $this->setId($this->getLastInsertId());

        return true;
    }

    public function autenticar(string $email, string $senha): bool
    {
        $sql = "SELECT
                    u.id,
                    u.senha_hash AS senhaHash
                FROM
                    usuario u
                WHERE
                    u.email = :email
        ";
        $this->prepare($sql);
        $this->execute([
            ":email" => $email
        ]);

        if ($this->rowCount() === 0) {
            password_hash($senha, PASSWORD_DEFAULT);
            return false;
        }

        $usuario = $this->fetch();

        if (!password_verify($senha, $usuario->senhaHash)) {
            return false;
        }

        $this->setId($usuario->id);

        return true;
    }

    private function getIdPorEmail(string $email): ?int
    {
        $sql = "SELECT id FROM usuario WHERE email = :email";
        $this->prepare($sql);
        $this->execute([
            ":email" => $email
        ]);

        if ($this->rowCount() === 0) {
            return null;
        }

        return (int) $this->fetch()->id;
    }

    private static function emailEmUso(): ValidacaoException
    {
        return new ValidacaoException("Este e-mail já está cadastrado. Use outro e-mail ou entre no sistema.", "email", 409);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getNomeNegocio(): ?string
    {
        return $this->nomeNegocio;
    }

    public function setNomeNegocio(string $nomeNegocio): self
    {
        $this->nomeNegocio = $nomeNegocio;

        return $this;
    }

    public function getNomeResponsavel(): ?string
    {
        return $this->nomeResponsavel;
    }

    public function setNomeResponsavel(string $nomeResponsavel): self
    {
        $this->nomeResponsavel = $nomeResponsavel;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower($email);

        return $this;
    }

    public function setSenha(string $senha): self
    {
        $this->senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        return $this;
    }

    public function getDataCadastro(): ?string
    {
        return $this->dataCadastro;
    }
}
