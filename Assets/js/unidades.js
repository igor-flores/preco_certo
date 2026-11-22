export const UNIDADES = {
    mg: { grupo: "massa", fator: 0.001, nome: "Miligrama (mg)" },
    g: { grupo: "massa", fator: 1, nome: "Grama (g)" },
    kg: { grupo: "massa", fator: 1000, nome: "Quilograma (kg)" },
    ml: { grupo: "volume", fator: 1, nome: "Mililitro (ml)" },
    l: { grupo: "volume", fator: 1000, nome: "Litro (l)" },
    un: { grupo: "unidade", fator: 1, nome: "Unidade (un)" },
    dz: { grupo: "unidade", fator: 12, nome: "Dúzia (dz)" },
};

export const GRUPOS = {
    massa: "Peso",
    volume: "Volume",
    unidade: "Quantidade",
};

export function unidadesCompativeis(unidade) {
    const grupo = UNIDADES[unidade]?.grupo;

    return Object.keys(UNIDADES).filter((chave) => UNIDADES[chave].grupo === grupo);
}

export function converter(quantidade, de, para) {
    return (quantidade * UNIDADES[de].fator) / UNIDADES[para].fator;
}

export function opcoesUnidades(selecionada = "", somente = null) {
    return Object.entries(GRUPOS)
        .map(([grupo, rotulo]) => {
            const opcoes = Object.entries(UNIDADES)
                .filter(([chave, unidade]) => unidade.grupo === grupo && (!somente || somente.includes(chave)))
                .map(([chave, unidade]) => `<option value="${chave}" ${chave === selecionada ? "selected" : ""}>${unidade.nome}</option>`)
                .join("");

            return opcoes ? `<optgroup label="${rotulo}">${opcoes}</optgroup>` : "";
        })
        .join("");
}
