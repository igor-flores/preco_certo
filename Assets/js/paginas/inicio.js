import { usuario } from "../painel.js";
import { Api } from "../api.js";
import { Formato, cartaoIndicador, copiarTexto, escapeHtml, estadoCarregando, estadoVazio } from "../ui.js";
import { seloSituacao } from "../situacoes.js";

const indicadores = document.getElementById("indicadores");
const pendentes = document.getElementById("pedidos-pendentes");
const resumoCardapio = document.getElementById("resumo-cardapio");

function renderizarIndicadores(relatorio) {
    const { atual, anterior, variacao } = relatorio;

    indicadores.innerHTML = [
        cartaoIndicador({
            rotulo: "Faturamento",
            valor: Formato.moeda(atual.faturamento),
            variacao: variacao.faturamento,
            detalhe: `vs ${Formato.moeda(anterior.faturamento)} em ${anterior.rotulo}`,
        }),
        cartaoIndicador({
            rotulo: "Lucro",
            valor: Formato.moeda(atual.lucro),
            variacao: variacao.lucro,
            detalhe: `vs ${Formato.moeda(anterior.lucro)}`,
        }),
        cartaoIndicador({
            rotulo: "Margem de lucro",
            valor: Formato.percentual(atual.margemLucro),
            detalhe: `${variacao.margemLucroPontos >= 0 ? "+" : ""}${Formato.numero(variacao.margemLucroPontos, 1)} p.p. vs período anterior`,
        }),
        cartaoIndicador({
            rotulo: "Pedidos concluídos",
            valor: String(atual.pedidos),
            variacao: variacao.pedidos,
            detalhe: `vs ${anterior.pedidos}`,
        }),
    ].join("");
}

function renderizarPendentes(lista) {
    if (!lista.length) {
        pendentes.innerHTML = estadoVazio("fa-mug-hot", "Nenhum pedido aguardando ou em produção.");
        return;
    }

    pendentes.innerHTML = lista.map((pedido) => `
        <a href="${Api.url(`pedidos/?id=${pedido.id}`)}" class="caixa-selecao" style="justify-content: space-between; text-decoration: none; color: inherit;">
            <span>
                <strong>#${pedido.id} · ${escapeHtml(pedido.cliente.nome)}</strong>
                <span class="texto-suave texto-pequeno" style="display: block;">${Formato.dataHora(pedido.dataPedido)} · ${Formato.moeda(pedido.valorTotal)}</span>
            </span>
            ${seloSituacao(pedido.situacao, pedido.rotuloSituacao)}
        </a>`).join("");
}

function renderizarCardapio(cardapio) {
    if (!cardapio.linkPublico) {
        resumoCardapio.innerHTML = `
            ${estadoVazio("fa-book-open", "Seu cardápio ainda não foi publicado.")}
            <a class="botao botao-bloco" href="${Api.url("cardapio/")}">Configurar cardápio</a>`;
        return;
    }

    const url = Api.urlAbsoluta(`loja/${cardapio.linkPublico}`);
    resumoCardapio.innerHTML = `
        <div class="pilha">
            <p class="texto-suave">${Formato.plural(cardapio.produtos.length, "produto exibido", "produtos exibidos")}. Compartilhe o link com seus clientes:</p>
            <div class="link-publico">
                <code>${escapeHtml(url)}</code>
            </div>
            <div class="linha">
                <button type="button" class="botao botao-secundario" id="copiar-link"><i class="fa-solid fa-copy" aria-hidden="true"></i>Copiar link</button>
                <a class="botao botao-secundario" href="${escapeHtml(url)}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>Abrir</a>
            </div>
        </div>`;

    document.getElementById("copiar-link").addEventListener("click", () => copiarTexto(url));
}

async function carregar() {
    indicadores.innerHTML = estadoCarregando("Carregando indicadores…");
    pendentes.innerHTML = estadoCarregando();
    resumoCardapio.innerHTML = estadoCarregando();

    usuario.then((dados) => {
        if (dados) {
            document.getElementById("saudacao").textContent = `Olá, ${dados.nomeResponsavel.split(" ")[0]}!`;
        }
    });

    try {
        const [relatorio, aguardando, emProducao, cardapio] = await Promise.all([
            Api.get("relatorio-financeiro", { periodo: "mes" }),
            Api.get("pedido", { situacao: "aguardando_confirmacao", limite: 5 }),
            Api.get("pedido", { situacao: "em_producao", limite: 5 }),
            Api.get("cardapio"),
        ]);

        document.getElementById("subtitulo-inicio").textContent = `Resumo de ${relatorio.atual.rotulo}, considerando pedidos concluídos.`;
        renderizarIndicadores(relatorio);
        renderizarPendentes([...aguardando.pedidos, ...emProducao.pedidos].slice(0, 8));
        renderizarCardapio(cardapio);
    } catch (erro) {
        indicadores.innerHTML = estadoVazio("fa-triangle-exclamation", erro.message);
    }
}

carregar();
