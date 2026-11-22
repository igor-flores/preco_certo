import { Api } from "../api.js";
import { Formato, copiarTexto, escapeHtml, estadoVazio } from "../ui.js";
import { buscarSituacao, situacaoFinal } from "../situacoes.js";

const INTERVALO_ATUALIZACAO = 20000;
const hash = window.location.pathname.split("/").filter(Boolean).pop() ?? "";

let temporizador = null;
let situacaoAnterior = null;

function aplicarCores(pedido) {
    const estilo = document.body.style;
    estilo.setProperty("--loja-primaria", pedido.corPrimaria);
    estilo.setProperty("--loja-texto-primaria", pedido.corTextoPrimaria);
    estilo.setProperty("--cor-linha-tempo", pedido.corPrimaria);
}

function renderizar(pedido) {
    const situacao = buscarSituacao(pedido.situacao);

    document.title = `Pedido · ${pedido.nomeNegocio}`;
    document.getElementById("nome-negocio").textContent = pedido.nomeNegocio;
    document.getElementById("info-pedido").innerHTML = `
        <span><i class="fa-regular fa-clock" aria-hidden="true"></i>Feito em ${Formato.dataHora(pedido.dataPedido)}</span>
        <span><i class="fa-solid fa-user" aria-hidden="true"></i>${escapeHtml(pedido.cliente)}</span>`;

    document.getElementById("icone-situacao").className = `fa-solid ${situacao.icone}`;
    document.getElementById("rotulo-situacao").textContent = pedido.rotuloSituacao;
    document.querySelectorAll("#progresso span").forEach((barra, indice) => {
        barra.classList.toggle("ativo", indice < situacao.etapa);
    });
    document.getElementById("progresso").hidden = pedido.situacao === "cancelado";
    document.getElementById("atualizado-em").textContent = situacaoFinal(pedido.situacao)
        ? ""
        : `Atualiza sozinho · última verificação às ${new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}`;

    document.getElementById("historico").innerHTML = [...pedido.historico].reverse().map((registro) => `
        <li><strong>${escapeHtml(registro.rotulo)}</strong><span class="texto-suave numero">${Formato.dataHora(registro.dataHora)}</span></li>`).join("");

    const itens = pedido.itens.map((item) => `
        <li><span>${item.quantidade}× ${escapeHtml(item.nome)}</span><span class="numero">${Formato.moeda(item.subtotal)}</span></li>`).join("");
    const taxa = pedido.tipoEntrega === "entrega"
        ? `<li class="texto-suave"><span>Taxa de entrega</span><span class="numero">${pedido.taxaEntrega > 0 ? Formato.moeda(pedido.taxaEntrega) : "Grátis"}</span></li>`
        : "";

    document.getElementById("itens").innerHTML = `${itens}${taxa}
        <li class="total"><span>Total</span><span class="numero">${Formato.moeda(pedido.valorTotal)}</span></li>`;

    document.getElementById("dados").innerHTML = `
        <dt>Recebimento</dt><dd>${pedido.tipoEntrega === "entrega" ? "Entrega" : "Retirada no local"}</dd>
        <dt>Pagamento</dt><dd>${escapeHtml(pedido.formaPagamento)}</dd>`;

    if (pedido.linkCardapio) {
        const voltar = document.getElementById("voltar-cardapio");
        voltar.href = Api.url(`loja/${pedido.linkCardapio}`);
        voltar.hidden = false;
    }
}

async function carregar() {
    try {
        const pedido = await Api.get("pedido-publico", { hash });

        if (situacaoAnterior === null) {
            aplicarCores(pedido);
        }

        situacaoAnterior = pedido.situacao;
        renderizar(pedido);
        document.getElementById("area-acompanhamento").hidden = false;

        if (situacaoFinal(pedido.situacao)) {
            clearInterval(temporizador);
        }
    } catch (erro) {
        if (situacaoAnterior !== null) {
            return;
        }

        clearInterval(temporizador);
        document.getElementById("nome-negocio").textContent = "Pedido não encontrado";
        const elementoErro = document.getElementById("erro-carregamento");
        elementoErro.innerHTML = estadoVazio("fa-receipt", erro.status === 404
            ? "Não encontramos este pedido. Confira se o link está completo."
            : erro.message);
        elementoErro.hidden = false;
    }
}

document.getElementById("copiar-link").addEventListener("click", () => copiarTexto(window.location.href));

temporizador = setInterval(() => {
    if (document.visibilityState === "visible") {
        carregar();
    }
}, INTERVALO_ATUALIZACAO);

carregar();
