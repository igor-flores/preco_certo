import { Api } from "../api.js";
import { Formato, Formulario, comCarregamento, escapeHtml, estadoVazio, mascararTelefone } from "../ui.js";

const link = window.location.pathname.split("/").filter(Boolean).pop() ?? "";
const chaveCarrinho = `carrinho-${link}`;

const formulario = document.getElementById("form-dados");
const listaProdutos = document.getElementById("produtos");
const barraCarrinho = document.getElementById("barra-carrinho");

let cardapio = null;
let carrinho = new Map();
let etapaAtual = 1;

function lerCarrinhoSalvo() {
    try {
        const salvo = JSON.parse(sessionStorage.getItem(chaveCarrinho) ?? "[]");
        return new Map(salvo);
    } catch {
        return new Map();
    }
}

function salvarCarrinho() {
    try {
        sessionStorage.setItem(chaveCarrinho, JSON.stringify([...carrinho]));
    } catch {
        return;
    }
}

function limparCarrinhoSalvo() {
    try {
        sessionStorage.removeItem(chaveCarrinho);
    } catch {
        return;
    }
}

function aplicarCores() {
    const estilo = document.body.style;
    estilo.setProperty("--loja-primaria", cardapio.corPrimaria);
    estilo.setProperty("--loja-texto-primaria", cardapio.corTextoPrimaria);
    estilo.setProperty("--loja-secundaria", cardapio.corSecundaria);
    estilo.setProperty("--loja-texto-secundaria", cardapio.corTextoSecundaria);

    const meta = document.createElement("meta");
    meta.name = "theme-color";
    meta.content = cardapio.corPrimaria;
    document.head.append(meta);
}

function renderizarCabecalho() {
    document.title = `${cardapio.nomeNegocio} · Cardápio`;
    document.getElementById("nome-negocio").textContent = cardapio.nomeNegocio;

    const informacoes = [];
    if (cardapio.endereco) {
        informacoes.push(`<span><i class="fa-solid fa-location-dot" aria-hidden="true"></i>${escapeHtml(cardapio.endereco)}</span>`);
    }

    informacoes.push(`<span><i class="fa-solid fa-motorcycle" aria-hidden="true"></i>Entrega: ${cardapio.taxaEntrega > 0 ? Formato.moeda(cardapio.taxaEntrega) : "grátis"}</span>`);

    if (cardapio.formasPagamento.length) {
        informacoes.push(`<span><i class="fa-solid fa-wallet" aria-hidden="true"></i>${escapeHtml(cardapio.formasPagamento.join(", "))}</span>`);
    }

    document.getElementById("loja-info").innerHTML = informacoes.join("");
    document.getElementById("rotulo-entrega").textContent = cardapio.taxaEntrega > 0
        ? `Entrega (+ ${Formato.moeda(cardapio.taxaEntrega)})`
        : "Entrega grátis";
}

function renderizarProdutos() {
    if (!cardapio.produtos.length) {
        listaProdutos.innerHTML = estadoVazio("fa-store-slash", "Este cardápio ainda não tem produtos disponíveis.");
        return;
    }

    listaProdutos.innerHTML = cardapio.produtos.map((produto) => {
        const quantidade = carrinho.get(produto.id) ?? 0;

        return `
            <article class="produto-loja ${quantidade ? "selecionado" : ""}" data-id="${produto.id}" aria-labelledby="produto-${produto.id}">
                <div>
                    <h3 id="produto-${produto.id}">${escapeHtml(produto.nome)}</h3>
                    ${produto.descricao ? `<p class="descricao">${escapeHtml(produto.descricao)}</p>` : ""}
                    <p class="preco">${Formato.moeda(produto.preco)}</p>
                </div>
                <div class="quantidade" role="group" aria-label="Quantidade de ${escapeHtml(produto.nome)}">
                    <button type="button" data-alterar="-1" aria-label="Remover um ${escapeHtml(produto.nome)}" ${quantidade ? "" : "disabled"}>
                        <i class="fa-solid fa-minus" aria-hidden="true"></i>
                    </button>
                    <output aria-live="polite">${quantidade}</output>
                    <button type="button" data-alterar="1" aria-label="Adicionar um ${escapeHtml(produto.nome)}" ${quantidade >= 999 ? "disabled" : ""}>
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    </button>
                </div>
            </article>`;
    }).join("");
}

function itensDoCarrinho() {
    return cardapio.produtos
        .filter((produto) => carrinho.get(produto.id))
        .map((produto) => ({ ...produto, quantidade: carrinho.get(produto.id) }));
}

function tipoEntrega() {
    return formulario.querySelector('[name="tipoEntrega"]:checked').value;
}

function totais() {
    const itens = itensDoCarrinho();
    const valorItens = itens.reduce((total, item) => total + item.preco * item.quantidade, 0);
    const taxa = tipoEntrega() === "entrega" ? cardapio.taxaEntrega : 0;
    const quantidade = itens.reduce((total, item) => total + item.quantidade, 0);

    return { itens, valorItens, taxa, total: valorItens + taxa, quantidade };
}

function atualizarCarrinho() {
    const { valorItens, quantidade } = totais();

    barraCarrinho.hidden = etapaAtual !== 1 || quantidade === 0;
    document.getElementById("carrinho-quantidade").textContent = Formato.plural(quantidade, "item", "itens");
    document.getElementById("carrinho-total").textContent = Formato.moeda(valorItens);
}

function irParaEtapa(etapa) {
    etapaAtual = etapa;

    [1, 2, 3].forEach((numero) => {
        document.getElementById(`etapa-${numero}`).hidden = numero !== etapa;

        const indicador = document.querySelector(`#etapas [data-etapa="${numero}"]`);
        indicador.classList.toggle("feita", numero < etapa);

        if (numero === etapa) {
            indicador.setAttribute("aria-current", "step");
            return;
        }

        indicador.removeAttribute("aria-current");
    });

    atualizarCarrinho();
    window.scrollTo({ top: 0, behavior: "smooth" });
    document.getElementById(`titulo-etapa-${etapa}`).focus({ preventScroll: true });
}

function formaPagamentoInformada() {
    if (cardapio.formasPagamento.length) {
        return formulario.querySelector('[name="formaPagamento"]:checked')?.value ?? "";
    }

    return document.getElementById("cliente-pagamento").value.trim();
}

function configurarFormulario() {
    const temFormas = cardapio.formasPagamento.length > 0;
    document.getElementById("campo-pagamento-opcoes").hidden = !temFormas;
    document.getElementById("campo-pagamento-texto").hidden = temFormas;

    if (temFormas) {
        document.getElementById("opcoes-pagamento").innerHTML = cardapio.formasPagamento.map((forma, indice) => `
            <label class="opcao-radio">
                <input type="radio" name="formaPagamento" value="${escapeHtml(forma)}" ${indice === 0 ? "checked" : ""}>
                <span>${escapeHtml(forma)}</span>
            </label>`).join("");
        document.getElementById("opcoes-pagamento").dataset.campo = "formaPagamento";
        return;
    }

    document.getElementById("cliente-pagamento").dataset.campo = "formaPagamento";
}

function validarDados() {
    Formulario.limparErros(formulario);

    const telefone = formulario.telefone.value.replace(/\D/g, "");
    const verificacoes = [
        [!formulario.nome.value.trim(), "nome", "Informe seu nome."],
        [telefone.length < 10 || telefone.length > 13, "telefone", "Informe um telefone com DDD, ex.: (51) 99999-9999."],
        [tipoEntrega() === "entrega" && !formulario.endereco.value.trim(), "endereco", "Informe o endereço de entrega."],
        [!formaPagamentoInformada(), "formaPagamento", "Escolha a forma de pagamento."],
    ];

    const erro = verificacoes.find(([invalido]) => invalido);
    if (erro) {
        Formulario.mostrarErro(formulario, erro[1], erro[2]);
        return false;
    }

    return true;
}

function renderizarConfirmacao() {
    const { itens, valorItens, taxa, total } = totais();
    const entrega = tipoEntrega() === "entrega";

    document.getElementById("resumo-itens").innerHTML = `
        ${itens.map((item) => `
            <li><span>${item.quantidade}× ${escapeHtml(item.nome)}</span><span class="numero">${Formato.moeda(item.preco * item.quantidade)}</span></li>`).join("")}
        <li class="texto-suave"><span>Itens</span><span class="numero">${Formato.moeda(valorItens)}</span></li>
        ${entrega ? `<li class="texto-suave"><span>Taxa de entrega</span><span class="numero">${taxa > 0 ? Formato.moeda(taxa) : "Grátis"}</span></li>` : ""}
        <li class="total"><span>Total</span><span class="numero">${Formato.moeda(total)}</span></li>`;

    const dados = [
        ["Nome", formulario.nome.value.trim()],
        ["Telefone", formulario.telefone.value],
        ["Recebimento", entrega ? "Entrega" : "Retirada no local"],
        ...(entrega ? [["Endereço", formulario.endereco.value.trim()]] : []),
        ["Pagamento", formaPagamentoInformada()],
    ];

    document.getElementById("resumo-dados").innerHTML = dados
        .map(([rotulo, valor]) => `<dt>${rotulo}</dt><dd>${escapeHtml(valor)}</dd>`).join("");
    document.getElementById("erro-envio").hidden = true;
}

async function confirmarPedido(botao) {
    const entrega = tipoEntrega() === "entrega";
    const dados = {
        nome: formulario.nome.value,
        telefone: formulario.telefone.value,
        tipoEntrega: tipoEntrega(),
        endereco: entrega ? formulario.endereco.value : "",
        formaPagamento: formaPagamentoInformada(),
        itens: itensDoCarrinho().map((item) => ({ idProduto: item.id, quantidade: item.quantidade })),
    };

    try {
        const pedido = await comCarregamento(botao, () => Api.post("pedido-publico", dados, { link }));

        limparCarrinhoSalvo();
        window.location.href = Api.url(`acompanhar/${pedido.hashAcompanhamento}`);
    } catch (erro) {
        const camposDados = ["nome", "telefone", "endereco", "formaPagamento", "tipoEntrega"];

        if (erro.campo && camposDados.includes(erro.campo)) {
            irParaEtapa(2);
            Formulario.mostrarErro(formulario, erro.campo, erro.message);
            return;
        }

        const elementoErro = document.getElementById("erro-envio");
        elementoErro.textContent = erro.campo?.startsWith("itens")
            ? `${erro.message} Volte ao cardápio e ajuste os itens.`
            : erro.message;
        elementoErro.hidden = false;
    }
}

async function carregar() {
    try {
        cardapio = await Api.get("cardapio-publico", { link });
    } catch (erro) {
        document.getElementById("nome-negocio").textContent = "Cardápio indisponível";
        const elementoErro = document.getElementById("erro-carregamento");
        elementoErro.innerHTML = estadoVazio("fa-store-slash", erro.status === 404
            ? "Não encontramos este cardápio. Confira o link com o estabelecimento."
            : erro.message);
        elementoErro.hidden = false;
        return;
    }

    const idsDisponiveis = new Set(cardapio.produtos.map((produto) => produto.id));
    carrinho = new Map([...lerCarrinhoSalvo()].filter(([id, quantidade]) => idsDisponiveis.has(id) && quantidade > 0));

    aplicarCores();
    renderizarCabecalho();
    configurarFormulario();
    renderizarProdutos();
    document.getElementById("area-pedido").hidden = false;
    atualizarCarrinho();
}

listaProdutos.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-alterar]");
    if (!botao) {
        return;
    }

    const id = Number(botao.closest("[data-id]").dataset.id);
    const novaQuantidade = Math.min(999, Math.max(0, (carrinho.get(id) ?? 0) + Number(botao.dataset.alterar)));

    if (novaQuantidade === 0) {
        carrinho.delete(id);
    } else {
        carrinho.set(id, novaQuantidade);
    }

    salvarCarrinho();
    renderizarProdutos();
    atualizarCarrinho();
    const mesmoBotao = listaProdutos.querySelector(`[data-id="${id}"] [data-alterar="${botao.dataset.alterar}"]:not(:disabled)`);
    const botaoAdicionar = listaProdutos.querySelector(`[data-id="${id}"] [data-alterar="1"]`);
    (mesmoBotao ?? botaoAdicionar)?.focus();
});

document.getElementById("avancar-dados").addEventListener("click", () => irParaEtapa(2));

document.querySelectorAll("[data-ir-etapa]").forEach((botao) => {
    botao.addEventListener("click", () => irParaEtapa(Number(botao.dataset.irEtapa)));
});

formulario.addEventListener("change", (evento) => {
    if (evento.target.name === "tipoEntrega") {
        document.getElementById("campo-endereco").hidden = tipoEntrega() !== "entrega";
    }
});

formulario.addEventListener("submit", (evento) => {
    evento.preventDefault();

    if (!totais().quantidade) {
        irParaEtapa(1);
        return;
    }

    if (!validarDados()) {
        return;
    }

    renderizarConfirmacao();
    irParaEtapa(3);
});

document.getElementById("confirmar-pedido").addEventListener("click", (evento) => confirmarPedido(evento.currentTarget));
mascararTelefone(formulario.telefone);

carregar();
