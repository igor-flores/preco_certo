import { Api } from "../api.js";
import { Formulario, comCarregamento } from "../ui.js";

const formulario = document.getElementById("form-entrar");

if (new URLSearchParams(window.location.search).has("expirou")) {
    document.getElementById("aviso-expirou").hidden = false;
}

formulario.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    Formulario.limparErros(formulario);

    const botao = formulario.querySelector('[type="submit"]');

    try {
        await comCarregamento(botao, () => Api.post("sessao", Formulario.dados(formulario)));
        window.location.href = Api.url();
    } catch (erro) {
        Formulario.tratarErro(formulario, erro);
    }
});
