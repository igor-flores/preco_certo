# Preço Certo

Sistema web de gestão para pequenos negócios de produção própria, como confeitarias, salgaderias e marmitarias. Ele ajuda o dono do negócio a saber quanto custa cada produto, por quanto vender e quanto está lucrando.

## Funcionalidades

- **Matérias-primas:** cadastro com tamanho e preço da embalagem e unidade de medida. O custo por unidade é calculado automaticamente.
- **Produtos:** ficha técnica (receita) com as matérias-primas e as quantidades. O sistema calcula o custo da receita, o custo unitário, o lucro e a margem.
- **Cardápio público:** página da loja com link próprio, cores personalizadas, formas de pagamento, taxa de entrega e os produtos escolhidos.
- **Pedidos:** podem vir do cardápio público ou ser lançados manualmente no painel. Cada pedido segue um fluxo de situações (aguardando confirmação → em produção → pronto para retirada / em rota de entrega → concluído ou cancelado). O cliente acompanha o próprio pedido por um link com um hash.
- **Histórico de vendas:** filtros por cliente, produto, período e situação, com resumo de faturamento, custo e lucro.
- **Relatórios:** comparativo financeiro do período atual com o anterior (semana, mês ou ano) e estimativa de vendas por regressão linear sobre o histórico.

O sistema tem duas áreas:

- o **painel**, que exige login e mostra só os dados do próprio negócio;
- a **área pública**, com o cardápio (`/loja/`) e o acompanhamento de pedido (`/acompanhar/`).

## Tecnologias e versões

| Item | Versão |
|---|---|
| PHP | 8.3 no Docker local, 8.5 em produção |
| Apache | 2.4, com `mod_rewrite` e `mod_headers` |
| MySQL | 8.0 no Docker local, 8.4 em produção (Amazon RDS); InnoDB, `utf8mb4` |
| Acesso ao banco | PDO (`pdo_mysql`) |
| Frontend | HTML, CSS e JavaScript |
| Chart.js | 4.4.7, incluído no repositório |
| Font Awesome Free | 6.7.2, incluído no repositório |
| Docker e Docker Compose | para o ambiente local  |

O projeto não usa Composer nem npm. O autoload é próprio ([autoload.php](autoload.php)): ele mapeia o namespace para o caminho da pasta, no estilo PSR-4. Por exemplo, a classe `Model\Pedido\Pedido` fica em `Model/Pedido/Pedido.php`.

## Instalação e execução local

### Com Docker (recomendado)

Pré-requisitos: Docker e Docker Compose.

1. Crie o arquivo de ambiente a partir do exemplo e troque as senhas:
   ```bash
   cp docker/.env.example docker/.env
   ```
2. Suba os containers:
   ```bash
   docker compose up -d --build
   ```
3. Na primeira subida, com o volume do banco ainda vazio, o MySQL cria as tabelas sozinho a partir do [database/schema.sql](database/schema.sql). Se o volume já existir, o schema não é aplicado de novo.
4. Acesse `http://localhost:8080`.

Para parar os containers, use `docker compose down`. Para apagar também o banco, use `docker compose down -v`.

### Variáveis de ambiente

| Variável | Padrão | Descrição |
|---|---|---|
| `APP_ENV` | `production` | Com `local`, os erros aparecem na tela e as respostas de erro 500 trazem o campo `debug` |
| `DB_HOST` | `localhost` | Host do MySQL (no Docker, `db`) |
| `DB_PORT` | `3306` | Porta do MySQL |
| `DB_NAME` | `preco_certo` | Nome do banco |
| `DB_USER` | `root` | Usuário do banco |
| `DB_PASS` | (vazio) | Senha do banco |

As variáveis `MYSQL_*` do `docker/.env` não são lidas pela aplicação. O container do MySQL usa essas variáveis para criar o banco e o usuário.

## Ambiente de produção

A versão de produção fica na AWS, na região **us-east-2 (Ohio)**. A aplicação roda numa instância EC2, e o banco de dados é uma instância MySQL gerenciada pelo Amazon RDS.

```
Navegador ──HTTP:80──► EC2 (Apache + PHP-FPM) ──MySQL:3306──► RDS (MySQL 8.4)
```

### Servidor da aplicação (Amazon EC2)

| Item | Configuração |
|---|---|
| Tipo de instância | `t3.micro` |
| Zona de disponibilidade | `us-east-2c` |
| Sistema operacional | Amazon Linux 2023 (kernel 6.18) |
| Servidor web | Apache 2.4 (`httpd`) |
| PHP | 8.5, executado via PHP-FPM |

A aplicação lê as variáveis de ambiente (`APP_ENV`, `DB_*`) por `SetEnv` do Apache, definidas em `/etc/httpd/conf.d/`. Esse arquivo fica restrito ao root e não faz parte do repositório.

### Banco de dados (Amazon RDS for MySQL)

| Item | Configuração |
|---|---|
| Mecanismo | MySQL Community 8.4 |
| Classe da instância | `db.t4g.micro` (2 vCPU, 1 GB de RAM, ARM/Graviton) |
| Zona de disponibilidade | `us-east-2c`, a mesma da EC2 (Single-AZ) |
| Armazenamento | 20 GiB SSD `gp2`, com escalonamento automático até 1000 GiB |
| Criptografia em repouso | Habilitada, com chave do AWS KMS (`aws/rds`) |
| Monitoramento | Enhanced Monitoring (granularidade de 60 s) e Database Insights no modo padrão |

A aplicação conecta ao RDS pelo endpoint da instância, informado em `DB_HOST`. O schema é o mesmo do ambiente local ([database/schema.sql](database/schema.sql)). A versão do MySQL é diferente (8.4 em produção, 8.0 no Docker), mas o projeto não usa nenhum recurso que mude entre as duas.

### Deploy

Não há pipeline automatizado. Os arquivos da aplicação são copiados para `/var/www/html` na instância. As pastas `docker/`, `docs/` e os dumps em `database/` não são necessários em produção e não devem ir para o servidor.

### Pontos de atenção da infraestrutura

- **IP público dinâmico:** a instância não tem Elastic IP, então o IP público muda se ela for parada e iniciada de novo. Associe um Elastic IP ou use um domínio apontando para a instância.
- **Sem HTTPS:** o acesso hoje é só por HTTP (porta 80). Com um domínio configurado, dá para emitir um certificado gratuito com Let's Encrypt (`certbot`) e habilitar a porta 443 no Security Group.
- **Proteção contra exclusão do RDS desabilitada:** vale habilitar, para evitar que o banco seja apagado por engano.
- **Single-AZ:** banco e aplicação ficam numa única zona de disponibilidade. Para um sistema deste porte isso é aceitável, mas uma falha na zona derruba os dois.

## Orientações para análise do código

### Estrutura de pastas

```
├── index.php, config.php, autoload.php   ponto de entrada, configuração global e autoload
├── Core/          roteador (URL → controller → método) e montagem das respostas
├── Controller/
│   ├── Api/       endpoints JSON, um controller por recurso
│   └── <Tela>/    controllers que só renderizam a página HTML
├── Model/         entidades, relatórios, enums, exceções, conexão e configuração
├── Utils/         validação de entrada, sessão, formatação, filtros e funções auxiliares
├── Render/        montagem do HTML: template + view + substituição de marcadores
├── Middleware/    base para middlewares dos controllers de tela
├── View/          templates (_painel, _publico, _acesso) e páginas HTML
├── Assets/        CSS, JavaScript por página e bibliotecas de terceiros (vendor)
├── database/      schema e dump de exemplo
├── docker/        Dockerfile, php.ini e variáveis do ambiente local
└── docs/          collection do Postman
```

### Fluxo de uma requisição

1. O [.htaccess](.htaccess) manda qualquer URL que não seja um arquivo para `index.php?pag=<url>`.
2. [Core::autoSetByURL](Core/Core.php) separa a URL em módulo, controller, método e argumentos, convertendo `kebab-case` para `CamelCase`. Por exemplo, `/api/materia-prima` chega a `Controller\Api\MateriaPrimaController`.
3. `Core::checkLogin` separa as rotas públicas, as telas de acesso (entrar e cadastro) e o painel, que exige sessão.
4. O controller executa, e `Core::responder` devolve HTML (quando o retorno é um `Render`) ou JSON.
5. As exceções terminam em `Core::responderErro`: `AppException` vira o status HTTP correspondente, e qualquer outro erro vira 500 com registro no log.

### Telas x API

- Os controllers de tela (`Controller/<Tela>/`) só devolvem a página HTML, montada pelo [Render](Render/Render.php) com um dos templates.
- Os dados chegam pela API: [ApiController::index](Controller/Api/ApiController.php) chama `get()`, `post()`, `put()` ou `delete()` conforme o verbo HTTP, e cada recurso implementa só os verbos que suporta.
- Cada página tem o seu JS em `Assets/js/paginas/*.js`, que consome a API por meio de [Assets/js/api.js](Assets/js/api.js).

### Padrão dos models

- **Hierarquia:** `Conexao` → `Model` → entidade.
  - [Conexao](Model/Conexao/Conexao.php) guarda a conexão PDO estática e implementa `prepare`, `execute`, `fetch`, `fetchAll` e o controle de transações.
  - [Model](Model/Model.php) acrescenta o que as entidades compartilham:
    - `convertData`: converte o tipo de cada coluna conforme a propriedade da entidade (`int`, `float`, `bool`, `array`).
    - `fixGetAll`: listagem paginada com [FiltrosPadrao](Utils/FiltrosPadrao.php) e [Listagem](Model/Sistema/Listagem/Listagem.php).
    - `bindList`: monta `IN (...)` com parâmetros nomeados.
- **Entidades:** cada uma tem propriedades tipadas, setters fluentes e os métodos `get()`, `getAll(FiltrosPadrao)`, `insert()`, `update()` e `delete()`. As exceções seguem as regras do domínio: o produto é desativado em vez de excluído, e o cardápio usa `save()`, que inclui ou atualiza conforme o caso.
- **Isolamento por negócio:** toda consulta filtra pelo `idUsuario`, então um negócio nunca enxerga dados de outro.
- **Por onde começar:**
  1. [MateriaPrima](Model/MateriaPrima/MateriaPrima.php): o caso simples, com o CRUD completo.
  2. [Produto](Model/Produto/Produto.php): composição com transação e cálculo de custos.
  3. [Pedido](Model/Pedido/Pedido.php): o caso mais completo, com cliente, itens, histórico e mudança de situação.

### Regras de negócio que valem a leitura

- **Custo do produto:** [UnidadeMedida::converter](Model/MateriaPrima/UnidadeMedida.php) converte a quantidade da receita para a unidade da embalagem. `Produto::getCustoItemComposicao` e `Produto::fixCustos` calculam o custo, o lucro e a margem.
- **Custo histórico:** o custo unitário é gravado em `item_pedido` no momento da venda. Assim, mudar o preço de uma matéria-prima não altera o lucro de vendas já feitas.
- **Situação do pedido:** a máquina de estados fica em [SituacaoPedido](Model/Pedido/SituacaoPedido.php) e `Pedido::checkMudancaSituacao`. A atualização usa `UPDATE ... AND situacao = :situacaoAtual`: se o pedido foi alterado ao mesmo tempo por outra operação, a requisição responde 409.
- **Estimativa de vendas:** regressão linear simples em [EstimativaVendas](Model/Relatorio/EstimativaVendas.php). Ela só é calculada a partir de dois períodos com vendas concluídas.

### Validação e erros

- A entrada é validada na borda da aplicação, nos controllers da API, por [Utils/Entrada.php](Utils/Entrada.php): tipos, limites, enums, datas e listas.
- Exceções em `Model/Exception/`:
  - `AppException`: erro de negócio, com o status HTTP correspondente.
  - `ValidacaoException`: 422, com o campo que falhou.
  - `EndPointException`: 404.
- Formato da resposta de erro: `{"error": "mensagem", "campo": "nomeDoCampo"}`.

### Segurança

- Todas as queries usam prepared statements com parâmetros nomeados.
- As senhas são guardadas com `password_hash`. O login tem resposta de tempo uniforme mesmo quando o e-mail não existe.
- A sessão usa cookie `HttpOnly` com `SameSite=Lax`, `use_strict_mode`, regeneração do id no login e expiração após 3 horas de inatividade ([Utils/Sessao.php](Utils/Sessao.php)).
- Cabeçalhos de segurança (`nosniff`, `X-Frame-Options`, `Referrer-Policy` e HSTS quando a conexão é HTTPS) são enviados em [config.php](config.php).
- O [.htaccess](.htaccess) bloqueia as pastas `docker`, `database`, `Logs`, `View` e `docs`, os arquivos de configuração, dados e log, e qualquer `.php` além do `index.php`.

### Logs

Os erros vão para `Logs/error.log`, em JSON, com data, usuário e URI. Falhas de SQL também ficam registradas ali: `Conexao::execute` grava o SQL, os parâmetros e a mensagem do PDO, e o model lança uma `AppException` para a resposta.