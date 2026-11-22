import "../painel.js";
import { Api } from "../api.js";
import {
    Formato, Formulario, Toast, comCarregamento, confirmar, debounce, escapeHtml,
    estadoCarregando, estadoVazio, paraNumero, paraTextoDecimal,
} from "../ui.js";
import { converter, opcoesUnidades, unidadesCompativeis } from "../unidades.js";

const lista = document.getElementById("lista");
const busca = document.getElementById("busca");
const filtroAtivo = document.getElementById("filtro-ativo");
const dialogo = document.getElementById("dialogo-produto");
const formulario = document.getElementById("form-produto");
const linhasMaterias = document.getElementById("linhas-materias");
const campoAtivo = document.getElementById("campo-ativo");
const avisoSemMaterias = document.getElementById("aviso-sem-materias");

let produtos = [];
let materiasPrimas = [];
let idEmEdicao = null;
let contadorLinhas = 0;
let ativoSelecionado = "true";

async function carregar() {
    lista.innerHTML = estadoCarregando();

    try {
        produtos = await Api.get("produto", { ativo: ativoSelecionado, busca: busca.value.trim() });
        renderizar();
    } catch (erro) {
        lista.innerHTML = estadoVazio("fa-triangle-exclamation", erro.message);
    }
}

function classeMargem(margem) {
    if (margem < 0) {
        return "negativo";
    }

    return margem >= 30 ? "positivo" : "";
}

function renderizar() {
    if (!produtos.length) {
        const mensagem = busca.value.trim() || ativoSelecionado !== "true"
            ? "Nenhum produto encontrado com esses filtros."
            : "Nenhum produto cadastrado. Cadastre o primeiro produto e descubra o preço certo.";
        lista.innerHTML = estadoVazio("fa-cookie-bite", mensagem);
        return;
    }

    const linhas = produtos.map((produto) => `
        <tr class="${produto.ativo ? "" : "inativo"}">
            <td data-rotulo="Produto">
                <strong>${escapeHtml(produto.nome)}</strong>
                ${produto.descricao ? `<div class="texto-suave texto-pequeno">${escapeHtml(produto.descricao)}</div>` : ""}
            </td>
            <td data-rotulo="Preço de venda" class="numero">${Formato.moeda(produto.precoVenda)}</td>
            <td data-rotulo="Custo unitário" class="numero">${Formato.moeda(produto.custoUnitario)}</td>
            <td data-rotulo="Lucro unitário" class="numero ${produto.lucroUnitario < 0 ? "negativo" : ""}">${Formato.moeda(produto.lucroUnitario)}</td>
            <td data-rotulo="Margem" class="numero ${classeMargem(produto.margemLucro)}"><strong>${Formato.percentual(produto.margemLucro)}</strong></td>
            <td data-rotulo="Situação">
                <span class="selo ${produto.ativo ? "selo-ativo" : "selo-inativo"}">${produto.ativo ? "Ativo" : "Desativado"}</span>
            </td>
            <td class="acoes">
                <button type="button" class="botao botao-icone" data-acao="editar" data-id="${produto.id}" aria-label="Editar ${escapeHtml(produto.nome)}">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </button>
                ${produto.ativo
                    ? `<button type="button" class="botao botao-icone" data-acao="desativar" data-id="${produto.id}" aria-label="Desativar ${escapeHtml(produto.nome)}">
                        <i class="fa-solid fa-eye-slash" aria-hidden="true"></i></button>`
                    : `<button type="button" class="botao botao-icone" data-acao="reativar" data-id="${produto.id}" aria-label="Reativar ${escapeHtml(produto.nome)}">
                        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button>`}
            </td>
        </tr>`).join("");

    lista.innerHTML = `
        <div class="tabela-container">
            <table class="tabela tabela-responsiva">
                <thead>
                    <tr>
                        <th scope="col">Produto</th>
                        <th scope="col">Preço de venda</th>
                        <th scope="col">Custo unitário</th>
                        <th scope="col">Lucro unitário</th>
                        <th scope="col">Margem</th>
                        <th scope="col">Situação</th>
                        <th scope="col"><span class="visualmente-oculto">Ações</span></th>
                    </tr>
                </thead>
                <tbody>${linhas}</tbody>
            </table>
        </div>`;
}

function buscarMateriaPrima(id) {
    return materiasPrimas.find((materia) => materia.id === Number(id));
}

function opcoesMateriasPrimas(selecionada) {
    const opcoes = materiasPrimas.map((materia) => {
        const embalagem = `${Formato.numero(materia.quantidadeEmbalagem, 3)} ${materia.unidadeMedida}`;
        const marcado = materia.id === Number(selecionada) ? "selected" : "";

        return `<option value="${materia.id}" ${marcado}>${escapeHtml(materia.nome)} — ${embalagem} por ${Formato.moeda(materia.precoEmbalagem)}</option>`;
    });

    return `<option value="">Selecione…</option>${opcoes.join("")}`;
}

function atualizarUnidadesDaLinha(linha, unidadeDesejada = null) {
    const materia = buscarMateriaPrima(linha.querySelector(".mp-select").value);
    const seletorUnidade = linha.querySelector(".mp-unidade");

    if (!materia) {
        seletorUnidade.innerHTML = opcoesUnidades(unidadeDesejada ?? "g");
        return;
    }

    const compativeis = unidadesCompativeis(materia.unidadeMedida);
    const unidade = compativeis.includes(unidadeDesejada) ? unidadeDesejada : materia.unidadeMedida;
    seletorUnidade.innerHTML = opcoesUnidades(unidade, compativeis);
}

function adicionarLinha(item = null) {
    contadorLinhas += 1;
    const numero = contadorLinhas;
    const linha = document.createElement("div");

    linha.className = "linha-item";
    linha.innerHTML = `
        <div class="campo">
            <label for="mp-${numero}">Matéria-prima</label>
            <select id="mp-${numero}" class="mp-select">${opcoesMateriasPrimas(item?.idMateriaPrima)}</select>
        </div>
        <div class="campo">
            <label for="mp-quantidade-${numero}">Quantidade</label>
            <input id="mp-quantidade-${numero}" class="mp-quantidade" type="text" inputmode="decimal" value="${item ? paraTextoDecimal(item.quantidade) : ""}">
        </div>
        <div class="campo">
            <label for="mp-unidade-${numero}">Unidade</label>
            <select id="mp-unidade-${numero}" class="mp-unidade"></select>
        </div>
        <button type="button" class="botao botao-icone" data-remover aria-label="Remover matéria-prima da receita">
            <i class="fa-solid fa-trash" aria-hidden="true"></i>
        </button>`;

    linhasMaterias.append(linha);
    atualizarUnidadesDaLinha(linha, item?.unidadeMedida ?? null);

    return linha;
}

function custoDaLinha(linha) {
    const materia = buscarMateriaPrima(linha.querySelector(".mp-select").value);
    const quantidade = paraNumero(linha.querySelector(".mp-quantidade").value);
    const unidade = linha.querySelector(".mp-unidade").value;

    if (!materia || quantidade <= 0 || materia.quantidadeEmbalagem <= 0) {
        return 0;
    }

    return converter(quantidade, unidade, materia.unidadeMedida) * materia.precoEmbalagem / materia.quantidadeEmbalagem;
}

function atualizarResumo() {
    const custoReceita = [...linhasMaterias.children].reduce((total, linha) => total + custoDaLinha(linha), 0);
    const rendimento = paraNumero(formulario.rendimento.value);
    const precoVenda = paraNumero(formulario.precoVenda.value);
    const custoUnitario = rendimento > 0 ? custoReceita / rendimento : 0;
    const lucro = precoVenda - custoUnitario;
    const margem = precoVenda > 0 ? (lucro / precoVenda) * 100 : 0;

    document.getElementById("resumo-custo-receita").textContent = Formato.moeda(custoReceita);
    document.getElementById("resumo-custo-unitario").textContent = Formato.moeda(custoUnitario);

    const elementoLucro = document.getElementById("resumo-lucro");
    elementoLucro.textContent = Formato.moeda(lucro);
    elementoLucro.className = lucro < 0 ? "negativo" : "";

    const elementoMargem = document.getElementById("resumo-margem");
    elementoMargem.textContent = Formato.percentual(margem);
    elementoMargem.className = margem < 0 ? "negativo" : "";
}

function coletarMateriasPrimas() {
    return [...linhasMaterias.children].map((linha, indice) => {
        linha.querySelector(".mp-select").dataset.campo = `materiasPrimas[${indice}].idMateriaPrima`;
        linha.querySelector(".mp-quantidade").dataset.campo = `materiasPrimas[${indice}].quantidade`;
        linha.querySelector(".mp-unidade").dataset.campo = `materiasPrimas[${indice}].unidadeMedida`;

        return {
            idMateriaPrima: linha.querySelector(".mp-select").value,
            quantidade: linha.querySelector(".mp-quantidade").value,
            unidadeMedida: linha.querySelector(".mp-unidade").value,
        };
    });
}

async function abrirFormulario(id = null) {
    idEmEdicao = id;
    formulario.reset();
    Formulario.limparErros(formulario);
    linhasMaterias.innerHTML = "";
    document.getElementById("titulo-dialogo-produto").textContent = id ? "Editar produto" : "Novo produto";
    campoAtivo.hidden = !id;

    try {
        const [listaMaterias, produto] = await Promise.all([
            Api.get("materia-prima"),
            id ? Api.get("produto", { id }) : Promise.resolve(null),
        ]);
        materiasPrimas = listaMaterias;

        if (produto) {
            formulario.nome.value = produto.nome;
            formulario.descricao.value = produto.descricao ?? "";
            formulario.rendimento.value = paraTextoDecimal(produto.rendimento);
            formulario.precoVenda.value = paraTextoDecimal(produto.precoVenda.toFixed(2));
            formulario.ativo.checked = produto.ativo;
            produto.materiasPrimas.forEach((item) => adicionarLinha(item));
        }
    } catch (erro) {
        Toast.erro(erro.message);
        return;
    }

    avisoSemMaterias.hidden = materiasPrimas.length > 0;
    if (!linhasMaterias.children.length && materiasPrimas.length) {
        adicionarLinha();
    }

    atualizarResumo();
    dialogo.showModal();
    formulario.nome.focus();
}

async function alterarAtivo(id, ativo) {
    const produto = produtos.find((item) => item.id === id);

    if (!ativo) {
        const confirmado = await confirmar(`Desativar "${produto.nome}"? Ele sai do cardápio, mas o histórico de vendas é mantido.`, {
            titulo: "Desativar produto",
            textoConfirmar: "Desativar",
        });

        if (!confirmado) {
            return;
        }
    }

    try {
        if (!ativo) {
            await Api.delete("produto", { id });
        } else {
            const completo = await Api.get("produto", { id });
            await Api.put("produto", {
                nome: completo.nome,
                descricao: completo.descricao,
                rendimento: completo.rendimento,
                precoVenda: completo.precoVenda,
                ativo: true,
                materiasPrimas: completo.materiasPrimas.map(({ idMateriaPrima, quantidade, unidadeMedida }) => ({ idMateriaPrima, quantidade, unidadeMedida })),
            }, { id });
        }

        Toast.sucesso(ativo ? "Produto reativado." : "Produto desativado.");
        carregar();
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

formulario.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    Formulario.limparErros(formulario);

    const botao = formulario.querySelector('[type="submit"]');
    const dados = {
        nome: formulario.nome.value,
        descricao: formulario.descricao.value,
        rendimento: formulario.rendimento.value,
        precoVenda: formulario.precoVenda.value,
        materiasPrimas: coletarMateriasPrimas(),
    };

    if (idEmEdicao) {
        dados.ativo = formulario.ativo.checked;
    }

    try {
        await comCarregamento(botao, () => (idEmEdicao
            ? Api.put("produto", dados, { id: idEmEdicao })
            : Api.post("produto", dados)));

        dialogo.close();
        Toast.sucesso(idEmEdicao ? "Produto atualizado." : "Produto cadastrado.");
        carregar();
    } catch (erro) {
        Formulario.tratarErro(formulario, erro);
    }
});

linhasMaterias.addEventListener("change", (evento) => {
    if (evento.target.matches(".mp-select")) {
        atualizarUnidadesDaLinha(evento.target.closest(".linha-item"));
    }

    atualizarResumo();
});

linhasMaterias.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-remover]");
    if (!botao) {
        return;
    }

    botao.closest(".linha-item").remove();
    atualizarResumo();
    document.getElementById("adicionar-materia").focus();
});

formulario.addEventListener("input", atualizarResumo);

document.getElementById("adicionar-materia").addEventListener("click", () => {
    const linha = adicionarLinha();
    linha.querySelector("select").focus();
});

lista.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-acao]");
    if (!botao) {
        return;
    }

    const id = Number(botao.dataset.id);
    const acoes = {
        editar: () => abrirFormulario(id),
        desativar: () => alterarAtivo(id, false),
        reativar: () => alterarAtivo(id, true),
    };
    acoes[botao.dataset.acao]();
});

filtroAtivo.addEventListener("click", (evento) => {
    const botao = evento.target.closest("button");
    if (!botao) {
        return;
    }

    filtroAtivo.querySelectorAll("button").forEach((item) => item.setAttribute("aria-pressed", String(item === botao)));
    ativoSelecionado = botao.dataset.ativo;
    carregar();
});

document.getElementById("botao-novo").addEventListener("click", () => abrirFormulario());
busca.addEventListener("input", debounce(carregar));

carregar();
