import "../painel.js";
import { Api } from "../api.js";
import { Formato, Toast, cartaoIndicador, escapeHtml } from "../ui.js";

const CORES = {
    atual: "#166534",
    anterior: "#9ca3af",
    lucro: "#b45309",
    estimativa: "#2563eb",
};

const Chart = window.Chart;
Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
Chart.defaults.color = "#4b5563";

const formatarEixoMoeda = (valor) => Formato.moeda(valor).replace(",00", "");
const tooltipMoeda = (contexto) => `${contexto.dataset.label}: ${Formato.moeda(contexto.parsed.y)}`;

let periodoFinanceiro = "mes";
let graficoFinanceiro = null;
let graficoEstimativa = null;

function alerta(tipo, icone, mensagem) {
    return `<div class="alerta alerta-${tipo}"><i class="fa-solid ${icone}" aria-hidden="true"></i><span>${escapeHtml(mensagem)}</span></div>`;
}

function textoTendencia(relatorio) {
    const variacao = relatorio.variacao.lucro;
    const anterior = relatorio.anterior.rotulo;

    if (variacao === null) {
        return relatorio.atual.lucro > 0
            ? alerta("info", "fa-seedling", `Sem vendas concluídas em ${anterior} para comparar.`)
            : alerta("info", "fa-circle-info", "Ainda não há vendas concluídas nesses períodos.");
    }

    if (relatorio.tendencia === "crescimento") {
        return alerta("sucesso", "fa-arrow-trend-up", `Crescimento de ${Formato.percentual(variacao)} no lucro em relação a ${anterior}.`);
    }

    if (relatorio.tendencia === "queda") {
        return alerta("erro", "fa-arrow-trend-down", `Queda de ${Formato.percentual(Math.abs(variacao))} no lucro em relação a ${anterior}.`);
    }

    return alerta("info", "fa-minus", `Lucro estável em relação a ${anterior}.`);
}

async function carregarFinanceiro() {
    const referencia = document.getElementById("referencia").value;

    try {
        const relatorio = await Api.get("relatorio-financeiro", { periodo: periodoFinanceiro, referencia });
        const { atual, anterior, variacao } = relatorio;

        const avisoAndamento = relatorio.periodoEmAndamento
            ? alerta("aviso", "fa-hourglass-half", `${atual.rotulo} ainda está em andamento: os números são parciais.`)
            : "";
        document.getElementById("aviso-financeiro").innerHTML = `<div class="pilha" style="gap: 0.5rem;">${avisoAndamento}${textoTendencia(relatorio)}</div>`;

        document.getElementById("indicadores-financeiro").innerHTML = [
            cartaoIndicador({ rotulo: `Faturamento · ${atual.rotulo}`, valor: Formato.moeda(atual.faturamento), variacao: variacao.faturamento, detalhe: `antes ${Formato.moeda(anterior.faturamento)}` }),
            cartaoIndicador({ rotulo: "Custo", valor: Formato.moeda(atual.custo), detalhe: `antes ${Formato.moeda(anterior.custo)}` }),
            cartaoIndicador({ rotulo: "Lucro", valor: Formato.moeda(atual.lucro), variacao: variacao.lucro, detalhe: `antes ${Formato.moeda(anterior.lucro)}` }),
            cartaoIndicador({
                rotulo: "Margem de lucro",
                valor: Formato.percentual(atual.margemLucro),
                detalhe: `${variacao.margemLucroPontos >= 0 ? "+" : ""}${Formato.numero(variacao.margemLucroPontos, 1)} p.p. (antes ${Formato.percentual(anterior.margemLucro)})`,
            }),
            cartaoIndicador({ rotulo: "Pedidos concluídos", valor: String(atual.pedidos), variacao: variacao.pedidos, detalhe: `antes ${anterior.pedidos}` }),
        ].join("");

        desenharFinanceiro(relatorio);
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

function desenharFinanceiro({ atual, anterior }) {
    const dados = {
        labels: ["Faturamento", "Custo", "Lucro"],
        datasets: [
            { label: anterior.rotulo, data: [anterior.faturamento, anterior.custo, anterior.lucro], backgroundColor: CORES.anterior, borderRadius: 4 },
            { label: atual.rotulo, data: [atual.faturamento, atual.custo, atual.lucro], backgroundColor: CORES.atual, borderRadius: 4 },
        ],
    };

    if (graficoFinanceiro) {
        graficoFinanceiro.data = dados;
        graficoFinanceiro.update();
        return;
    }

    graficoFinanceiro = new Chart(document.getElementById("grafico-financeiro"), {
        type: "bar",
        data: dados,
        options: {
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { callback: formatarEixoMoeda } } },
            plugins: { tooltip: { callbacks: { label: tooltipMoeda } } },
        },
    });
}

async function carregarEstimativa() {
    const periodo = document.getElementById("periodo-estimativa").value;
    const periodos = document.getElementById("quantidade-periodos").value;

    try {
        const resposta = await Api.get("estimativa-vendas", { periodo, periodos });
        const { estimativa, realizadoAteAgora, periodoEstimado } = resposta;
        const aviso = document.getElementById("aviso-estimativa");
        const indicadores = document.getElementById("indicadores-estimativa");

        if (!estimativa) {
            aviso.innerHTML = alerta("info", "fa-circle-info", resposta.aviso);
            indicadores.innerHTML = "";
        } else {
            const textos = {
                crescimento: ["sucesso", "fa-arrow-trend-up", "Tendência de crescimento nas vendas."],
                queda: ["erro", "fa-arrow-trend-down", "Tendência de queda nas vendas."],
                estavel: ["info", "fa-minus", "Vendas estáveis nos últimos períodos."],
            };
            aviso.innerHTML = alerta(...textos[resposta.tendencia]);

            const progresso = estimativa.faturamento > 0 ? (realizadoAteAgora.faturamento / estimativa.faturamento) * 100 : 0;
            indicadores.innerHTML = [
                cartaoIndicador({ rotulo: `Faturamento estimado · ${periodoEstimado.rotulo}`, valor: Formato.moeda(estimativa.faturamento) }),
                cartaoIndicador({ rotulo: "Lucro estimado", valor: Formato.moeda(estimativa.lucro) }),
                cartaoIndicador({ rotulo: "Pedidos estimados", valor: String(estimativa.pedidos) }),
                cartaoIndicador({
                    rotulo: "Realizado até agora",
                    valor: Formato.moeda(realizadoAteAgora.faturamento),
                    detalhe: `${Formato.percentual(progresso)} da estimativa`,
                }),
            ].join("");
        }

        desenharEstimativa(resposta);
    } catch (erro) {
        Toast.erro(erro.message);
    }
}

function desenharEstimativa({ historico, estimativa, realizadoAteAgora, periodoEstimado }) {
    const rotulos = [...historico.map((item) => item.rotulo), periodoEstimado.rotulo];
    const vazios = historico.map(() => null);
    const ultimo = historico[historico.length - 1];

    const datasets = [
        { label: "Faturamento", data: [...historico.map((item) => item.faturamento), null], borderColor: CORES.atual, backgroundColor: CORES.atual, tension: 0.25 },
        { label: "Lucro", data: [...historico.map((item) => item.lucro), null], borderColor: CORES.lucro, backgroundColor: CORES.lucro, tension: 0.25 },
        { label: "Realizado até agora", data: [...vazios, realizadoAteAgora.faturamento], borderColor: CORES.anterior, backgroundColor: CORES.anterior, pointRadius: 6, showLine: false },
    ];

    if (estimativa) {
        const linhaEstimativa = historico.map(() => null);
        linhaEstimativa[linhaEstimativa.length - 1] = ultimo.faturamento;

        datasets.push({
            label: "Estimativa de faturamento",
            data: [...linhaEstimativa, estimativa.faturamento],
            borderColor: CORES.estimativa,
            backgroundColor: CORES.estimativa,
            borderDash: [6, 6],
            pointRadius: 5,
        });
    }

    const dados = { labels: rotulos, datasets };

    if (graficoEstimativa) {
        graficoEstimativa.data = dados;
        graficoEstimativa.update();
        return;
    }

    graficoEstimativa = new Chart(document.getElementById("grafico-estimativa"), {
        type: "line",
        data: dados,
        options: {
            maintainAspectRatio: false,
            spanGaps: false,
            scales: { y: { beginAtZero: true, ticks: { callback: formatarEixoMoeda } } },
            plugins: { tooltip: { callbacks: { label: tooltipMoeda } } },
        },
    });
}

document.getElementById("periodo-financeiro").addEventListener("click", (evento) => {
    const botao = evento.target.closest("button");
    if (!botao) {
        return;
    }

    document.querySelectorAll("#periodo-financeiro button").forEach((item) => item.setAttribute("aria-pressed", String(item === botao)));
    periodoFinanceiro = botao.dataset.periodo;
    carregarFinanceiro();
});

document.getElementById("referencia").addEventListener("change", carregarFinanceiro);
document.getElementById("form-financeiro").addEventListener("submit", (evento) => evento.preventDefault());
document.getElementById("form-estimativa").addEventListener("change", carregarEstimativa);
document.getElementById("form-estimativa").addEventListener("submit", (evento) => evento.preventDefault());

carregarFinanceiro();
carregarEstimativa();
