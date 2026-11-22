<?php

namespace Model\MateriaPrima;

use Model\Exception\AppException;
use Model\Exception\ValidacaoException;
use Model\Model;
use Utils\FiltrosPadrao;
use Utils\Utils;

class MateriaPrima extends Model
{
    protected ?int $id = null;
    protected ?int $idUsuario = null;
    protected ?string $nome = null;
    protected ?UnidadeMedida $unidadeMedida = null;
    protected ?float $quantidadeEmbalagem = null;
    protected ?float $precoEmbalagem = null;

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
                    m.id,
                    m.nome,
                    m.unidade_medida AS unidadeMedida,
                    m.quantidade_embalagem AS quantidadeEmbalagem,
                    m.preco_embalagem AS precoEmbalagem
                FROM
                    materia_prima m
                WHERE
                    m.id = :id AND
                    m.usuario_id = :idUsuario
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

        return self::fixCusto($this->convertData($response));
    }

    public function getAll(?FiltrosPadrao $FiltrosPadrao = null): Object
    {
        $this->checkIdUsuario();

        $where = ["m.usuario_id = :idUsuario"];
        $bind = [":idUsuario" => $this->idUsuario];

        if (isset($FiltrosPadrao?->getEspecificos()->nome)) {
            $nome = Utils::escapeLike($FiltrosPadrao->getEspecificos()->nome);
            $where[] = "m.nome LIKE :nome";
            $bind[":nome"] = "%{$nome}%";
        }

        $where = implode(" AND". PHP_EOL, $where);

        $sql = "SELECT
                    m.id,
                    m.nome,
                    m.unidade_medida AS unidadeMedida,
                    m.quantidade_embalagem AS quantidadeEmbalagem,
                    m.preco_embalagem AS precoEmbalagem
                FROM
                    materia_prima m
                WHERE
                    {$where}
                ORDER BY
                    m.nome
        ";

        $response = $this->fixGetAll($sql, $FiltrosPadrao, $bind);
        $response->data = array_map(fn (Object $materiaPrima) => self::fixCusto($materiaPrima), $response->data);

        return $response;
    }

    public function insert(): bool
    {
        $this->checkIdUsuario();
        $this->checkCampos();

        $sql = "INSERT INTO materia_prima (
                    usuario_id,
                    nome,
                    unidade_medida,
                    quantidade_embalagem,
                    preco_embalagem
                ) VALUES (
                    :idUsuario,
                    :nome,
                    :unidadeMedida,
                    :quantidadeEmbalagem,
                    :precoEmbalagem
                )
        ";
        $this->prepare($sql);
        $this->execute([
            ":idUsuario" => $this->idUsuario,
            ":nome" => $this->nome,
            ":unidadeMedida" => $this->unidadeMedida->value,
            ":quantidadeEmbalagem" => $this->quantidadeEmbalagem,
            ":precoEmbalagem" => $this->precoEmbalagem
        ]);

        if ($this->hasError()) {
            throw new AppException("Erro ao cadastrar matéria-prima.", 500);
        }

        $this->setId($this->getLastInsertId());

        return true;
    }

    public function update(): bool
    {
        if (empty($this->id)) {
            throw new AppException("O código da matéria-prima é obrigatório");
        }
        $this->checkCampos();

        $data = $this->get();
        if (!isset($data->id)) {
            throw new AppException("Matéria-prima não encontrada.", 404);
        }

        $this->checkUnidadeNosProdutos();

        $sql = "UPDATE
                    materia_prima
                SET
                    nome = :nome,
                    unidade_medida = :unidadeMedida,
                    quantidade_embalagem = :quantidadeEmbalagem,
                    preco_embalagem = :precoEmbalagem
                WHERE
                    id = :id AND
                    usuario_id = :idUsuario
        ";
        $this->prepare($sql);
        $this->execute($this->getAttributes());

        if ($this->hasError()) {
            throw new AppException("Erro ao editar matéria-prima.", 500);
        }

        return true;
    }

    public function delete(): bool
    {
        $data = $this->get();

        if (!isset($data->id)) {
            throw new AppException("Matéria-prima não encontrada.", 404);
        }

        $this->checkDelete($data->id);

        $sql = "DELETE FROM materia_prima WHERE id = :id AND usuario_id = :idUsuario";
        $this->prepare($sql);
        $this->execute([
            ":id" => $data->id,
            ":idUsuario" => $this->idUsuario
        ]);

        return !$this->hasError();
    }

    private function checkCampos(): void
    {
        if (empty($this->nome)) {
            throw new AppException("Nome obrigatório");
        }

        if ($this->unidadeMedida === null) {
            throw new AppException("Unidade de medida obrigatória");
        }

        if (empty($this->quantidadeEmbalagem)) {
            throw new AppException("Quantidade da embalagem obrigatória");
        }

        if ($this->precoEmbalagem === null) {
            throw new AppException("Preço da embalagem obrigatório");
        }
    }

    private function checkDelete(int $id): void
    {
        $sql = "SELECT
                    p.nome
                FROM
                    produto_materia_prima pmp
                    JOIN produto p ON p.id = pmp.produto_id
                WHERE
                    pmp.materia_prima_id = :id
                ORDER BY
                    p.nome
        ";
        $this->prepare($sql);
        $this->execute([
            ":id" => $id
        ]);

        if ($this->rowCount() === 0) {
            return;
        }

        $nomes = implode(", ", array_map(fn (Object $produto) => $produto->nome, $this->fetchAll()));
        throw new AppException("Esta matéria-prima é usada nos produtos: {$nomes}. Remova-a desses produtos antes de excluir.", 409);
    }

    private function checkUnidadeNosProdutos(): void
    {
        $sql = "SELECT
                    p.nome,
                    pmp.unidade_medida AS unidadeMedida
                FROM
                    produto_materia_prima pmp
                    JOIN produto p ON p.id = pmp.produto_id
                WHERE
                    pmp.materia_prima_id = :id
        ";
        $this->prepare($sql);
        $this->execute([
            ":id" => $this->id
        ]);

        foreach ($this->fetchAll() as $uso) {
            $unidadeUso = UnidadeMedida::from($uso->unidadeMedida);

            if (!$unidadeUso->compativelCom($this->unidadeMedida)) {
                throw new ValidacaoException(
                    "O produto {$uso->nome} usa esta matéria-prima em {$unidadeUso->value}, que não é compatível com {$this->unidadeMedida->value}. Ajuste o produto ou escolha uma unidade compatível.",
                    "unidadeMedida"
                );
            }
        }
    }

    private static function fixCusto(Object $materiaPrima): Object
    {
        $quantidade = $materiaPrima->quantidadeEmbalagem;
        $materiaPrima->custoPorUnidade = $quantidade > 0 ? round($materiaPrima->precoEmbalagem / $quantidade, 4) : 0.0;

        return $materiaPrima;
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

    public function getUnidadeMedida(): ?UnidadeMedida
    {
        return $this->unidadeMedida;
    }

    public function setUnidadeMedida(UnidadeMedida $unidadeMedida): self
    {
        $this->unidadeMedida = $unidadeMedida;

        return $this;
    }

    public function getQuantidadeEmbalagem(): ?float
    {
        return $this->quantidadeEmbalagem;
    }

    public function setQuantidadeEmbalagem(float $quantidadeEmbalagem): self
    {
        if ($quantidadeEmbalagem <= 0) {
            throw new ValidacaoException("A quantidade da embalagem deve ser maior que zero.", "quantidadeEmbalagem");
        }

        $this->quantidadeEmbalagem = $quantidadeEmbalagem;

        return $this;
    }

    public function getPrecoEmbalagem(): ?float
    {
        return $this->precoEmbalagem;
    }

    public function setPrecoEmbalagem(float $precoEmbalagem): self
    {
        if ($precoEmbalagem < 0) {
            throw new ValidacaoException("O preço da embalagem não pode ser negativo.", "precoEmbalagem");
        }

        $this->precoEmbalagem = $precoEmbalagem;

        return $this;
    }
}
