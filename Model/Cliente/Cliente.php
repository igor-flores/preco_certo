<?php

namespace Model\Cliente;

use Model\Exception\AppException;
use Model\Model;
use Utils\FiltrosPadrao;
use Utils\Utils;

class Cliente extends Model
{
    protected ?int $id = null;
    protected ?int $idUsuario = null;
    protected ?string $nome = null;
    protected ?string $telefone = null;
    protected ?string $endereco = null;

    public function __construct(?int $id = null)
    {
        parent::__construct();

        if (!empty($id)) {
            $this->setId($id);
        }
    }

    public function get(): Object
    {
        if (empty($this->id)) {
            throw new AppException("Necessário informar o id");
        }
        $this->checkIdUsuario();

        $sql = "SELECT
                    c.id,
                    c.nome,
                    c.telefone,
                    c.endereco
                FROM
                    cliente c
                WHERE
                    c.id = :id AND
                    c.usuario_id = :idUsuario
        ";
        $this->prepare($sql);
        $this->execute([
            ":id" => $this->id,
            ":idUsuario" => $this->idUsuario
        ]);

        if ($this->rowCount() === 0) {
            return (Object) [];
        }

        $response = $this->fetch();

        return $this->convertData($response);
    }

    public function getAll(?FiltrosPadrao $FiltrosPadrao = null): Object
    {
        $this->checkIdUsuario();

        $where = ["c.usuario_id = :idUsuario"];
        $bind = [":idUsuario" => $this->idUsuario];

        if (isset($FiltrosPadrao?->getEspecificos()->busca)) {
            $busca = Utils::escapeLike($FiltrosPadrao->getEspecificos()->busca);
            $where[] = "(c.nome LIKE :busca OR c.telefone LIKE :busca)";
            $bind[":busca"] = "%{$busca}%";
        }

        $where = implode(" AND". PHP_EOL, $where);

        $sql = "SELECT
                    c.id,
                    c.nome,
                    c.telefone,
                    c.endereco,
                    COUNT(p.id) AS pedidos
                FROM
                    cliente c
                    LEFT JOIN pedido p ON p.cliente_id = c.id
                WHERE
                    {$where}
                GROUP BY
                    c.id
                ORDER BY
                    c.nome
        ";

        $response = $this->fixGetAll($sql, $FiltrosPadrao, $bind);
        foreach ($response->data as $cliente) {
            $cliente->pedidos = (int) $cliente->pedidos;
        }

        return $response;
    }

    public function insert(): bool
    {
        $this->checkIdUsuario();
        $this->checkCampos();

        $sql = "INSERT INTO cliente (
                    usuario_id,
                    nome,
                    telefone,
                    endereco
                ) VALUES (
                    :idUsuario,
                    :nome,
                    :telefone,
                    :endereco
                )
        ";
        $this->prepare($sql);
        $this->execute([
            ":idUsuario" => $this->idUsuario,
            ":nome" => $this->nome,
            ":telefone" => $this->telefone,
            ":endereco" => $this->endereco
        ]);

        if ($this->hasError()) {
            throw new AppException("Erro ao cadastrar cliente.", 500);
        }

        $this->setId($this->getLastInsertId());

        return true;
    }

    public function update(): bool
    {
        if (empty($this->id)) {
            throw new AppException("O código do cliente é obrigatório");
        }
        $this->checkIdUsuario();
        $this->checkCampos();

        $sql = "UPDATE
                    cliente
                SET
                    nome = :nome,
                    endereco = COALESCE(:endereco, endereco)
                WHERE
                    id = :id AND
                    usuario_id = :idUsuario
        ";
        $this->prepare($sql);
        $this->execute($this->getAttributes());

        if ($this->hasError()) {
            throw new AppException("Erro ao editar cliente.", 500);
        }

        return true;
    }

    public function saveByTelefone(): bool
    {
        $this->checkIdUsuario();
        $this->checkCampos();

        $idExistente = $this->getIdByTelefone();
        if ($idExistente === null) {
            return $this->insert();
        }

        $this->setId($idExistente);

        return $this->update();
    }

    private function getIdByTelefone(): ?int
    {
        $sql = "SELECT
                    id
                FROM
                    cliente
                WHERE
                    usuario_id = :idUsuario AND
                    telefone = :telefone
                ORDER BY
                    id
                LIMIT 1
        ";
        $this->prepare($sql);
        $this->execute([
            ":idUsuario" => $this->idUsuario,
            ":telefone" => $this->telefone
        ]);

        if ($this->rowCount() === 0) {
            return null;
        }

        return (int) $this->fetch()->id;
    }

    private function checkCampos(): void
    {
        if (empty($this->nome)) {
            throw new AppException("Nome obrigatório");
        }

        if (empty($this->telefone)) {
            throw new AppException("Telefone obrigatório");
        }
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

    public function getIdUsuario(): ?int
    {
        return $this->idUsuario;
    }

    public function setIdUsuario(int $idUsuario): self
    {
        $this->idUsuario = $idUsuario;

        return $this;
    }

    public function getNome(): ?string
    {
        return $this->nome;
    }

    public function setNome(string $nome): self
    {
        $this->nome = $nome;

        return $this;
    }

    public function getTelefone(): ?string
    {
        return $this->telefone;
    }

    public function setTelefone(string $telefone): self
    {
        $this->telefone = $telefone;

        return $this;
    }

    public function getEndereco(): ?string
    {
        return $this->endereco;
    }

    public function setEndereco(?string $endereco): self
    {
        $this->endereco = $endereco;

        return $this;
    }
}
