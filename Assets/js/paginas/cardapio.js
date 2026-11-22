import { usuario } from "../painel.js";
import { Api } from "../api.js";
import {
    Formato, Formulario, Toast, comCarregamento, contraste, copiarTexto, corTextoLegivel,
    escapeHtml, estadoVazio, paraTextoDecimal,
} from "../ui.js";

const CONTRASTE_MINIMO = 4.5;
const formulario = document.getElementById("form-cardapio");
const listaProdutos = document.getElementById("lista-produtos");
const listaFormas = document.getElementById("formas-pagamento");
const campoNovaForma = document.getElementById("nova-forma");

const cores = {
    primaria: { seletor: document.getElementById("cor-primaria"), texto: document.getElementById("cor-primaria-texto") },
    secundaria: { seletor: document.getElementById("cor-secundaria"), texto: document.getElementById("cor-secundaria-texto") },
};

let formasPagamento = [];
let produtos = [];

function corValida(valor) {
    return /^#[0-9a-fA-F]{6}$/.test(valor);
}

function renderizarFormas() {
    listaFormas.innerHTML = formasPagamento.map((forma, indice) => `
        <span class="etiqueta">
            ${escapeHtml(forma)}
            <button type="button" data-remover-forma="${indice}" aria-label="Remover ${escapeHtml(forma)}">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </span>`).join("");
}

function adicionarForma() {
    const forma = campoNovaForma.value.trim();

    if (!forma) {
        campoNovaForma.focus();
        return;
    }

    const repetida = formasPagamento.some((item) => item.toLowerCase() === forma.toLowerCase());
    if (!repetida) {
        formasPagamento.push(forma);
        renderizarFormas();
    }

    campoNovaForma.value = "";
    campoNovaForma.focus();
}

function renderizarProdutos(selecionados) {
    if (!produtos.length) {
        listaProdutos.innerHTML = estadoVazio("fa-cookie-bite", "Nenhum produto ativo. Cadastre produtos para exibir no cardápio.");
        atualizarContador();
        return;
    }

    listaProdutos.innerHTML = produtos.map((produto) => `
        <label class="caixa-selecao">
            <input type="checkbox" value="${produto.id}" ${selecionados.includes(produto.id) ? "checked" : ""}>
            <span style="flex: 1;">${escapeHtml(produto.nome)}</span>
            <span class="numero texto-suave">${Formato.moeda(produto.precoVenda)}</span>
        </label>`).join("");

    atualizarContador();
}

function produtosSelecionados() {
    return [...listaProdutos.querySelectorAll("input:checked")].map((caixa) => Number(caixa.value));
}

function atualizarContador() {
    const total = produtosSelecionados().length;
    document.getElementById("contador-produtos").textContent = `${Formato.plural(total, "produto selecionado", "produtos selecionados")} de ${produtos.length}`;
}

function atualizarPrevia() {
    const corPrimaria = corValida(cores.primaria.texto.value) ? cores.primaria.texto.value : "#1F2937";
    const corSecundaria = corValida(cores.secundaria.texto.value) ? cores.secundaria.texto.value : "#F59E0B";
    const textoPrimaria = corTextoLegivel(corPrimaria);
    const textoSecundaria = corTextoLegivel(corSecundaria);

    const topo = document.getElementById("previa-topo");
    topo.style.background = corPrimaria;
    topo.style.color = textoPrimaria;

    const botao = document.getElementById("previa-botao");
    botao.style.background = corSecundaria;
    botao.style.color = textoSecundaria;

    document.getElementById("previa-endereco").textContent = formulario.endereco.value;

    const selecionados = produtos.filter((produto) => produtosSelecionados().includes(produto.id)).slice(0, 3);
    document.getElementById("previa-produtos").innerHTML = selecionados.length
        ? selecionados.map((produto) => `
            <div class="previa-produto"><span>${escapeHtml(produto.nome)}</span><strong>${Formato.moeda(produto.precoVenda)}</strong></div>`).join("")
        : '<div class="texto-suave texto-pequeno">Selecione produtos para vê-los aqui.</div>';

    const avisos = [
        { nome: "cor principal", cor: corPrimaria, texto: textoPrimaria },
        { nome: "cor de destaque", cor: corSecundaria, texto: textoSecundaria },
    ].map(({ nome, cor, texto }) => {
        const razao = contraste(cor, texto);
        const corTexto = texto === "#FFFFFF" ? "branco" : "preto";
        const valor = Formato.numero(razao, 1);

        if (razao >= CONTRASTE_MINIMO) {
            return `<div class="alerta alerta-sucesso"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span>Texto ${corTexto} sobre a ${nome}: contraste ${valor}:1. Boa leitura.</span></div>`;
        }

        return `<div class="alerta alerta-aviso"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>Texto sobre a ${nome} fica com contraste ${valor}:1, abaixo do recomendado (${CONTRASTE_MINIMO}:1). Escolha um tom mais claro ou mais escuro.</span></div>`;
    });

    document.getElementById("avisos-contraste").innerHTML = avisos.join("");
}

function definirCor(chave, valor) {
    cores[chave].texto.value = valor.toUpperCase();

    if (corValida(valor)) {
        cores[chave].seletor.value = valor.toLowerCase();
    }
}

function mostrarLink(linkPublico) {
    if (!linkPublico) {
        return;
    }

    const url = Api.urlAbsoluta(`loja/${linkPublico}`);
    document.getElementById("bloco-link").hidden = false;
    document.getElementById("link-publico").textContent = url;
    document.getElementById("abrir-link").href = url;
    document.getElementById("copiar-link").onclick = () => copiarTexto(url);
}

async function carregar() {
    try {
        const [cardapio, listaAtivos, dadosUsuario] = await Promise.all([
            Api.get("cardapio"),
            Api.get("produto", { ativo: "true" }),
            usuario,
        ]);

        produtos = listaAtivos;
        formasPagamento = [...cardapio.formasPagamento];

        definirCor("primaria", cardapio.corPrimaria);
        definirCor("secundaria", cardapio.corSecundaria);
        formulario.endereco.value = cardapio.endereco ?? "";
        formulario.taxaEntrega.value = paraTextoDecimal(cardapio.taxaEntrega.toFixed(2));
        document.getElementById("previa-nome").textContent = dadosUsuario?.nomeNegocio ?? "Seu negócio";

        renderizarFormas();
        renderizarProdutos(cardapio.produtos);
        mostrarLink(cardapio.linkPublico);
        atualizarPrevia();
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

Object.entries(cores).forEach(([chave, { seletor, texto }]) => {
    seletor.addEventListener("input", () => {
        definirCor(chave, seletor.value);
        atualizarPrevia();
    });

    texto.addEventListener("input", () => {
        if (corValida(texto.value)) {
            seletor.value = texto.value.toLowerCase();
        }
        atualizarPrevia();
    });
});

campoNovaForma.addEventListener("keydown", (evento) => {
    if (evento.key === "Enter") {
        evento.preventDefault();
        adicionarForma();
    }
});

document.getElementById("adicionar-forma").addEventListener("click", adicionarForma);

listaFormas.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-remover-forma]");
    if (!botao) {
        return;
    }

    formasPagamento.splice(Number(botao.dataset.removerForma), 1);
    renderizarFormas();
    campoNovaForma.focus();
});

listaProdutos.addEventListener("change", () => {
    atualizarContador();
    atualizarPrevia();
});

document.getElementById("selecionar-todos").addEventListener("click", () => {
    const caixas = [...listaProdutos.querySelectorAll("input")];
    const marcar = caixas.some((caixa) => !caixa.checked);
    caixas.forEach((caixa) => (caixa.checked = marcar));
    atualizarContador();
    atualizarPrevia();
});

formulario.endereco.addEventListener("input", atualizarPrevia);

formulario.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    Formulario.limparErros(formulario);

    const botao = formulario.querySelector('[type="submit"]');
    const dados = {
        corPrimaria: cores.primaria.texto.value,
        corSecundaria: cores.secundaria.texto.value,
        endereco: formulario.endereco.value,
        formasPagamento,
        taxaEntrega: formulario.taxaEntrega.value || "0",
        produtos: produtosSelecionados(),
    };

    try {
        const cardapio = await comCarregamento(botao, () => Api.put("cardapio", dados));
        mostrarLink(cardapio.linkPublico);
        Toast.sucesso("Cardápio salvo.");
    } catch (erro) {
        Formulario.tratarErro(formulario, erro);
    }
});

carregar();
