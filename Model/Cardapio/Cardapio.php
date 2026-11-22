<?php

namespace Model\Cardapio;

use Model\Exception\AppException;
use Model\Exception\ValidacaoException;
use Model\Model;
use Utils\Cor;
use Utils\Format;

class Cardapio extends Model
{
    public const COR_PRIMARIA_PADRAO = "#1F2937";
    public const COR_SECUNDARIA_PADRAO = "#F59E0B";

    protected ?int $id = null;
    protected ?int $idUsuario = null;
    protected ?string $linkPublico = null;
    protected ?string $corPrimaria = self::COR_PRIMARIA_PADRAO;
    protected ?string $corSecundaria = self::COR_SECUNDARIA_PADRAO;
    protected ?string $endereco = null;
    protected ?Array $formasPagamento = [];
    protected ?float $taxaEntrega = 0.0;
    protected ?Array $produtos = [];

    public function get(): Object
    {
        if (empty($this->idUsuario) && empty($this->linkPublico)) {
            throw new AppException("Necessário informar o usuário ou o link público");
        }

        $where = (Object) [
            "column" => "usuario_id",
            "bind" => "idUsuario",
            "value" => $this->idUsuario
        ];

        if (!empty($this->linkPublico)) {
            $where = (Object) [
                "column" => "link_publico",
                "bind" => "linkPublico",
                "value" => $this->linkPublico
            ];
        }

        $sql = "SELECT
                    c.id,
                    c.usuario_id AS idUsuario,
                    c.link_publico AS linkPublico,
                    u.nome_negocio AS nomeNegocio,
                    c.cor_primaria AS corPrimaria,
                    c.cor_secundaria AS corSecundaria,
                    c.endereco,
                    c.formas_pagamento AS formasPagamento,
                    c.taxa_entrega AS taxaEntrega
                FROM
                    cardapio c
                    JOIN usuario u ON u.id = c.usuario_id
                WHERE
                    c.{$where->column} = :{$where->bind}
        ";
        $this->prepare($sql);
        $this->execute([
            ":{$where->bind}" => $where->value
        ]);

        if ($this->rowCount() === 0) {
            return (Object) [];
        }

        $response = $this->convertData($this->fetch());
        $response->produtos = $this->getIdsProdutos($response->id);

        return $response;
    }

    public function getPainel(): Object
    {
        $data = $this->get();

        if (!isset($data->id)) {
            $data = (Object) [
                "linkPublico" => null,
                "corPrimaria" => self::COR_PRIMARIA_PADRAO,
                "corSecundaria" => self::COR_SECUNDARIA_PADRAO,
                "endereco" => null,
                "formasPagamento" => [],
                "taxaEntrega" => 0.0,
                "produtos" => []
            ];
        }

        return (Object) [
            "linkPublico" => $data->linkPublico,
            "corPrimaria" => $data->corPrimaria,
            "corSecundaria" => $data->corSecundaria,
            "corTextoPrimaria" => Cor::textoLegivel($data->corPrimaria),
            "corTextoSecundaria" => Cor::textoLegivel($data->corSecundaria),
            "endereco" => $data->endereco,
            "formasPagamento" => $data->formasPagamento,
            "taxaEntrega" => $data->taxaEntrega,
            "produtos" => $data->produtos
        ];
    }

    public function getPublico(): Object
    {
        if (empty($this->linkPublico)) {
            throw new AppException("Necessário informar o link público");
        }

        $data = $this->get();

        if (!isset($data->id)) {
            throw new AppException("Cardápio não encontrado.", 404);
        }

        return (Object) [
            "linkPublico" => $data->linkPublico,
            "nomeNegocio" => $data->nomeNegocio,
            "corPrimaria" => $data->corPrimaria,
            "corSecundaria" => $data->corSecundaria,
            "corTextoPrimaria" => Cor::textoLegivel($data->corPrimaria),
            "corTextoSecundaria" => Cor::textoLegivel($data->corSecundaria),
            "endereco" => $data->endereco,
            "formasPagamento" => $data->formasPagamento,
            "taxaEntrega" => $data->taxaEntrega,
            "produtos" => $this->getProdutosAtivos($data->id)
        ];
    }

    public function save(): bool
    {
        $this->checkIdUsuario();
        $this->checkProdutos();

        $this->startTransaction();

        try {
            $idExistente = $this->getIdParaAtualizar();

            if ($idExistente === null) {
                $this->insert();
            } else {
                $this->setId($idExistente);
                $this->update();
            }

            $this->saveProdutos();

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }

        return true;
    }

    private function insert(): void
    {
        $this->linkPublico = $this->gerarLinkPublico();

        $sql = "INSERT INTO cardapio (
                    usuario_id,
                    link_publico,
                    cor_primaria,
                    cor_secundaria,
                    endereco,
                    formas_pagamento,
                    taxa_entrega
                ) VALUES (
                    :idUsuario,
                    :linkPublico,
                    :corPrimaria,
                    :corSecundaria,
                    :endereco,
                    :formasPagamento,
                    :taxaEntrega
                )
        ";
        $this->prepare($sql);
        $this->execute([
            ":idUsuario" => $this->idUsuario,
            ":linkPublico" => $this->linkPublico,
            ":corPrimaria" => $this->corPrimaria,
            ":corSecundaria" => $this->corSecundaria,
            ":endereco" => $this->endereco,
            ":formasPagamento" => json_encode($this->formasPagamento, JSON_UNESCAPED_UNICODE),
            ":taxaEntrega" => $this->taxaEntrega
        ]);

        if ($this->hasError()) {
            throw new AppException("Erro ao cadastrar cardápio.", 500);
        }

        $this->setId($this->getLastInsertId());
    }

    private function update(): void
    {
        $sql = "UPDATE
                    cardapio
                SET
                    cor_primaria = :corPrimaria,
                    cor_secundaria = :corSecundaria,
                    endereco = :endereco,
                    formas_pagamento = :formasPagamento,
                    taxa_entrega = :taxaEntrega
                WHERE
                    id = :id
        ";
        $this->prepare($sql);
        $this->execute([
            ":id" => $this->id,
            ":corPrimaria" => $this->corPrimaria,
            ":corSecundaria" => $this->corSecundaria,
            ":endereco" => $this->endereco,
            ":formasPagamento" => json_encode($this->formasPagamento, JSON_UNESCAPED_UNICODE),
            ":taxaEntrega" => $this->taxaEntrega
        ]);

        if ($this->hasError()) {
            throw new AppException("Erro ao editar cardápio.", 500);
        }
    }

    private function getIdParaAtualizar(): ?int
    {
        $sql = "SELECT id FROM cardapio WHERE usuario_id = :idUsuario FOR UPDATE";
        $this->prepare($sql);
        $this->execute([
            ":idUsuario" => $this->idUsuario
        ]);

        if ($this->rowCount() === 0) {
            return null;
        }

        return (int) $this->fetch()->id;
    }

    private function saveProdutos(): void
    {
        $sql = "DELETE FROM cardapio_produto WHERE cardapio_id = :id";
        $this->prepare($sql);
        $this->execute([
            ":id" => $this->id
        ]);

        if (!$this->produtos) {
            return;
        }

        $valores = [];
        $bind = [":id" => $this->id];
        foreach ($this->produtos as $indice => $idProduto) {
            $valores[] = "(:id, :idProduto{$indice})";
            $bind[":idProduto{$indice}"] = $idProduto;
        }
        $valores = implode(", ", $valores);

        $sql = "INSERT INTO cardapio_produto (
                    cardapio_id,
                    produto_id
                ) VALUES
                    {$valores}
        ";
        $this->prepare($sql);
        $this->execute($bind);

        if ($this->hasError()) {
            throw new AppException("Erro ao salvar os produtos do cardápio.", 500);
        }
    }

    private function checkProdutos(): void
    {
        if (!$this->produtos) {
            return;
        }

        $bind = [":idUsuario" => $this->idUsuario];
        $marcadores = self::bindList("idProduto", $this->produtos, $bind);

        $sql = "SELECT
                    id
                FROM
                    produto
                WHERE
                    usuario_id = :idUsuario AND
                    ativo = 1 AND
                    id IN ({$marcadores})
        ";
        $this->prepare($sql);
        $this->execute($bind);

        $encontrados = array_map(fn (Object $produto) => (int) $produto->id, $this->fetchAll());
        $invalidos = array_diff($this->produtos, $encontrados);

        if ($invalidos) {
            $lista = implode(", ", $invalidos);
            throw new ValidacaoException("Os produtos {$lista} não existem ou estão desativados. Selecione apenas produtos ativos.", "produtos");
        }
    }

    private function getIdsProdutos(int $idCardapio): Array
    {
        $sql = "SELECT produto_id AS idProduto FROM cardapio_produto WHERE cardapio_id = :idCardapio ORDER BY produto_id";
        $this->prepare($sql);
        $this->execute([
            ":idCardapio" => $idCardapio
        ]);

        return array_map(fn (Object $produto) => (int) $produto->idProduto, $this->fetchAll());
    }

    private function getProdutosAtivos(int $idCardapio): Array
    {
        $sql = "SELECT
                    p.id,
                    p.nome,
                    p.descricao,
                    p.preco_venda AS preco
                FROM
                    cardapio_produto cp
                    JOIN produto p ON p.id = cp.produto_id
                WHERE
                    cp.cardapio_id = :idCardapio AND
                    p.ativo = 1
                ORDER BY
                    p.nome
        ";
        $this->prepare($sql);
        $this->execute([
            ":idCardapio" => $idCardapio
        ]);

        return array_map(fn (Object $produto) => (Object) [
            "id" => (int) $produto->id,
            "nome" => $produto->nome,
            "descricao" => $produto->descricao,
            "preco" => (float) $produto->preco,
        ], $this->fetchAll());
    }

    private function gerarLinkPublico(): string
    {
        $sql = "SELECT nome_negocio AS nomeNegocio FROM usuario WHERE id = :idUsuario";
        $this->prepare($sql);
        $this->execute([
            ":idUsuario" => $this->idUsuario
        ]);
        $usuario = $this->fetch();
        $slug = Format::slug($usuario->nomeNegocio ?? "cardapio");

        do {
            $sufixo = bin2hex(random_bytes(3));
            $link = "{$slug}-{$sufixo}";
        } while ($this->existeLinkPublico($link));

        return $link;
    }

    private function existeLinkPublico(string $link): bool
    {
        $sql = "SELECT id FROM cardapio WHERE link_publico = :linkPublico";
        $this->prepare($sql);
        $this->execute([
            ":linkPublico" => $link
        ]);

        return $this->rowCount() > 0;
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

    public function getLinkPublico(): ?string
    {
        return $this->linkPublico;
    }

    public function setLinkPublico(string $linkPublico): self
    {
        if (!preg_match("/^[a-z0-9-]{1,80}$/", $linkPublico)) {
            throw new AppException("Cardápio não encontrado.", 404);
        }

        $this->linkPublico = $linkPublico;

        return $this;
    }

    public function getCorPrimaria(): ?string
    {
        return $this->corPrimaria;
    }

    public function setCorPrimaria(string $corPrimaria): self
    {
        $this->corPrimaria = $corPrimaria;

        return $this;
    }

    public function getCorSecundaria(): ?string
    {
        return $this->corSecundaria;
    }

    public function setCorSecundaria(string $corSecundaria): self
    {
        $this->corSecundaria = $corSecundaria;

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

    public function getFormasPagamento(): ?Array
    {
        return $this->formasPagamento;
    }

    public function setFormasPagamento(Array $formasPagamento): self
    {
        $formasPagamentoJson = json_encode(array_values($formasPagamento), JSON_UNESCAPED_UNICODE);
        if (strlen($formasPagamentoJson) > 255) {
            throw new ValidacaoException("As formas de pagamento ultrapassam o limite de 255 caracteres. Use nomes mais curtos ou menos opções.", "formasPagamento");
        }

        $this->formasPagamento = array_values($formasPagamento);

        return $this;
    }

    public function getTaxaEntrega(): ?float
    {
        return $this->taxaEntrega;
    }

    public function setTaxaEntrega(float $taxaEntrega): self
    {
        if ($taxaEntrega < 0) {
            throw new ValidacaoException("A taxa de entrega não pode ser negativa.", "taxaEntrega");
        }

        $this->taxaEntrega = $taxaEntrega;

        return $this;
    }

    public function getProdutos(): ?Array
    {
        return $this->produtos;
    }

    public function setProdutos(Array $produtos): self
    {
        $this->produtos = array_values(array_unique(array_map("intval", $produtos)));

        return $this;
    }
}
