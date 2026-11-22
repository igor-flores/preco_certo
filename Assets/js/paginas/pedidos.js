import "../painel.js";
import { Api } from "../api.js";
import {
    Formato, Formulario, Toast, comCarregamento, confirmar, copiarTexto, escapeHtml,
    estadoCarregando, estadoVazio, mascararTelefone, paraNumero, paraTextoDecimal,
} from "../ui.js";
import { proximaSituacao, seloSituacao, situacaoFinal, situacoesPermitidas } from "../situacoes.js";

const POR_PAGINA = 20;
const INTERVALO_ATUALIZACAO = 30000;

const ROTULOS_ACAO = {
    em_producao: "Iniciar produção",
    pronto_retirada: "Pronto para retirada",
    em_rota_entrega: "Saiu para entrega",
    concluido: "Concluir",
};

const lista = document.getElementById("lista");
const filtroSituacao = document.getElementById("filtro-situacao");
const filtroOrigem = document.getElementById("filtro-origem");
const botaoCarregarMais = document.getElementById("carregar-mais");
const statusAtualizacao = document.getElementById("status-atualizacao");
const dialogoDetalhe = document.getElementById("dialogo-detalhe");
const formSituacao = document.getElementById("form-situacao");
const dialogoNovo = document.getElementById("dialogo-novo");
const formNovo = document.getElementById("form-novo");
const linhasItens = document.getElementById("linhas-itens");

let pedidos = [];
let totalPedidos = 0;
let situacaoSelecionada = "";
let pedidoAberto = null;
let produtosAtivos = [];
let taxaPadrao = 0;
let contadorItens = 0;

function descricaoItens(itens) {
    return itens.map((item) => `${item.quantidade}× ${item.nome}`).join(", ");
}

function cartaoPedido(pedido) {
    const proxima = proximaSituacao(pedido.situacao, pedido.tipoEntrega);
    const entrega = pedido.tipoEntrega === "entrega"
        ? '<i class="fa-solid fa-motorcycle" aria-hidden="true"></i> Entrega'
        : '<i class="fa-solid fa-store" aria-hidden="true"></i> Retirada';
    const origem = pedido.origem === "manual" ? "Manual" : "Cardápio online";

    return `
        <article class="pedido-cartao" aria-labelledby="pedido-${pedido.id}">
            <div class="pilha" style="gap: 0.35rem;">
                <div class="titulo">
                    <span id="pedido-${pedido.id}">#${pedido.id} · ${escapeHtml(pedido.cliente.nome)}</span>
                    ${seloSituacao(pedido.situacao, pedido.rotuloSituacao)}
                </div>
                <div class="linha texto-suave texto-pequeno" style="gap: 0.4rem 1rem;">
                    <span><i class="fa-regular fa-clock" aria-hidden="true"></i> ${Formato.dataHora(pedido.dataPedido)}</span>
                    <span>${entrega}</span>
                    <span><i class="fa-solid fa-wallet" aria-hidden="true"></i> ${escapeHtml(pedido.formaPagamento)}</span>
                    <span>${origem}</span>
                </div>
                <div class="itens">${escapeHtml(descricaoItens(pedido.itens))}</div>
            </div>
            <div class="total numero">${Formato.moeda(pedido.valorTotal)}</div>
            <div class="acoes-pedido">
                <button type="button" class="botao botao-secundario botao-pequeno" data-acao="detalhes" data-id="${pedido.id}">
                    <i class="fa-solid fa-eye" aria-hidden="true"></i>Detalhes
                </button>
                ${proxima ? `
                <button type="button" class="botao botao-pequeno" data-acao="avancar" data-id="${pedido.id}" data-situacao="${proxima.valor}">
                    <i class="fa-solid ${proxima.icone}" aria-hidden="true"></i>${ROTULOS_ACAO[proxima.valor]}
                </button>` : ""}
            </div>
        </article>`;
}

function renderizar() {
    if (!pedidos.length) {
        lista.innerHTML = estadoVazio("fa-receipt", situacaoSelecionada || filtroOrigem.value
            ? "Nenhum pedido com esses filtros."
            : "Nenhum pedido ainda. Compartilhe o link do cardápio ou registre um pedido manual.");
        botaoCarregarMais.hidden = true;
        return;
    }

    lista.innerHTML = pedidos.map(cartaoPedido).join("");
    botaoCarregarMais.hidden = pedidos.length >= totalPedidos;
}

async function carregar({ manterQuantidade = false, silencioso = false } = {}) {
    if (!silencioso) {
        lista.innerHTML = estadoCarregando();
    }

    const limite = manterQuantidade ? Math.min(100, Math.max(POR_PAGINA, pedidos.length)) : POR_PAGINA;

    try {
        const resposta = await Api.get("pedido", {
            situacao: situacaoSelecionada,
            origem: filtroOrigem.value,
            limite,
            offset: 0,
        });

        pedidos = resposta.pedidos;
        totalPedidos = resposta.total;
        renderizar();
        statusAtualizacao.textContent = `Atualizado às ${new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}`;
    } catch (erro) {
        if (!silencioso) {
            lista.innerHTML = estadoVazio("fa-triangle-exclamation", erro.message);
        }
    }
}

async function carregarMais() {
    try {
        const resposta = await comCarregamento(botaoCarregarMais, () => Api.get("pedido", {
            situacao: situacaoSelecionada,
            origem: filtroOrigem.value,
            limite: POR_PAGINA,
            offset: pedidos.length,
        }));

        pedidos = [...pedidos, ...resposta.pedidos];
        totalPedidos = resposta.total;
        renderizar();
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

async function alterarSituacao(id, situacao) {
    if (situacao === "cancelado") {
        const confirmado = await confirmar("Cancelar este pedido? Depois de cancelado ele não pode mais mudar de situação.", {
            titulo: "Cancelar pedido",
            textoConfirmar: "Cancelar pedido",
            perigo: true,
        });

        if (!confirmado) {
            return null;
        }
    }

    const pedido = await Api.put("pedido", { situacao }, { id });
    Toast.sucesso(`Pedido #${id}: ${pedido.rotuloSituacao}.`);
    carregar({ manterQuantidade: true, silencioso: true });

    return pedido;
}

function linkWhatsApp(telefone) {
    const numeros = telefone.replace(/\D/g, "");
    const comPais = numeros.length <= 11 ? `55${numeros}` : numeros;

    return `https://wa.me/${comPais}`;
}

function renderizarDetalhe(pedido) {
    pedidoAberto = pedido;
    const urlAcompanhamento = Api.urlAbsoluta(`acompanhar/${pedido.hashAcompanhamento}`);

    document.getElementById("titulo-detalhe").innerHTML = `Pedido #${pedido.id} ${seloSituacao(pedido.situacao, pedido.rotuloSituacao)}`;

    const itens = pedido.itens.map((item) => `
        <tr>
            <td data-rotulo="Produto">${escapeHtml(item.nome)}</td>
            <td data-rotulo="Qtd." class="numero">${item.quantidade}</td>
            <td data-rotulo="Preço unit." class="numero">${Formato.moeda(item.precoUnitario)}</td>
            <td data-rotulo="Subtotal" class="numero">${Formato.moeda(item.subtotal)}</td>
            <td data-rotulo="Custo" class="numero texto-suave">${Formato.moeda(item.custoTotal)}</td>
        </tr>`).join("");

    const historico = pedido.historico.map((registro) => `
        <li><span>${escapeHtml(registro.rotulo)}</span><span class="texto-suave numero">${Formato.dataHora(registro.dataHora)}</span></li>`).join("");

    document.getElementById("detalhe-corpo").innerHTML = `
        <div class="pilha">
            <div class="duas-colunas" style="gap: 1rem;">
                <div class="pilha" style="gap: 0.3rem;">
                    <h3>Cliente</h3>
                    <span>${escapeHtml(pedido.cliente.nome)}</span>
                    <span class="linha" style="gap: 0.5rem;">
                        ${escapeHtml(Formato.telefone(pedido.cliente.telefone))}
                        <a href="${linkWhatsApp(pedido.cliente.telefone)}" target="_blank" rel="noopener" class="texto-pequeno">
                            <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp
                        </a>
                    </span>
                    ${pedido.tipoEntrega === "entrega" ? `<span class="texto-suave">${escapeHtml(pedido.cliente.endereco ?? "")}</span>` : ""}
                </div>
                <div class="pilha" style="gap: 0.3rem;">
                    <h3>Pedido</h3>
                    <span>${pedido.tipoEntrega === "entrega" ? "Entrega" : "Retirada no local"} · ${escapeHtml(pedido.formaPagamento)}</span>
                    <span class="texto-suave">Feito em ${Formato.dataHora(pedido.dataPedido)} · ${pedido.origem === "manual" ? "Manual" : "Cardápio online"}</span>
                </div>
            </div>

            <div class="tabela-container">
                <table class="tabela tabela-responsiva">
                    <caption class="visualmente-oculto">Itens do pedido</caption>
                    <thead><tr><th scope="col">Produto</th><th scope="col">Qtd.</th><th scope="col">Preço unit.</th><th scope="col">Subtotal</th><th scope="col">Custo</th></tr></thead>
                    <tbody>${itens}</tbody>
                </table>
            </div>

            <div class="resumo-custo">
                <div><span>Itens</span><strong>${Formato.moeda(pedido.valorItens)}</strong></div>
                <div><span>Taxa de entrega</span><strong>${Formato.moeda(pedido.taxaEntrega)}</strong></div>
                <div><span>Total</span><strong>${Formato.moeda(pedido.valorTotal)}</strong></div>
                <div><span>Custo</span><strong>${Formato.moeda(pedido.custo)}</strong></div>
                <div><span>Lucro (${Formato.percentual(pedido.margemLucro)})</span><strong class="${pedido.lucro < 0 ? "negativo" : ""}">${Formato.moeda(pedido.lucro)}</strong></div>
            </div>

            <div class="duas-colunas" style="gap: 1rem;">
                <div class="pilha" style="gap: 0.5rem;">
                    <h3>Histórico</h3>
                    <ol class="linha-tempo">${historico}</ol>
                </div>
                <div class="pilha" style="gap: 0.5rem;">
                    <h3>Link de acompanhamento</h3>
                    <p class="texto-suave texto-pequeno">Envie ao cliente para ele acompanhar o pedido.</p>
                    <div class="linha">
                        <button type="button" class="botao botao-secundario botao-pequeno" id="copiar-acompanhamento">
                            <i class="fa-solid fa-copy" aria-hidden="true"></i>Copiar link
                        </button>
                        <a class="botao botao-secundario botao-pequeno" href="${urlAcompanhamento}" target="_blank" rel="noopener">
                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>Abrir
                        </a>
                    </div>
                </div>
            </div>
        </div>`;

    document.getElementById("copiar-acompanhamento").addEventListener("click", () => copiarTexto(urlAcompanhamento));

    const permitidas = situacoesPermitidas(pedido.situacao, pedido.tipoEntrega);
    formSituacao.hidden = situacaoFinal(pedido.situacao);
    formSituacao.situacao.innerHTML = permitidas.map((situacao) => `<option value="${situacao.valor}">${situacao.rotulo}</option>`).join("");

    const sugerida = proximaSituacao(pedido.situacao, pedido.tipoEntrega);
    if (sugerida) {
        formSituacao.situacao.value = sugerida.valor;
    }
}

async function abrirDetalhe(id) {
    try {
        const pedido = await Api.get("pedido", { id });
        renderizarDetalhe(pedido);

        if (!dialogoDetalhe.open) {
            dialogoDetalhe.showModal();
        }
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

function opcoesProdutos(selecionado) {
    return `<option value="">Selecione…</option>${produtosAtivos.map((produto) => `
        <option value="${produto.id}" ${produto.id === Number(selecionado) ? "selected" : ""}>
            ${escapeHtml(produto.nome)} — ${Formato.moeda(produto.precoVenda)}
        </option>`).join("")}`;
}

function adicionarItem() {
    contadorItens += 1;
    const numero = contadorItens;
    const linha = document.createElement("div");

    linha.className = "linha-item pedido";
    linha.innerHTML = `
        <div class="campo">
            <label for="item-produto-${numero}">Produto</label>
            <select id="item-produto-${numero}" class="item-produto">${opcoesProdutos()}</select>
        </div>
        <div class="campo">
            <label for="item-quantidade-${numero}">Quantidade</label>
            <input id="item-quantidade-${numero}" class="item-quantidade" type="number" min="1" max="999" step="1" value="1">
        </div>
        <button type="button" class="botao botao-icone" data-remover aria-label="Remover item">
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
        </button>`;

    linhasItens.append(linha);
    return linha;
}

function tipoEntregaSelecionado() {
    return formNovo.querySelector('[name="tipoEntrega"]:checked').value;
}

function atualizarTotalNovo() {
    const totalItens = [...linhasItens.children].reduce((total, linha) => {
        const produto = produtosAtivos.find((item) => item.id === Number(linha.querySelector(".item-produto").value));
        const quantidade = Math.max(0, Math.floor(Number(linha.querySelector(".item-quantidade").value) || 0));

        return total + (produto ? produto.precoVenda * quantidade : 0);
    }, 0);

    const entrega = tipoEntregaSelecionado() === "entrega";
    const taxa = entrega ? paraNumero(formNovo.taxaEntrega.value) : 0;

    document.getElementById("campos-entrega").hidden = !entrega;
    document.getElementById("novo-total-itens").textContent = Formato.moeda(totalItens);
    document.getElementById("novo-total-taxa").textContent = Formato.moeda(taxa);
    document.getElementById("novo-total").textContent = Formato.moeda(totalItens + taxa);
}

async function abrirNovo() {
    formNovo.reset();
    Formulario.limparErros(formNovo);
    linhasItens.innerHTML = "";

    try {
        const [produtos, cardapio] = await Promise.all([Api.get("produto", { ativo: "true" }), Api.get("cardapio")]);
        produtosAtivos = produtos;
        taxaPadrao = cardapio.taxaEntrega;
        document.getElementById("formas-pagamento").innerHTML = cardapio.formasPagamento
            .map((forma) => `<option value="${escapeHtml(forma)}"></option>`).join("");
    } catch (erro) {
        Toast.erro(erro.message);
        return;
    }

    if (!produtosAtivos.length) {
        Toast.erro("Cadastre ao menos um produto ativo antes de registrar pedidos.");
        return;
    }

    formNovo.taxaEntrega.value = paraTextoDecimal(taxaPadrao.toFixed(2));
    formNovo.dataPedido.max = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    adicionarItem();
    atualizarTotalNovo();
    dialogoNovo.showModal();
    formNovo.nome.focus();
}

function coletarItens() {
    return [...linhasItens.children].map((linha, indice) => {
        linha.querySelector(".item-produto").dataset.campo = `itens[${indice}].idProduto`;
        linha.querySelector(".item-quantidade").dataset.campo = `itens[${indice}].quantidade`;

        return {
            idProduto: linha.querySelector(".item-produto").value,
            quantidade: linha.querySelector(".item-quantidade").value,
        };
    });
}

formNovo.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    Formulario.limparErros(formNovo);

    const entrega = tipoEntregaSelecionado() === "entrega";
    const dados = {
        nome: formNovo.nome.value,
        telefone: formNovo.telefone.value,
        tipoEntrega: tipoEntregaSelecionado(),
        endereco: entrega ? formNovo.endereco.value : "",
        taxaEntrega: entrega ? formNovo.taxaEntrega.value || "0" : "",
        formaPagamento: formNovo.formaPagamento.value,
        situacao: formNovo.situacao.value,
        dataPedido: formNovo.dataPedido.value,
        itens: coletarItens(),
    };

    try {
        const pedido = await comCarregamento(formNovo.querySelector('[type="submit"]'), () => Api.post("pedido", dados));
        dialogoNovo.close();
        Toast.sucesso(`Pedido #${pedido.id} registrado.`);
        carregar();
    } catch (erro) {
        Formulario.tratarErro(formNovo, erro);
    }
});

formSituacao.addEventListener("submit", async (evento) => {
    evento.preventDefault();

    if (!pedidoAberto) {
        return;
    }

    try {
        const pedido = await comCarregamento(formSituacao.querySelector('[type="submit"]'), () => alterarSituacao(pedidoAberto.id, formSituacao.situacao.value));
        if (pedido) {
            renderizarDetalhe(pedido);
        }
    } catch (erro) {
        Toast.erro(erro.message);
    }
});

lista.addEventListener("click", async (evento) => {
    const botao = evento.target.closest("[data-acao]");
    if (!botao) {
        return;
    }

    const id = Number(botao.dataset.id);

    if (botao.dataset.acao === "detalhes") {
        abrirDetalhe(id);
        return;
    }

    try {
        await comCarregamento(botao, () => alterarSituacao(id, botao.dataset.situacao));
    } catch (erro) {
        Toast.erro(erro.message);
    }
});

linhasItens.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-remover]");
    if (!botao) {
        return;
    }

    botao.closest(".linha-item").remove();
    atualizarTotalNovo();
    document.getElementById("adicionar-item").focus();
});

formNovo.addEventListener("input", atualizarTotalNovo);
formNovo.addEventListener("change", atualizarTotalNovo);

document.getElementById("adicionar-item").addEventListener("click", () => {
    adicionarItem().querySelector("select").focus();
    atualizarTotalNovo();
});

filtroSituacao.addEventListener("click", (evento) => {
    const botao = evento.target.closest("button");
    if (!botao) {
        return;
    }

    filtroSituacao.querySelectorAll("button").forEach((item) => item.setAttribute("aria-pressed", String(item === botao)));
    situacaoSelecionada = botao.dataset.situacao;
    carregar();
});

filtroOrigem.addEventListener("change", () => carregar());
botaoCarregarMais.addEventListener("click", carregarMais);
document.getElementById("botao-atualizar").addEventListener("click", () => carregar({ manterQuantidade: true }));
document.getElementById("botao-novo").addEventListener("click", abrirNovo);
mascararTelefone(formNovo.telefone);

setInterval(() => {
    const dialogoAberto = dialogoDetalhe.open || dialogoNovo.open;

    if (!dialogoAberto && document.visibilityState === "visible") {
        carregar({ manterQuantidade: true, silencioso: true });
    }
}, INTERVALO_ATUALIZACAO);

const parametros = new URLSearchParams(window.location.search);
carregar();

if (parametros.has("id")) {
    abrirDetalhe(Number(parametros.get("id")));
}

if (parametros.has("novo")) {
    abrirNovo();
}
