SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuario (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome_negocio VARCHAR(120) NOT NULL,
    nome_responsavel VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuario_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materia_prima (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    unidade_medida VARCHAR(10) NOT NULL,
    quantidade_embalagem DECIMAL(10,3) NOT NULL,
    preco_embalagem DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_materia_prima_usuario (usuario_id),
    CONSTRAINT fk_materia_prima_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produto (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    descricao TEXT NULL,
    rendimento DECIMAL(10,3) NOT NULL DEFAULT 1,
    preco_venda DECIMAL(10,2) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_produto_usuario (usuario_id),
    CONSTRAINT fk_produto_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produto_materia_prima (
    produto_id INT UNSIGNED NOT NULL,
    materia_prima_id INT UNSIGNED NOT NULL,
    quantidade DECIMAL(10,3) NOT NULL,
    unidade_medida VARCHAR(10) NOT NULL,
    PRIMARY KEY (produto_id, materia_prima_id),
    KEY idx_pmp_materia_prima (materia_prima_id),
    CONSTRAINT fk_pmp_produto FOREIGN KEY (produto_id) REFERENCES produto (id) ON DELETE CASCADE,
    CONSTRAINT fk_pmp_materia_prima FOREIGN KEY (materia_prima_id) REFERENCES materia_prima (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cliente (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    endereco VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_cliente_usuario_telefone (usuario_id, telefone),
    CONSTRAINT fk_cliente_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NOT NULL,
    hash_acompanhamento CHAR(64) NOT NULL,
    origem ENUM('cardapio', 'manual') NOT NULL,
    situacao ENUM(
        'aguardando_confirmacao',
        'em_producao',
        'pronto_retirada',
        'em_rota_entrega',
        'concluido',
        'cancelado'
    ) NOT NULL DEFAULT 'aguardando_confirmacao',
    tipo_entrega ENUM('retirada', 'entrega') NOT NULL,
    forma_pagamento VARCHAR(50) NOT NULL,
    taxa_entrega DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_total DECIMAL(10,2) NOT NULL,
    data_pedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_conclusao DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pedido_hash (hash_acompanhamento),
    KEY idx_pedido_usuario_situacao (usuario_id, situacao),
    KEY idx_pedido_usuario_data (usuario_id, data_pedido),
    KEY idx_pedido_cliente (cliente_id),
    CONSTRAINT fk_pedido_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE,
    CONSTRAINT fk_pedido_cliente FOREIGN KEY (cliente_id) REFERENCES cliente (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historico_situacao (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id INT UNSIGNED NOT NULL,
    situacao ENUM(
        'aguardando_confirmacao',
        'em_producao',
        'pronto_retirada',
        'em_rota_entrega',
        'concluido',
        'cancelado'
    ) NOT NULL,
    data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_historico_pedido (pedido_id),
    CONSTRAINT fk_historico_pedido FOREIGN KEY (pedido_id) REFERENCES pedido (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_pedido (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id INT UNSIGNED NOT NULL,
    produto_id INT UNSIGNED NOT NULL,
    quantidade INT UNSIGNED NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    custo_unitario DECIMAL(12,4) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_item_pedido_pedido (pedido_id),
    KEY idx_item_pedido_produto (produto_id),
    CONSTRAINT fk_item_pedido_pedido FOREIGN KEY (pedido_id) REFERENCES pedido (id) ON DELETE CASCADE,
    CONSTRAINT fk_item_pedido_produto FOREIGN KEY (produto_id) REFERENCES produto (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cardapio (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    link_publico VARCHAR(80) NOT NULL,
    cor_primaria CHAR(7) NOT NULL DEFAULT '#1F2937',
    cor_secundaria CHAR(7) NOT NULL DEFAULT '#F59E0B',
    endereco VARCHAR(255) NULL,
    formas_pagamento VARCHAR(255) NULL,
    taxa_entrega DECIMAL(10,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cardapio_usuario (usuario_id),
    UNIQUE KEY uk_cardapio_link (link_publico),
    CONSTRAINT fk_cardapio_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cardapio_produto (
    cardapio_id INT UNSIGNED NOT NULL,
    produto_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (cardapio_id, produto_id),
    KEY idx_cardapio_produto_produto (produto_id),
    CONSTRAINT fk_cardapio_produto_cardapio FOREIGN KEY (cardapio_id) REFERENCES cardapio (id) ON DELETE CASCADE,
    CONSTRAINT fk_cardapio_produto_produto FOREIGN KEY (produto_id) REFERENCES produto (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
