<?php

namespace Model\Pedido;

use DateTimeImmutable;
use Model\Cardapio\Cardapio;
use Model\Cliente\Cliente;
use Model\Exception\AppException;
use Model\Exception\ValidacaoException;
use Model\Model;
use Model\Produto\Produto;
use Utils\Cor;
use Utils\FiltrosPadrao;
use Utils\Utils;

class Pedido extends Model
{
    protected ?int $id = null;
    protected ?int $idUsuario = null;
    protected ?int $idCliente = null;
    protected ?string $hashAcompanhamento = null;
    protected ?OrigemPedido $origem = null;
    protected ?SituacaoPedido $situacao = SituacaoPedido::AguardandoConfirmacao;
    protected ?TipoEntrega $tipoEntrega = null;
    protected ?string $formaPagamento = null;
    protected ?float $taxaEntrega = null;
    protected ?float $valorTotal = null;
    protected ?DateTimeImmutable $dataPedido = null;
    protected ?Array $itens = null;
    protected ?Cliente $Cliente = null;

    public function __construct(?int $id = null)
    {
        parent::__construct();

        if (!empty($id)) {
            $this->setId($id);
        }
    }

    public function get(): Object
    {
        if (empty($this->id) && empty($this->hashAcompanhamento)) {
            throw new AppException("Necessário informar o id");
        }

        $where = ["p.id = :id", "p.usuario_id = :idUsuario"];
        $bind = [
            ":id" => $this->id,
            ":idUsuario" => $this->idUsuario
        ];

        if (!empty($this->hashAcompanhamento)) {
            $where = ["p.hash_acompanhamento = :hashAcompanhamento"];
            $bind = [":hashAcompanhamento" => $this->hashAcompanhamento];
        } else {
            $this->checkIdUsuario();
        }

        $where = implode(" AND". PHP_EOL, $where);

        $sql = "SELECT
                    p.id,
                    p.usuario_id AS idUsuario,
                    p.cliente_id AS idCliente,
                    p.hash_acompanhamento AS hashAcompanhamento,
                    p.origem,
                    p.situacao,
                    p.tipo_entrega AS tipoEntrega,
                    p.forma_pagamento AS formaPagamento,
                    p.taxa_entrega AS taxaEntrega,
                    p.valor_total AS valorTotal,
                    p.data_pedido AS dataPedido,
                    p.data_conclusao AS dataConclusao,
                    c.nome AS clienteNome,
                    c.telefone AS clienteTelefone,
                    c.endereco AS clienteEndereco,
                    u.nome_negocio AS nomeNegocio,
                    ca.link_publico AS linkCardapio,
                    ca.cor_primaria AS corPrimaria,
                    ca.cor_secundaria AS corSecundaria
                FROM
                    pedido p
                    JOIN cliente c ON c.id = p.cliente_id
                    JOIN usuario u ON u.id = p.usuario_id
                    LEFT JOIN cardapio ca ON ca.usuario_id = p.usuario_id
                WHERE
                    {$where}
        ";
        $this->prepare($sql);
        $this->execute($bind);

        if ($this->rowCount() === 0) {
            return (Object) [];
        }

        $response = $this->fetch();

        return $this->convertData($response);
    }

    public function getDetalhado(): Object
    {
        $pedido = $this->get();

        if (!isset($pedido->id)) {
            throw new AppException("Pedido não encontrado.", 404);
        }

        $itens = $this->getItensPedido($pedido->id, true);
        $valorItens = array_sum(array_map(fn (Object $item) => $item->subtotal, $itens));
        $custo = array_sum(array_map(fn (Object $item) => $item->custoTotal, $itens));
        $lucro = $valorItens - $custo;

        return (Object) [
            "id" => $pedido->id,
            "hashAcompanhamento" => $pedido->hashAcompanhamento,
            "origem" => $pedido->origem,
            "situacao" => $pedido->situacao,
            "rotuloSituacao" => SituacaoPedido::from($pedido->situacao)->rotulo(),
            "tipoEntrega" => $pedido->tipoEntrega,
            "formaPagamento" => $pedido->formaPagamento,
            "dataPedido" => $pedido->dataPedido,
            "dataConclusao" => $pedido->dataConclusao,
            "cliente" => (Object) [
                "id" => $pedido->idCliente,
                "nome" => $pedido->clienteNome,
                "telefone" => $pedido->clienteTelefone,
                "endereco" => $pedido->clienteEndereco,
            ],
            "itens" => $itens,
            "valorItens" => Utils::dinheiro($valorItens),
            "taxaEntrega" => $pedido->taxaEntrega,
            "valorTotal" => $pedido->valorTotal,
            "custo" => Utils::dinheiro($custo),
            "lucro" => Utils::dinheiro($lucro),
            "margemLucro" => Utils::percentual($lucro, $valorItens),
            "historico" => $this->getHistorico($pedido->id),
        ];
    }

    public function getAcompanhamento(): Object
    {
        if (empty($this->hashAcompanhamento)) {
            throw new AppException("Necessário informar o código de acompanhamento");
        }

        $pedido = $this->get();

        if (!isset($pedido->id)) {
            throw new AppException("Pedido não encontrado.", 404);
        }

        $corPrimaria = $pedido->corPrimaria ?? Cardapio::COR_PRIMARIA_PADRAO;
        $corSecundaria = $pedido->corSecundaria ?? Cardapio::COR_SECUNDARIA_PADRAO;

        return (Object) [
            "hashAcompanhamento" => $pedido->hashAcompanhamento,
            "nomeNegocio" => $pedido->nomeNegocio,
            "linkCardapio" => $pedido->linkCardapio,
            "corPrimaria" => $corPrimaria,
            "corSecundaria" => $corSecundaria,
            "corTextoPrimaria" => Cor::textoLegivel($corPrimaria),
            "corTextoSecundaria" => Cor::textoLegivel($corSecundaria),
            "cliente" => $pedido->clienteNome,
            "situacao" => $pedido->situacao,
            "rotuloSituacao" => SituacaoPedido::from($pedido->situacao)->rotulo(),
            "tipoEntrega" => $pedido->tipoEntrega,
            "formaPagamento" => $pedido->formaPagamento,
            "dataPedido" => $pedido->dataPedido,
            "itens" => $this->getItensPedido($pedido->id, false),
            "taxaEntrega" => $pedido->taxaEntrega,
            "valorTotal" => $pedido->valorTotal,
            "historico" => $this->getHistorico($pedido->id),
        ];
    }

    public function getAll(?FiltrosPadrao $FiltrosPadrao = null): Object
    {
        $this->checkIdUsuario();

        $where = ["p.usuario_id = :idUsuario"];
        $bind = [":idUsuario" => $this->idUsuario];

        if (isset($FiltrosPadrao?->getEspecificos()->situacao)) {
            $where[] = "p.situacao = :situacao";
            $bind[":situacao"] = $FiltrosPadrao->getEspecificos()->situacao;
        }

        if (isset($FiltrosPadrao?->getEspecificos()->origem)) {
            $where[] = "p.origem = :origem";
            $bind[":origem"] = $FiltrosPadrao->getEspecificos()->origem;
        }

        $where = implode(" AND". PHP_EOL, $where);

        $sql = "SELECT
                    p.id,
                    p.origem,
                    p.situacao,
                    p.tipo_entrega AS tipoEntrega,
                    p.forma_pagamento AS formaPagamento,
                    p.taxa_entrega AS taxaEntrega,
                    p.valor_total AS valorTotal,
                    p.data_pedido AS dataPedido,
                    p.data_conclusao AS dataConclusao,
                    c.nome AS clienteNome,
                    c.telefone AS clienteTelefone
                FROM
                    pedido p
                    JOIN cliente c ON c.id = p.cliente_id
                WHERE
                    {$where}
                ORDER BY
                    p.data_pedido DESC,
                    p.id DESC
        ";

        $response = $this->fixGetAll($sql, $FiltrosPadrao, $bind);
        $response->total = $this->getTotal($where, $bind);

        $ids = array_map(fn (Object $pedido) => $pedido->id, $response->data);
        $itensPorPedido = $this->getResumoItens($ids);

        $response->data = array_map(fn (Object $pedido) => (Object) [
            "id" => $pedido->id,
            "origem" => $pedido->origem,
            "situacao" => $pedido->situacao,
            "rotuloSituacao" => SituacaoPedido::from($pedido->situacao)->rotulo(),
            "tipoEntrega" => $pedido->tipoEntrega,
            "formaPagamento" => $pedido->formaPagamento,
            "taxaEntrega" => $pedido->taxaEntrega,
            "valorTotal" => $pedido->valorTotal,
            "dataPedido" => $pedido->dataPedido,
            "dataConclusao" => $pedido->dataConclusao,
            "cliente" => (Object) [
                "nome" => $pedido->clienteNome,
                "telefone" => $pedido->clienteTelefone,
            ],
            "itens" => $itensPorPedido[$pedido->id] ?? [],
        ], $response->data);

        return $response;
    }

    public function insert(): bool
    {
        $this->checkIdUsuario();
        $this->checkCampos();

        $Cardapio = $this->origem === OrigemPedido::Cardapio ? $this->getCardapio() : null;

        if ($Cardapio !== null) {
            $this->checkFormaPagamento($Cardapio->formasPagamento);
            $this->taxaEntrega = $Cardapio->taxaEntrega;
        }

        if ($this->tipoEntrega === TipoEntrega::Retirada) {
            $this->taxaEntrega = 0.0;
        }

        $this->taxaEntrega = $this->taxaEntrega ?? 0.0;

        $idsProdutos = array_map(fn (Array $item) => $item["idProduto"], $this->itens);
        $precos = $this->getPrecosProdutos($idsProdutos, $Cardapio->id ?? null);

        foreach ($this->itens as $indice => $item) {
            if (!isset($precos[$item["idProduto"]])) {
                throw new ValidacaoException("Este produto não está disponível. Remova-o do pedido.", "itens[{$indice}].idProduto");
            }
        }

        $custos = (new Produto())->setIdUsuario($this->idUsuario)->getCustosUnitarios($idsProdutos);

        $valorItens = 0.0;
        foreach ($this->itens as $item) {
            $valorItens += round($precos[$item["idProduto"]] * $item["quantidade"], 2);
        }

        $agora = new DateTimeImmutable();
        $this->dataPedido = $this->dataPedido ?? $agora;
        $this->valorTotal = round($valorItens + $this->taxaEntrega, 2);
        $this->hashAcompanhamento = bin2hex(random_bytes(32));
        $dataConclusao = $this->situacao === SituacaoPedido::Concluido ? $agora->format("Y-m-d H:i:s") : null;

        $this->startTransaction();

        try {
            $this->Cliente->setIdUsuario($this->idUsuario)->saveByTelefone();
            $this->idCliente = $this->Cliente->getId();

            $sql = "INSERT INTO pedido (
                        usuario_id,
                        cliente_id,
                        hash_acompanhamento,
                        origem,
                        situacao,
                        tipo_entrega,
                        forma_pagamento,
                        taxa_entrega,
                        valor_total,
                        data_pedido,
                        data_conclusao
                    ) VALUES (
                        :idUsuario,
                        :idCliente,
                        :hashAcompanhamento,
                        :origem,
                        :situacao,
                        :tipoEntrega,
                        :formaPagamento,
                        :taxaEntrega,
                        :valorTotal,
                        :dataPedido,
                        :dataConclusao
                    )
            ";
            $this->prepare($sql);
            $this->execute([
                ":idUsuario" => $this->idUsuario,
                ":idCliente" => $this->idCliente,
                ":hashAcompanhamento" => $this->hashAcompanhamento,
                ":origem" => $this->origem->value,
                ":situacao" => $this->situacao->value,
                ":tipoEntrega" => $this->tipoEntrega->value,
                ":formaPagamento" => $this->formaPagamento,
                ":taxaEntrega" => $this->taxaEntrega,
                ":valorTotal" => $this->valorTotal,
                ":dataPedido" => $this->dataPedido->format("Y-m-d H:i:s"),
                ":dataConclusao" => $dataConclusao
            ]);

            if ($this->hasError()) {
                throw new AppException("Erro ao cadastrar pedido.", 500);
            }

            $this->setId($this->getLastInsertId());
            $this->insertItens($precos, $custos);
            $this->insertHistorico($this->situacao, $agora);

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }

        return true;
    }

    public function alterarSituacao(SituacaoPedido $novaSituacao): bool
    {
        $pedido = $this->get();

        if (!isset($pedido->id)) {
            throw new AppException("Pedido não encontrado.", 404);
        }

        $situacaoAtual = SituacaoPedido::from($pedido->situacao);
        $tipoEntrega = TipoEntrega::from($pedido->tipoEntrega);

        $this->checkMudancaSituacao($situacaoAtual, $novaSituacao, $tipoEntrega);

        $agora = new DateTimeImmutable();
        $dataConclusao = $novaSituacao === SituacaoPedido::Concluido ? $agora->format("Y-m-d H:i:s") : null;

        $this->startTransaction();

        try {
            $sql = "UPDATE
                        pedido
                    SET
                        situacao = :situacao,
                        data_conclusao = :dataConclusao
                    WHERE
                        id = :id AND
                        usuario_id = :idUsuario AND
                        situacao = :situacaoAtual
            ";
            $this->prepare($sql);
            $this->execute([
                ":situacao" => $novaSituacao->value,
                ":dataConclusao" => $dataConclusao,
                ":id" => $pedido->id,
                ":idUsuario" => $this->idUsuario,
                ":situacaoAtual" => $situacaoAtual->value
            ]);

            if ($this->hasError()) {
                throw new AppException("Erro ao alterar a situação do pedido.", 500);
            }

            if ($this->rowCount() === 0) {
                throw new AppException("O pedido foi alterado por outra operação. Atualize a página e tente novamente.", 409);
            }

            $this->insertHistorico($novaSituacao, $agora);

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }

        $this->situacao = $novaSituacao;

        return true;
    }

    private function checkCampos(): void
    {
        if ($this->origem === null) {
            throw new AppException("Origem obrigatória");
        }

        if ($this->tipoEntrega === null) {
            throw new AppException("Tipo de entrega obrigatório");
        }

        if (empty($this->formaPagamento)) {
            throw new AppException("Forma de pagamento obrigatória");
        }

        if ($this->Cliente === null) {
            throw new AppException("Cliente obrigatório");
        }

        if (empty($this->itens)) {
            throw new ValidacaoException("Informe pelo menos um item em itens do pedido.", "itens");
        }

        if ($this->tipoEntrega === TipoEntrega::Entrega && empty($this->Cliente->getEndereco())) {
            throw new ValidacaoException("Informe o endereço de entrega.", "endereco");
        }
    }

    private function checkMudancaSituacao(SituacaoPedido $atual, SituacaoPedido $nova, TipoEntrega $tipoEntrega): void
    {
        if ($atual === $nova) {
            throw new ValidacaoException("O pedido já está na situação \"{$nova->rotulo()}\".", "situacao");
        }

        if ($atual->isFinal()) {
            throw new AppException("Pedidos concluídos ou cancelados não podem mudar de situação.", 409);
        }

        if ($nova === SituacaoPedido::ProntoRetirada && $tipoEntrega === TipoEntrega::Entrega) {
            throw new ValidacaoException("Este pedido é para entrega. Use a situação \"Em rota de entrega\".", "situacao");
        }

        if ($nova === SituacaoPedido::EmRotaEntrega && $tipoEntrega === TipoEntrega::Retirada) {
            throw new ValidacaoException("Este pedido é para retirada. Use a situação \"Pronto para retirada\".", "situacao");
        }
    }

    private function checkFormaPagamento(Array $formasAceitas): void
    {
        if (!$formasAceitas || in_array($this->formaPagamento, $formasAceitas, true)) {
            return;
        }

        $opcoes = implode(", ", $formasAceitas);
        throw new ValidacaoException("Forma de pagamento não aceita. Escolha uma destas: {$opcoes}.", "formaPagamento");
    }

    private function getCardapio(): Object
    {
        $cardapio = (new Cardapio())->setIdUsuario($this->idUsuario)->get();

        if (!isset($cardapio->id)) {
            throw new AppException("Cardápio não encontrado.", 404);
        }

        return $cardapio;
    }

    private function getPrecosProdutos(Array $idsProdutos, ?int $idCardapio): Array
    {
        $bind = [":idUsuario" => $this->idUsuario];
        $marcadores = self::bindList("idProduto", $idsProdutos, $bind);

        $joinCardapio = "";
        if ($idCardapio !== null) {
            $joinCardapio = "JOIN cardapio_produto cp ON cp.produto_id = p.id AND cp.cardapio_id = :idCardapio";
            $bind[":idCardapio"] = $idCardapio;
        }

        $sql = "SELECT
                    p.id,
                    p.preco_venda AS precoVenda
                FROM
                    produto p
                    {$joinCardapio}
                WHERE
                    p.usuario_id = :idUsuario AND
                    p.ativo = 1 AND
                    p.id IN ({$marcadores})
        ";
        $this->prepare($sql);
        $this->execute($bind);

        $precos = [];
        foreach ($this->fetchAll() as $produto) {
            $precos[(int) $produto->id] = (float) $produto->precoVenda;
        }

        return $precos;
    }

    private function insertItens(Array $precos, Array $custos): void
    {
        $valores = [];
        $bind = [":idPedido" => $this->id];

        foreach ($this->itens as $indice => $item) {
            $valores[] = "(:idPedido, :idProduto{$indice}, :quantidade{$indice}, :precoUnitario{$indice}, :custoUnitario{$indice})";
            $bind[":idProduto{$indice}"] = $item["idProduto"];
            $bind[":quantidade{$indice}"] = $item["quantidade"];
            $bind[":precoUnitario{$indice}"] = $precos[$item["idProduto"]];
            $bind[":custoUnitario{$indice}"] = $custos[$item["idProduto"]] ?? 0.0;
        }
        $valores = implode(", ". PHP_EOL, $valores);

        $sql = "INSERT INTO item_pedido (
                    pedido_id,
                    produto_id,
                    quantidade,
                    preco_unitario,
                    custo_unitario
                ) VALUES
                    {$valores}
        ";
        $this->prepare($sql);
        $this->execute($bind);

        if ($this->hasError()) {
            throw new AppException("Erro ao cadastrar os itens do pedido.", 500);
        }
    }

    private function insertHistorico(SituacaoPedido $situacao, DateTimeImmutable $dataHora): void
    {
        $sql = "INSERT INTO historico_situacao (
                    pedido_id,
                    situacao,
                    data_hora
                ) VALUES (
                    :idPedido,
                    :situacao,
                    :dataHora
                )
        ";
        $this->prepare($sql);
        $this->execute([
            ":idPedido" => $this->id,
            ":situacao" => $situacao->value,
            ":dataHora" => $dataHora->format("Y-m-d H:i:s")
        ]);

        if ($this->hasError()) {
            throw new AppException("Erro ao registrar o histórico do pedido.", 500);
        }
    }

    private function getTotal(string $where, Array $bind): int
    {
        $sql = "SELECT
                    COUNT(*) AS total
                FROM
                    pedido p
                WHERE
                    {$where}
        ";
        $this->prepare($sql);
        $this->execute($bind);

        return (int) $this->fetch()->total;
    }

    private function getItensPedido(int $idPedido, bool $comCusto): Array
    {
        $sql = "SELECT
                    ip.produto_id AS idProduto,
                    pr.nome,
                    ip.quantidade,
                    ip.preco_unitario AS precoUnitario,
                    ip.custo_unitario AS custoUnitario
                FROM
                    item_pedido ip
                    JOIN produto pr ON pr.id = ip.produto_id
                WHERE
                    ip.pedido_id = :idPedido
                ORDER BY
                    ip.id
        ";
        $this->prepare($sql);
        $this->execute([
            ":idPedido" => $idPedido
        ]);

        return array_map(function (Object $item) use ($comCusto): Object {
            $quantidade = (int) $item->quantidade;
            $precoUnitario = (float) $item->precoUnitario;
            $dados = (Object) [
                "idProduto" => (int) $item->idProduto,
                "nome" => $item->nome,
                "quantidade" => $quantidade,
                "precoUnitario" => $precoUnitario,
                "subtotal" => round($precoUnitario * $quantidade, 2),
            ];

            if (!$comCusto) {
                return $dados;
            }

            $custoUnitario = (float) $item->custoUnitario;
            $dados->custoUnitario = round($custoUnitario, 2);
            $dados->custoTotal = round($custoUnitario * $quantidade, 2);

            return $dados;
        }, $this->fetchAll());
    }

    private function getHistorico(int $idPedido): Array
    {
        $sql = "SELECT
                    situacao,
                    data_hora AS dataHora
                FROM
                    historico_situacao
                WHERE
                    pedido_id = :idPedido
                ORDER BY
                    data_hora,
                    id
        ";
        $this->prepare($sql);
        $this->execute([
            ":idPedido" => $idPedido
        ]);

        return array_map(fn (Object $registro) => (Object) [
            "situacao" => $registro->situacao,
            "rotulo" => SituacaoPedido::from($registro->situacao)->rotulo(),
            "dataHora" => $registro->dataHora,
        ], $this->fetchAll());
    }

    private function getResumoItens(Array $idsPedidos): Array
    {
        if (!$idsPedidos) {
            return [];
        }

        $bind = [];
        $marcadores = self::bindList("idPedido", $idsPedidos, $bind);

        $sql = "SELECT
                    ip.pedido_id AS idPedido,
                    pr.nome,
                    ip.quantidade
                FROM
                    item_pedido ip
                    JOIN produto pr ON pr.id = ip.produto_id
                WHERE
                    ip.pedido_id IN ({$marcadores})
                ORDER BY
                    ip.id
        ";
        $this->prepare($sql);
        $this->execute($bind);

        $itens = [];
        foreach ($this->fetchAll() as $item) {
            $itens[(int) $item->idPedido][] = (Object) [
                "nome" => $item->nome,
                "quantidade" => (int) $item->quantidade,
            ];
        }

        return $itens;
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

    public function getHashAcompanhamento(): ?string
    {
        return $this->hashAcompanhamento;
    }

    public function setHashAcompanhamento(string $hashAcompanhamento): self
    {
        if (!preg_match("/^[a-f0-9]{64}$/", $hashAcompanhamento)) {
            throw new AppException("Pedido não encontrado.", 404);
        }

        $this->hashAcompanhamento = $hashAcompanhamento;

        return $this;
    }

    public function getOrigem(): ?OrigemPedido
    {
        return $this->origem;
    }

    public function setOrigem(OrigemPedido $origem): self
    {
        $this->origem = $origem;

        return $this;
    }

    public function getSituacao(): ?SituacaoPedido
    {
        return $this->situacao;
    }

    public function setSituacao(SituacaoPedido $situacao): self
    {
        $this->situacao = $situacao;

        return $this;
    }

    public function getTipoEntrega(): ?TipoEntrega
    {
        return $this->tipoEntrega;
    }

    public function setTipoEntrega(TipoEntrega $tipoEntrega): self
    {
        $this->tipoEntrega = $tipoEntrega;

        return $this;
    }

    public function getFormaPagamento(): ?string
    {
        return $this->formaPagamento;
    }

    public function setFormaPagamento(string $formaPagamento): self
    {
        $this->formaPagamento = $formaPagamento;

        return $this;
    }

    public function getTaxaEntrega(): ?float
    {
        return $this->taxaEntrega;
    }

    public function setTaxaEntrega(?float $taxaEntrega): self
    {
        if ($taxaEntrega !== null && $taxaEntrega < 0) {
            throw new ValidacaoException("A taxa de entrega não pode ser negativa.", "taxaEntrega");
        }

        $this->taxaEntrega = $taxaEntrega;

        return $this;
    }

    public function getValorTotal(): ?float
    {
        return $this->valorTotal;
    }

    public function getDataPedido(): ?DateTimeImmutable
    {
        return $this->dataPedido;
    }

    public function setDataPedido(?DateTimeImmutable $dataPedido): self
    {
        if ($dataPedido !== null && $dataPedido > new DateTimeImmutable()) {
            throw new ValidacaoException("A data do pedido não pode estar no futuro.", "dataPedido");
        }

        $this->dataPedido = $dataPedido;

        return $this;
    }

    public function getItens(): ?Array
    {
        return $this->itens;
    }

    public function setItens(Array $itens): self
    {
        foreach ($itens as $indice => $item) {
            if (!isset($item["idProduto"], $item["quantidade"])) {
                throw new ValidacaoException("Cada item deve ter idProduto e quantidade.", "itens[{$indice}]");
            }
        }

        $this->itens = array_values($itens);

        return $this;
    }

    public function getCliente(): ?Cliente
    {
        return $this->Cliente;
    }

    public function setCliente(Cliente $Cliente): self
    {
        $this->Cliente = $Cliente;

        return $this;
    }
}
