import "../painel.js";
import { Api } from "../api.js";
import {
    Formato, Formulario, Toast, cartaoIndicador, comCarregamento, escapeHtml, estadoCarregando, estadoVazio,
} from "../ui.js";
import { seloSituacao } from "../situacoes.js";

const POR_PAGINA = 50;

const formulario = document.getElementById("form-filtros");
const lista = document.getElementById("lista");
const resumo = document.getElementById("resumo");
const botaoCarregarMais = document.getElementById("carregar-mais");

let vendas = [];
let totalVendas = 0;
let filtrosAtuais = {};

function linhaVenda(venda) {
    const itens = venda.itens.map((item) => `${item.quantidade}× ${item.nome}`).join(", ");

    return `
        <tr>
            <td data-rotulo="Pedido"><a href="${Api.url(`pedidos/?id=${venda.idPedido}`)}">#${venda.idPedido}</a></td>
            <td data-rotulo="Data" class="numero">${Formato.dataHora(venda.dataPedido)}</td>
            <td data-rotulo="Cliente">${escapeHtml(venda.cliente.nome)}</td>
            <td data-rotulo="Itens" class="texto-pequeno">${escapeHtml(itens)}</td>
            <td data-rotulo="Valor" class="numero">${Formato.moeda(venda.valorItens)}</td>
            <td data-rotulo="Custo" class="numero texto-suave">${Formato.moeda(venda.custo)}</td>
            <td data-rotulo="Lucro" class="numero ${venda.lucro < 0 ? "negativo" : "positivo"}"><strong>${Formato.moeda(venda.lucro)}</strong></td>
            <td data-rotulo="Margem" class="numero">${Formato.percentual(venda.margemLucro)}</td>
            <td data-rotulo="Situação">${seloSituacao(venda.situacao, venda.rotuloSituacao)}</td>
        </tr>`;
}

function renderizar() {
    if (!vendas.length) {
        lista.innerHTML = estadoVazio("fa-magnifying-glass-dollar", "Nenhuma venda encontrada com esses filtros.");
        botaoCarregarMais.hidden = true;
        return;
    }

    lista.innerHTML = `
        <div class="tabela-container">
            <table class="tabela tabela-responsiva">
                <thead>
                    <tr>
                        <th scope="col">Pedido</th>
                        <th scope="col">Data</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Itens</th>
                        <th scope="col">Valor</th>
                        <th scope="col">Custo</th>
                        <th scope="col">Lucro</th>
                        <th scope="col">Margem</th>
                        <th scope="col">Situação</th>
                    </tr>
                </thead>
                <tbody>${vendas.map(linhaVenda).join("")}</tbody>
            </table>
        </div>`;

    botaoCarregarMais.hidden = vendas.length >= totalVendas;
}

function renderizarResumo(dados) {
    resumo.innerHTML = [
        cartaoIndicador({ rotulo: "Pedidos", valor: String(dados.pedidos) }),
        cartaoIndicador({ rotulo: "Faturamento dos itens", valor: Formato.moeda(dados.valorItens), detalhe: `${Formato.moeda(dados.valorTotal)} com taxas de entrega` }),
        cartaoIndicador({ rotulo: "Custo", valor: Formato.moeda(dados.custo) }),
        cartaoIndicador({ rotulo: "Lucro", valor: Formato.moeda(dados.lucro), detalhe: `Margem de ${Formato.percentual(dados.margemLucro)}` }),
    ].join("");
}

async function buscar(offset) {
    return Api.get("historico-vendas", { ...filtrosAtuais, limite: POR_PAGINA, offset });
}

async function carregar() {
    Formulario.limparErros(formulario);
    lista.innerHTML = estadoCarregando();
    filtrosAtuais = Formulario.dados(formulario);

    try {
        const resposta = await buscar(0);
        vendas = resposta.vendas;
        totalVendas = resposta.resumo.pedidos;
        renderizarResumo(resposta.resumo);
        renderizar();
    } catch (erro) {
        lista.innerHTML = "";
        Formulario.tratarErro(formulario, erro);
    }
}

async function carregarMais() {
    try {
        const resposta = await comCarregamento(botaoCarregarMais, () => buscar(vendas.length));
        vendas = [...vendas, ...resposta.vendas];
        renderizar();
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

async function carregarProdutos() {
    try {
        const produtos = await Api.get("produto");
        const seletor = formulario.idProduto;
        seletor.insertAdjacentHTML("beforeend", produtos
            .map((produto) => `<option value="${produto.id}">${escapeHtml(produto.nome)}${produto.ativo ? "" : " (desativado)"}</option>`)
            .join(""));
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

formulario.addEventListener("submit", (evento) => {
    evento.preventDefault();
    carregar();
});

formulario.addEventListener("reset", () => setTimeout(carregar));
botaoCarregarMais.addEventListener("click", carregarMais);

carregarProdutos();
carregar();
