import { escapeHtml } from "./ui.js";

export const SITUACOES = [
    { valor: "aguardando_confirmacao", rotulo: "Aguardando confirmação", icone: "fa-hourglass-half", etapa: 1 },
    { valor: "em_producao", rotulo: "Em produção", icone: "fa-kitchen-set", etapa: 2 },
    { valor: "pronto_retirada", rotulo: "Pronto para retirada", icone: "fa-bag-shopping", etapa: 3 },
    { valor: "em_rota_entrega", rotulo: "Em rota de entrega", icone: "fa-motorcycle", etapa: 3 },
    { valor: "concluido", rotulo: "Concluído", icone: "fa-circle-check", etapa: 4 },
    { valor: "cancelado", rotulo: "Cancelado", icone: "fa-ban", etapa: 0 },
];

export function buscarSituacao(valor) {
    return SITUACOES.find((situacao) => situacao.valor === valor) ?? SITUACOES[0];
}

export function situacaoFinal(valor) {
    return valor === "concluido" || valor === "cancelado";
}

export function situacoesPermitidas(atual, tipoEntrega) {
    if (situacaoFinal(atual)) {
        return [];
    }

    return SITUACOES.filter((situacao) => {
        if (situacao.valor === atual) {
            return false;
        }

        if (situacao.valor === "pronto_retirada") {
            return tipoEntrega === "retirada";
        }

        if (situacao.valor === "em_rota_entrega") {
            return tipoEntrega === "entrega";
        }

        return true;
    });
}

export function proximaSituacao(atual, tipoEntrega) {
    const fluxo = {
        aguardando_confirmacao: "em_producao",
        em_producao: tipoEntrega === "entrega" ? "em_rota_entrega" : "pronto_retirada",
        pronto_retirada: "concluido",
        em_rota_entrega: "concluido",
    };

    return fluxo[atual] ? buscarSituacao(fluxo[atual]) : null;
}

export function seloSituacao(valor, rotulo = null) {
    const situacao = buscarSituacao(valor);

    return `<span class="selo selo-${escapeHtml(valor)}">
        <i class="fa-solid ${situacao.icone}" aria-hidden="true"></i>${escapeHtml(rotulo ?? situacao.rotulo)}
    </span>`;
}
