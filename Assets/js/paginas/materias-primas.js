import "../painel.js";
import { Api } from "../api.js";
import {
    Formato, Formulario, Toast, comCarregamento, confirmar, debounce, escapeHtml,
    estadoCarregando, estadoVazio, paraNumero, paraTextoDecimal,
} from "../ui.js";
import { UNIDADES, opcoesUnidades } from "../unidades.js";

const lista = document.getElementById("lista");
const busca = document.getElementById("busca");
const dialogo = document.getElementById("dialogo-materia");
const formulario = document.getElementById("form-materia");
const tituloDialogo = document.getElementById("titulo-dialogo-materia");
const previaCusto = document.querySelector("#previa-custo span");

let materiasPrimas = [];
let idEmEdicao = null;

formulario.unidadeMedida.innerHTML = opcoesUnidades("g");

async function carregar() {
    lista.innerHTML = estadoCarregando();

    try {
        materiasPrimas = await Api.get("materia-prima", { busca: busca.value.trim() });
        renderizar();
    } catch (erro) {
        lista.innerHTML = estadoVazio("fa-triangle-exclamation", erro.message);
    }
}

function renderizar() {
    if (!materiasPrimas.length) {
        const mensagem = busca.value.trim()
            ? "Nenhuma matéria-prima encontrada para essa busca."
            : "Nenhuma matéria-prima cadastrada. Cadastre os ingredientes para calcular o custo dos produtos.";
        lista.innerHTML = estadoVazio("fa-wheat-awn", mensagem);
        return;
    }

    const linhas = materiasPrimas.map((materia) => `
        <tr>
            <td data-rotulo="Nome"><strong>${escapeHtml(materia.nome)}</strong></td>
            <td data-rotulo="Embalagem" class="numero">${Formato.numero(materia.quantidadeEmbalagem, 3)} ${escapeHtml(materia.unidadeMedida)}</td>
            <td data-rotulo="Preço pago" class="numero">${Formato.moeda(materia.precoEmbalagem)}</td>
            <td data-rotulo="Custo por unidade" class="numero">${Formato.moeda(materia.custoPorUnidade)} / ${escapeHtml(materia.unidadeMedida)}</td>
            <td class="acoes">
                <button type="button" class="botao botao-icone" data-acao="editar" data-id="${materia.id}" aria-label="Editar ${escapeHtml(materia.nome)}">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                </button>
                <button type="button" class="botao botao-icone" data-acao="excluir" data-id="${materia.id}" aria-label="Excluir ${escapeHtml(materia.nome)}">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                </button>
            </td>
        </tr>`).join("");

    lista.innerHTML = `
        <div class="tabela-container">
            <table class="tabela tabela-responsiva">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Embalagem</th>
                        <th scope="col">Preço pago</th>
                        <th scope="col">Custo por unidade</th>
                        <th scope="col"><span class="visualmente-oculto">Ações</span></th>
                    </tr>
                </thead>
                <tbody>${linhas}</tbody>
            </table>
        </div>`;
}

function atualizarPrevia() {
    const quantidade = paraNumero(formulario.quantidadeEmbalagem.value);
    const preco = paraNumero(formulario.precoEmbalagem.value);
    const unidade = formulario.unidadeMedida.value;

    if (quantidade <= 0 || preco <= 0) {
        previaCusto.textContent = "Preencha a quantidade e o preço para ver o custo por unidade.";
        return;
    }

    const custoUnitario = preco / quantidade;
    const nomeUnidade = UNIDADES[unidade]?.nome.toLowerCase() ?? unidade;
    previaCusto.textContent = `Custo por unidade: ${Formato.moeda(custoUnitario)} por ${nomeUnidade}.`;
}

function abrirFormulario(materia = null) {
    idEmEdicao = materia?.id ?? null;
    tituloDialogo.textContent = materia ? "Editar matéria-prima" : "Nova matéria-prima";

    formulario.reset();
    Formulario.limparErros(formulario);
    formulario.nome.value = materia?.nome ?? "";
    formulario.unidadeMedida.value = materia?.unidadeMedida ?? "g";
    formulario.quantidadeEmbalagem.value = materia ? paraTextoDecimal(materia.quantidadeEmbalagem) : "";
    formulario.precoEmbalagem.value = materia ? paraTextoDecimal(materia.precoEmbalagem.toFixed(2)) : "";
    atualizarPrevia();

    dialogo.showModal();
    formulario.nome.focus();
}

async function excluir(materia) {
    const confirmado = await confirmar(`Excluir "${materia.nome}"? Essa ação não pode ser desfeita.`, {
        titulo: "Excluir matéria-prima",
        textoConfirmar: "Excluir",
        perigo: true,
    });

    if (!confirmado) {
        return;
    }

    try {
        await Api.delete("materia-prima", { id: materia.id });
        Toast.sucesso("Matéria-prima excluída.");
        carregar();
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

formulario.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    Formulario.limparErros(formulario);

    const botao = formulario.querySelector('[type="submit"]');
    const dados = Formulario.dados(formulario);

    try {
        await comCarregamento(botao, () => (idEmEdicao
            ? Api.put("materia-prima", dados, { id: idEmEdicao })
            : Api.post("materia-prima", dados)));

        dialogo.close();
        Toast.sucesso(idEmEdicao ? "Matéria-prima atualizada." : "Matéria-prima cadastrada.");
        carregar();
    } catch (erro) {
        Formulario.tratarErro(formulario, erro);
    }
});

lista.addEventListener("click", (evento) => {
    const botao = evento.target.closest("[data-acao]");
    if (!botao) {
        return;
    }

    const materia = materiasPrimas.find((item) => item.id === Number(botao.dataset.id));
    if (botao.dataset.acao === "editar") {
        abrirFormulario(materia);
        return;
    }

    excluir(materia);
});

["input", "change"].forEach((tipo) => formulario.addEventListener(tipo, atualizarPrevia));
document.getElementById("botao-nova").addEventListener("click", () => abrirFormulario());
busca.addEventListener("input", debounce(carregar));

carregar();
