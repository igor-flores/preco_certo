<?php

namespace Model\Produto;

use Model\Exception\AppException;
use Model\Exception\ValidacaoException;
use Model\MateriaPrima\UnidadeMedida;
use Model\Model;
use Utils\FiltrosPadrao;
use Utils\Utils;

class Produto extends Model
{
    protected ?int $id = null;
    protected ?int $idUsuario = null;
    protected ?string $nome = null;
    protected ?string $descricao = null;
    protected ?float $rendimento = null;
    protected ?float $precoVenda = null;
    protected ?bool $ativo = true;
    protected ?Array $materiasPrimas = null;

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
                    p.id,
                    p.nome,
                    p.descricao,
                    p.rendimento,
                    p.preco_venda AS precoVenda,
                    p.ativo
                FROM
                    produto p
                WHERE
                    p.id = :id AND
                    p.usuario_id = :idUsuario
        ";
        $this->prepare($sql);
        $this->execute([
            ":id" => $this->id,
            ":idUsuario" => $this->idUsuario
        ]);

        if ($this->rowCount() === 0) {
            return (Object) [];
        }

        $response = $this->convertData($this->fetch());
        $composicoes = $this->getComposicoes([$response->id]);

        return self::fixCustos($response, $composicoes[$response->id] ?? [], true);
    }

    public function getAll(?FiltrosPadrao $FiltrosPadrao = null): Object
    {
        $this->checkIdUsuario();

        $where = ["p.usuario_id = :idUsuario"];
        $bind = [":idUsuario" => $this->idUsuario];

        if (isset($FiltrosPadrao?->getEspecificos()->ativo)) {
            $where[] = "p.ativo = :ativo";
            $bind[":ativo"] = (int) $FiltrosPadrao->getEspecificos()->ativo;
        }

        if (isset($FiltrosPadrao?->getEspecificos()->nome)) {
            $nome = Utils::escapeLike($FiltrosPadrao->getEspecificos()->nome);
            $where[] = "p.nome LIKE :nome";
            $bind[":nome"] = "%{$nome}%";
        }

        $where = implode(" AND". PHP_EOL, $where);

        $sql = "SELECT
                    p.id,
                    p.nome,
                    p.descricao,
                    p.rendimento,
                    p.preco_venda AS precoVenda,
                    p.ativo
                FROM
                    produto p
                WHERE
                    {$where}
                ORDER BY
                    p.nome
        ";

        $response = $this->fixGetAll($sql, $FiltrosPadrao, $bind);

        if (!$response->data) {
            return $response;
        }

        $ids = array_map(fn (Object $produto) => $produto->id, $response->data);
        $composicoes = $this->getComposicoes($ids);

        $response->data = array_map(
            fn (Object $produto) => self::fixCustos($produto, $composicoes[$produto->id] ?? [], false),
            $response->data
        );

        return $response;
    }

    public function insert(): bool
    {
        $this->checkIdUsuario();
        $this->checkCampos();

        $this->startTransaction();

        try {
            $sql = "INSERT INTO produto (
                        usuario_id,
                        nome,
                        descricao,
                        rendimento,
                        preco_venda,
                        ativo
                    ) VALUES (
                        :idUsuario,
                        :nome,
                        :descricao,
                        :rendimento,
                        :precoVenda,
                        1
                    )
            ";
            $this->prepare($sql);
            $this->execute([
                ":idUsuario" => $this->idUsuario,
                ":nome" => $this->nome,
                ":descricao" => $this->descricao,
                ":rendimento" => $this->rendimento,
                ":precoVenda" => $this->precoVenda
            ]);

            if ($this->hasError()) {
                throw new AppException("Erro ao cadastrar produto.", 500);
            }

            $this->setId($this->getLastInsertId());
            $this->ativo = true;
            $this->saveComposicao();

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }

        return true;
    }

    public function update(): bool
    {
        if (empty($this->id)) {
            throw new AppException("O código do produto é obrigatório");
        }
        $this->checkCampos();

        $data = $this->get();
        if (!isset($data->id)) {
            throw new AppException("Produto não encontrado.", 404);
        }

        $this->startTransaction();

        try {
            $sql = "UPDATE
                        produto
                    SET
                        nome = :nome,
                        descricao = :descricao,
                        rendimento = :rendimento,
                        preco_venda = :precoVenda,
                        ativo = :ativo
                    WHERE
                        id = :id AND
                        usuario_id = :idUsuario
            ";
            $this->prepare($sql);
            $this->execute([
                ":id" => $this->id,
                ":idUsuario" => $this->idUsuario,
                ":nome" => $this->nome,
                ":descricao" => $this->descricao,
                ":rendimento" => $this->rendimento,
                ":precoVenda" => $this->precoVenda,
                ":ativo" => $this->ativo
            ]);

            if ($this->hasError()) {
                throw new AppException("Erro ao editar produto.", 500);
            }

            $sql = "DELETE FROM produto_materia_prima WHERE produto_id = :id";
            $this->prepare($sql);
            $this->execute([
                ":id" => $this->id
            ]);

            $this->saveComposicao();

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }

        return true;
    }

    public function desativar(): bool
    {
        $data = $this->get();

        if (!isset($data->id)) {
            throw new AppException("Produto não encontrado.", 404);
        }

        $sql = "UPDATE produto SET ativo = 0 WHERE id = :id AND usuario_id = :idUsuario";
        $this->prepare($sql);
        $this->execute([
            ":id" => $data->id,
            ":idUsuario" => $this->idUsuario
        ]);

        return !$this->hasError();
    }

    public function getCustosUnitarios(Array $idsProdutos): Array
    {
        $this->checkIdUsuario();

        if (!$idsProdutos) {
            return [];
        }

        $bind = [":idUsuario" => $this->idUsuario];
        $marcadores = self::bindList("idProduto", $idsProdutos, $bind);

        $sql = "SELECT
                    p.id,
                    p.rendimento
                FROM
                    produto p
                WHERE
                    p.usuario_id = :idUsuario AND
                    p.id IN ({$marcadores})
        ";
        $this->prepare($sql);
        $this->execute($bind);
        $produtos = $this->fetchAll();

        $composicoes = $this->getComposicoes($idsProdutos);
        $custos = [];

        foreach ($produtos as $produto) {
            $custoReceita = self::getCustoComposicao($composicoes[$produto->id] ?? []);
            $rendimento = (float) $produto->rendimento;
            $custos[(int) $produto->id] = $rendimento > 0 ? round($custoReceita / $rendimento, 4) : 0.0;
        }

        return $custos;
    }

    private function checkCampos(): void
    {
        if (empty($this->nome)) {
            throw new AppException("Nome obrigatório");
        }

        if (empty($this->rendimento)) {
            throw new AppException("Rendimento obrigatório");
        }

        if (empty($this->precoVenda)) {
            throw new AppException("Preço de venda obrigatório");
        }

        if (empty($this->materiasPrimas)) {
            throw new ValidacaoException("Informe pelo menos um item em matérias-primas.", "materiasPrimas");
        }
    }

    private function getComposicoes(Array $idsProdutos): Array
    {
        $bind = [":idUsuario" => $this->idUsuario];
        $marcadores = self::bindList("idProduto", $idsProdutos, $bind);

        $sql = "SELECT
                    pmp.produto_id AS idProduto,
                    pmp.materia_prima_id AS idMateriaPrima,
                    pmp.quantidade,
                    pmp.unidade_medida AS unidadeMedida,
                    mp.nome,
                    mp.unidade_medida AS unidadeMateriaPrima,
                    mp.quantidade_embalagem AS quantidadeEmbalagem,
                    mp.preco_embalagem AS precoEmbalagem
                FROM
                    produto_materia_prima pmp
                    JOIN produto p ON p.id = pmp.produto_id
                    JOIN materia_prima mp ON mp.id = pmp.materia_prima_id
                WHERE
                    p.usuario_id = :idUsuario AND
                    pmp.produto_id IN ({$marcadores})
                ORDER BY
                    mp.nome
        ";
        $this->prepare($sql);
        $this->execute($bind);

        $composicoes = [];
        foreach ($this->fetchAll() as $linha) {
            $composicoes[$linha->idProduto][] = $linha;
        }

        return $composicoes;
    }

    private function saveComposicao(): void
    {
        $idsMateriasPrimas = array_map(fn (Array $item) => $item["idMateriaPrima"], $this->materiasPrimas);

        $bind = [":idUsuario" => $this->idUsuario];
        $marcadores = self::bindList("idMateriaPrima", $idsMateriasPrimas, $bind);

        $sql = "SELECT
                    id,
                    unidade_medida AS unidadeMedida
                FROM
                    materia_prima
                WHERE
                    usuario_id = :idUsuario AND
                    id IN ({$marcadores})
        ";
        $this->prepare($sql);
        $this->execute($bind);

        $unidadesCadastradas = [];
        foreach ($this->fetchAll() as $linha) {
            $unidadesCadastradas[(int) $linha->id] = UnidadeMedida::from($linha->unidadeMedida);
        }

        $valores = [];
        $bind = [];

        foreach ($this->materiasPrimas as $indice => $item) {
            $campo = "materiasPrimas[{$indice}]";
            $unidadeCadastrada = $unidadesCadastradas[$item["idMateriaPrima"]] ?? null;

            if ($unidadeCadastrada === null) {
                throw new ValidacaoException("Matéria-prima não encontrada. Selecione uma matéria-prima cadastrada.", "{$campo}.idMateriaPrima");
            }

            if (!$item["unidadeMedida"]->compativelCom($unidadeCadastrada)) {
                throw new ValidacaoException(
                    "A unidade {$item["unidadeMedida"]->value} não é compatível com a unidade da matéria-prima ({$unidadeCadastrada->value}). Use uma unidade do mesmo tipo.",
                    "{$campo}.unidadeMedida"
                );
            }

            $valores[] = "(:idProduto, :idMateriaPrima{$indice}, :quantidade{$indice}, :unidadeMedida{$indice})";
            $bind[":idMateriaPrima{$indice}"] = $item["idMateriaPrima"];
            $bind[":quantidade{$indice}"] = $item["quantidade"];
            $bind[":unidadeMedida{$indice}"] = $item["unidadeMedida"]->value;
        }

        $bind[":idProduto"] = $this->id;
        $valores = implode(", ". PHP_EOL, $valores);

        $sql = "INSERT INTO produto_materia_prima (
                    produto_id,
                    materia_prima_id,
                    quantidade,
                    unidade_medida
                ) VALUES
                    {$valores}
        ";
        $this->prepare($sql);
        $this->execute($bind);

        if ($this->hasError()) {
            throw new AppException("Erro ao salvar a composição do produto.", 500);
        }
    }

    private static function getCustoItemComposicao(Object $linha): float
    {
        $unidadeReceita = UnidadeMedida::from($linha->unidadeMedida);
        $unidadeMateriaPrima = UnidadeMedida::from($linha->unidadeMateriaPrima);
        $quantidadeEmbalagem = (float) $linha->quantidadeEmbalagem;

        if ($quantidadeEmbalagem <= 0) {
            return 0.0;
        }

        $quantidadeConvertida = $unidadeReceita->converter((float) $linha->quantidade, $unidadeMateriaPrima);

        return $quantidadeConvertida * (float) $linha->precoEmbalagem / $quantidadeEmbalagem;
    }

    private static function getCustoComposicao(Array $composicao): float
    {
        return array_sum(array_map(fn (Object $linha) => self::getCustoItemComposicao($linha), $composicao));
    }

    private static function fixCustos(Object $produto, Array $composicao, bool $detalhado): Object
    {
        $custoReceita = self::getCustoComposicao($composicao);
        $custoUnitario = $produto->rendimento > 0 ? $custoReceita / $produto->rendimento : 0.0;
        $lucroUnitario = $produto->precoVenda - $custoUnitario;

        $produto->custoReceita = Utils::dinheiro($custoReceita);
        $produto->custoUnitario = Utils::dinheiro($custoUnitario);
        $produto->lucroUnitario = Utils::dinheiro($lucroUnitario);
        $produto->margemLucro = Utils::percentual($lucroUnitario, $produto->precoVenda);

        if (!$detalhado) {
            return $produto;
        }

        $produto->materiasPrimas = array_map(fn (Object $linha) => (Object) [
            "idMateriaPrima" => (int) $linha->idMateriaPrima,
            "nome" => $linha->nome,
            "quantidade" => (float) $linha->quantidade,
            "unidadeMedida" => $linha->unidadeMedida,
            "custo" => Utils::dinheiro(self::getCustoItemComposicao($linha)),
        ], $composicao);

        return $produto;
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

    public function getDescricao(): ?string
    {
        return $this->descricao;
    }

    public function setDescricao(?string $descricao): self
    {
        $this->descricao = $descricao;

        return $this;
    }

    public function getRendimento(): ?float
    {
        return $this->rendimento;
    }

    public function setRendimento(float $rendimento): self
    {
        if ($rendimento <= 0) {
            throw new ValidacaoException("O rendimento deve ser maior que zero.", "rendimento");
        }

        $this->rendimento = $rendimento;

        return $this;
    }

    public function getPrecoVenda(): ?float
    {
        return $this->precoVenda;
    }

    public function setPrecoVenda(float $precoVenda): self
    {
        if ($precoVenda <= 0) {
            throw new ValidacaoException("O preço de venda deve ser maior que zero.", "precoVenda");
        }

        $this->precoVenda = $precoVenda;

        return $this;
    }

    public function getAtivo(): ?bool
    {
        return $this->ativo;
    }

    public function setAtivo(bool $ativo): self
    {
        $this->ativo = $ativo;

        return $this;
    }

    public function getMateriasPrimas(): ?Array
    {
        return $this->materiasPrimas;
    }

    public function setMateriasPrimas(Array $materiasPrimas): self
    {
        foreach ($materiasPrimas as $indice => $item) {
            if (!isset($item["idMateriaPrima"], $item["quantidade"], $item["unidadeMedida"])) {
                throw new ValidacaoException("Cada matéria-prima deve ter idMateriaPrima, quantidade e unidadeMedida.", "materiasPrimas[{$indice}]");
            }

            if (!$item["unidadeMedida"] instanceof UnidadeMedida) {
                throw new ValidacaoException("Unidade de medida inválida.", "materiasPrimas[{$indice}].unidadeMedida");
            }
        }

        $this->materiasPrimas = array_values($materiasPrimas);

        return $this;
    }
}
