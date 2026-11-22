import { Api } from "./api.js";

Api.aoExpirarSessao(() => {
    window.location.href = `${Api.url("entrar/")}?expirou=1`;
});

export const usuario = Api.get("sessao").catch(() => null);

const painel = document.getElementById("painel");
const barraLateral = document.getElementById("barra-lateral");
const fundoMenu = document.getElementById("fundo-menu");
const botaoMenu = document.getElementById("abrir-menu");
const telaPequena = window.matchMedia("(max-width: 900px)");

function marcarMenuAtivo() {
    const pagina = document.body.dataset.pagina;

    document.querySelectorAll(".menu a[data-pagina]").forEach((link) => {
        if (link.dataset.pagina === pagina) {
            link.setAttribute("aria-current", "page");
        }
    });
}

function atualizarMenuMovel() {
    const aberto = painel.classList.contains("menu-aberto");

    barraLateral.inert = telaPequena.matches && !aberto;
    fundoMenu.hidden = !(telaPequena.matches && aberto);
    botaoMenu.setAttribute("aria-expanded", String(aberto));
}

function alternarMenu(abrir) {
    painel.classList.toggle("menu-aberto", abrir);
    atualizarMenuMovel();

    if (abrir) {
        barraLateral.querySelector("a")?.focus();
        return;
    }

    botaoMenu.focus();
}

botaoMenu.addEventListener("click", () => alternarMenu(!painel.classList.contains("menu-aberto")));
fundoMenu.addEventListener("click", () => alternarMenu(false));
telaPequena.addEventListener("change", () => {
    painel.classList.remove("menu-aberto");
    atualizarMenuMovel();
});

document.addEventListener("keydown", (evento) => {
    if (evento.key === "Escape" && painel.classList.contains("menu-aberto")) {
        alternarMenu(false);
    }
});

document.getElementById("botao-sair").addEventListener("click", async () => {
    await Api.delete("sessao").catch(() => null);
    window.location.href = Api.url("entrar/");
});

marcarMenuAtivo();
atualizarMenuMovel();

usuario.then((dados) => {
    if (dados) {
        document.getElementById("nome-negocio").textContent = dados.nomeNegocio;
    }
});
