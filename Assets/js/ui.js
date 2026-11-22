import { ApiErro } from "./api.js";

export function escapeHtml(valor) {
    const entidades = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };

    return String(valor ?? "").replace(/[&<>"']/g, (caractere) => entidades[caractere]);
}

const formatoMoeda = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" });

export const Formato = {
    moeda: (valor) => formatoMoeda.format(Number(valor) || 0),
    numero: (valor, casas = 2) => new Intl.NumberFormat("pt-BR", { maximumFractionDigits: casas }).format(Number(valor) || 0),
    percentual: (valor) => `${Formato.numero(valor, 1)}%`,
    data(texto) {
        if (!texto) {
            return "—";
        }

        const [ano, mes, dia] = texto.slice(0, 10).split("-");
        return `${dia}/${mes}/${ano}`;
    },
    dataHora(texto) {
        if (!texto) {
            return "—";
        }

        return `${Formato.data(texto)} ${texto.slice(11, 16)}`.trim();
    },
    hora: (texto) => (texto ? texto.slice(11, 16) : ""),
    telefone(digitos) {
        const numeros = String(digitos ?? "").replace(/\D/g, "");

        if (numeros.length === 11) {
            return `(${numeros.slice(0, 2)}) ${numeros.slice(2, 7)}-${numeros.slice(7)}`;
        }

        if (numeros.length === 10) {
            return `(${numeros.slice(0, 2)}) ${numeros.slice(2, 6)}-${numeros.slice(6)}`;
        }

        return numeros;
    },
    plural: (quantidade, singular, plural) => `${quantidade} ${quantidade === 1 ? singular : plural}`,
};

export function mascararTelefone(campo) {
    campo.addEventListener("input", () => {
        const numeros = campo.value.replace(/\D/g, "").slice(0, 11);

        if (numeros.length <= 2) {
            campo.value = numeros.length ? `(${numeros}` : "";
            return;
        }

        const ddd = numeros.slice(0, 2);
        const resto = numeros.slice(2);
        const corte = resto.length > 8 ? 5 : 4;
        const meio = resto.slice(0, corte);
        const fim = resto.slice(corte);

        campo.value = fim ? `(${ddd}) ${meio}-${fim}` : `(${ddd}) ${meio}`;
    });
}

export function paraNumero(texto) {
    if (typeof texto === "number") {
        return texto;
    }

    const limpo = String(texto ?? "").trim();
    const normalizado = limpo.includes(",") ? limpo.replace(/\./g, "").replace(",", ".") : limpo;
    const numero = Number(normalizado);

    return Number.isFinite(numero) ? numero : 0;
}

export function paraTextoDecimal(numero) {
    return String(numero ?? "").replace(".", ",");
}

export const Toast = {
    mostrar(mensagem, tipo = "info") {
        const container = document.getElementById("toasts");
        const icones = { sucesso: "fa-circle-check", erro: "fa-circle-exclamation", info: "fa-circle-info" };
        const elemento = document.createElement("div");

        elemento.className = `toast toast-${tipo}`;
        elemento.setAttribute("role", tipo === "erro" ? "alert" : "status");
        elemento.innerHTML = `<i class="fa-solid ${icones[tipo]}" aria-hidden="true"></i><span>${escapeHtml(mensagem)}</span>`;
        container.append(elemento);

        setTimeout(() => elemento.remove(), tipo === "erro" ? 7000 : 4000);
    },
    sucesso(mensagem) {
        this.mostrar(mensagem, "sucesso");
    },
    erro(mensagem) {
        this.mostrar(mensagem, "erro");
    },
};

export const Formulario = {
    limparErros(formulario) {
        formulario.querySelectorAll(".campo-erro").forEach((elemento) => elemento.remove());
        formulario.querySelectorAll("[aria-invalid]").forEach((elemento) => {
            elemento.removeAttribute("aria-invalid");

            const descricaoOriginal = elemento.dataset.descricaoOriginal;
            if (descricaoOriginal) {
                elemento.setAttribute("aria-describedby", descricaoOriginal);
                return;
            }

            elemento.removeAttribute("aria-describedby");
        });
        formulario.querySelectorAll(".erro-geral").forEach((elemento) => (elemento.hidden = true));
    },

    mostrarErro(formulario, campo, mensagem) {
        const seletor = CSS.escape(campo);
        const alvo = formulario.querySelector(`[data-campo="${seletor}"]`) ?? formulario.querySelector(`[name="${seletor}"]`);

        if (!alvo) {
            this.erroGeral(formulario, mensagem);
            return;
        }

        const container = alvo.closest(".campo") ?? alvo.parentElement;
        const idErro = `erro-${campo.replace(/[^a-zA-Z0-9]/g, "-")}`;
        const paragrafo = document.createElement("p");

        paragrafo.className = "campo-erro";
        paragrafo.id = idErro;
        paragrafo.innerHTML = '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span></span>';
        paragrafo.querySelector("span").textContent = mensagem;
        container.append(paragrafo);

        const entrada = alvo.matches("input, select, textarea") ? alvo : alvo.querySelector("input, select, textarea");
        if (!entrada) {
            return;
        }

        const descricaoAtual = entrada.getAttribute("aria-describedby");
        if (descricaoAtual && !entrada.dataset.descricaoOriginal) {
            entrada.dataset.descricaoOriginal = descricaoAtual;
        }

        const descricoes = [idErro, entrada.dataset.descricaoOriginal].filter(Boolean).join(" ");
        entrada.setAttribute("aria-invalid", "true");
        entrada.setAttribute("aria-describedby", descricoes);
        entrada.focus();
    },

    erroGeral(formulario, mensagem) {
        let elemento = formulario.querySelector(".erro-geral");

        if (!elemento) {
            elemento = document.createElement("div");
            elemento.className = "alerta alerta-erro erro-geral";
            elemento.setAttribute("role", "alert");
            formulario.prepend(elemento);
        }

        elemento.textContent = mensagem;
        elemento.hidden = false;
        elemento.scrollIntoView({ block: "nearest", behavior: "smooth" });
    },

    tratarErro(formulario, erro) {
        if (erro instanceof ApiErro && erro.campo) {
            this.mostrarErro(formulario, erro.campo, erro.message);
            return;
        }

        this.erroGeral(formulario, erro?.message || "Erro inesperado. Tente novamente.");
    },

    dados(formulario) {
        return Object.fromEntries(new FormData(formulario));
    },
};

export async function comCarregamento(botao, acao) {
    botao.disabled = true;
    botao.classList.add("carregando");
    botao.setAttribute("aria-busy", "true");

    try {
        return await acao();
    } finally {
        botao.disabled = false;
        botao.classList.remove("carregando");
        botao.removeAttribute("aria-busy");
    }
}

export function confirmar(mensagem, { titulo = "Confirmar", textoConfirmar = "Confirmar", perigo = false } = {}) {
    const dialogo = document.createElement("dialog");
    dialogo.className = "dialogo dialogo-pequeno";
    dialogo.setAttribute("aria-labelledby", "titulo-confirmacao");
    dialogo.innerHTML = `
        <div class="dialogo-conteudo">
            <div class="dialogo-cabecalho"><h2 id="titulo-confirmacao">${escapeHtml(titulo)}</h2></div>
            <div class="dialogo-corpo"><p>${escapeHtml(mensagem)}</p></div>
            <div class="dialogo-rodape">
                <button type="button" class="botao botao-secundario" value="nao">Voltar</button>
                <button type="button" class="botao ${perigo ? "botao-perigo" : ""}" value="sim">${escapeHtml(textoConfirmar)}</button>
            </div>
        </div>`;
    document.body.append(dialogo);

    return new Promise((resolver) => {
        dialogo.addEventListener("click", (evento) => {
            const botao = evento.target.closest("button[value]");
            if (botao) {
                dialogo.close(botao.value);
            }
        });

        dialogo.addEventListener("close", () => {
            resolver(dialogo.returnValue === "sim");
            dialogo.remove();
        });

        dialogo.showModal();
        dialogo.querySelector('button[value="nao"]').focus();
    });
}

export async function copiarTexto(texto, mensagem = "Link copiado.") {
    try {
        await navigator.clipboard.writeText(texto);
        Toast.sucesso(mensagem);
    } catch {
        window.prompt("Copie o texto abaixo:", texto);
    }
}

export function estadoVazio(icone, mensagem) {
    return `<div class="vazio"><i class="fa-solid ${icone}" aria-hidden="true"></i><p>${escapeHtml(mensagem)}</p></div>`;
}

export function estadoCarregando(mensagem = "Carregando…") {
    return `<div class="carregando-bloco" role="status"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> ${escapeHtml(mensagem)}</div>`;
}

export function cartaoIndicador({ rotulo, valor, detalhe = "", variacao = null, inverterCores = false }) {
    let htmlVariacao = "";

    if (variacao !== null && variacao !== undefined) {
        const subiu = variacao > 0;
        const caiu = variacao < 0;
        const bom = inverterCores ? caiu : subiu;
        const ruim = inverterCores ? subiu : caiu;
        const classe = bom ? "positivo" : ruim ? "negativo" : "texto-suave";
        const icone = subiu ? "fa-arrow-trend-up" : caiu ? "fa-arrow-trend-down" : "fa-minus";
        const sinal = subiu ? "+" : "";
        const descricao = subiu ? "aumento" : caiu ? "queda" : "sem variação";

        htmlVariacao = `<span class="variacao ${classe}">
            <i class="fa-solid ${icone}" aria-hidden="true"></i>${sinal}${Formato.percentual(variacao)}
            <span class="visualmente-oculto">(${descricao})</span>
        </span>`;
    }

    return `
        <div class="cartao indicador">
            <span class="rotulo">${escapeHtml(rotulo)}</span>
            <span class="valor">${escapeHtml(valor)}</span>
            <span class="detalhe">${htmlVariacao} ${escapeHtml(detalhe)}</span>
        </div>`;
}

export function debounce(funcao, espera = 300) {
    let temporizador;

    return (...argumentos) => {
        clearTimeout(temporizador);
        temporizador = setTimeout(() => funcao(...argumentos), espera);
    };
}

export function corTextoLegivel(corFundo) {
    return contraste(corFundo, "#FFFFFF") >= contraste(corFundo, "#000000") ? "#FFFFFF" : "#000000";
}

export function contraste(cor1, cor2) {
    const luminancia = (cor) => {
        const hex = cor.replace("#", "");
        const canais = [0, 2, 4].map((inicio) => parseInt(hex.slice(inicio, inicio + 2), 16) / 255);
        const [r, g, b] = canais.map((canal) => (canal <= 0.03928 ? canal / 12.92 : ((canal + 0.055) / 1.055) ** 2.4));

        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };

    const [maior, menor] = [luminancia(cor1), luminancia(cor2)].sort((a, b) => b - a);

    return (maior + 0.05) / (menor + 0.05);
}

document.addEventListener("click", (evento) => {
    const botaoFechar = evento.target.closest("[data-fechar-dialogo]");
    if (botaoFechar) {
        botaoFechar.closest("dialog")?.close();
        return;
    }

    const botaoSenha = evento.target.closest("[data-alternar-senha]");
    if (!botaoSenha) {
        return;
    }

    const campo = document.getElementById(botaoSenha.dataset.alternarSenha);
    const mostrar = campo.type === "password";
    campo.type = mostrar ? "text" : "password";
    botaoSenha.setAttribute("aria-label", mostrar ? "Ocultar senha" : "Mostrar senha");
    botaoSenha.querySelector("i").className = `fa-solid ${mostrar ? "fa-eye-slash" : "fa-eye"}`;
});
