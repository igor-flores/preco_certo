const basePath = document.querySelector('meta[name="base-path"]')?.content || "/";

let aoExpirarSessao = null;

export class ApiErro extends Error {
    constructor(mensagem, status, campo = null) {
        super(mensagem);
        this.status = status;
        this.campo = campo;
    }
}

async function requisitar(metodo, rota, { corpo, query } = {}) {
    const url = new URL(`${basePath}api/${rota}`, window.location.origin);

    Object.entries(query ?? {}).forEach(([chave, valor]) => {
        if (valor === null || valor === undefined || valor === "") {
            return;
        }

        url.searchParams.set(chave, valor);
    });

    const opcoes = {
        method: metodo,
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    };

    if (corpo !== undefined) {
        opcoes.headers["Content-Type"] = "application/json";
        opcoes.body = JSON.stringify(corpo);
    }

    let resposta;
    try {
        resposta = await fetch(url, opcoes);
    } catch {
        throw new ApiErro("Sem conexão com o servidor. Verifique sua internet e tente novamente.", 0);
    }

    if (resposta.status === 204) {
        return null;
    }

    const dados = await resposta.json().catch(() => null);

    if (resposta.ok) {
        return dados;
    }

    if (resposta.status === 401 && aoExpirarSessao) {
        aoExpirarSessao();
    }

    throw new ApiErro(dados?.error ?? "Erro inesperado. Tente novamente em instantes.", resposta.status, dados?.campo ?? null);
}

export const Api = {
    get: (rota, query) => requisitar("GET", rota, { query }),
    post: (rota, corpo, query) => requisitar("POST", rota, { corpo, query }),
    put: (rota, corpo, query) => requisitar("PUT", rota, { corpo, query }),
    delete: (rota, query) => requisitar("DELETE", rota, { query }),
    url: (caminho = "") => `${basePath}${caminho}`,
    urlAbsoluta: (caminho = "") => `${window.location.origin}${basePath}${caminho}`,
    aoExpirarSessao(callback) {
        aoExpirarSessao = callback;
    },
};
