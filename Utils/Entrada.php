<?php

namespace Utils;

use BackedEnum;
use DateTimeImmutable;
use Model\Exception\AppException;
use Model\Exception\ValidacaoException;

final class Entrada
{
    private static ?array $corpo = null;

    public function __construct(
        private array $dados,
        private string $prefixo = ""
    ) {}

    public static function doCorpo(): self
    {
        self::$corpo ??= self::lerCorpo();

        return new self(self::$corpo);
    }

    public static function daQuery(): self
    {
        $dados = $_GET;
        unset($dados["pag"]);

        return new self($dados);
    }

    public function tem(string $campo): bool
    {
        return isset($this->dados[$campo]) && $this->dados[$campo] !== "";
    }

    public function texto(string $campo, string $rotulo, bool $obrigatorio = true, int $maximo = 255): ?string
    {
        if (!$this->tem($campo)) {
            $this->exigir($campo, $rotulo, $obrigatorio);
            return null;
        }

        $valor = $this->dados[$campo];

        if (!is_scalar($valor)) {
            throw $this->erro($campo, "O campo {$rotulo} deve ser um texto.");
        }

        $valor = trim((string) $valor);

        if ($valor === "") {
            $this->exigir($campo, $rotulo, $obrigatorio);
            return null;
        }

        if (mb_strlen($valor) > $maximo) {
            throw $this->erro($campo, "O campo {$rotulo} aceita no máximo {$maximo} caracteres. Abrevie o texto.");
        }

        return $valor;
    }

    public function senha(string $campo, string $rotulo): string
    {
        $valor = $this->dados[$campo] ?? null;

        if (!is_string($valor) || $valor === "") {
            throw $this->erro($campo, "Informe a {$rotulo}.");
        }

        if (strlen($valor) > 72) {
            throw $this->erro($campo, "A {$rotulo} aceita no máximo 72 caracteres.");
        }

        return $valor;
    }

    public function email(string $campo, string $rotulo): string
    {
        $valor = $this->texto($campo, $rotulo, true, 150);

        if (filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            throw $this->erro($campo, "Informe um {$rotulo} válido, ex.: nome@dominio.com.");
        }

        return mb_strtolower($valor);
    }

    public function telefone(string $campo, string $rotulo): string
    {
        $valor = Format::onlyNumbers((string) $this->texto($campo, $rotulo, true, 30));
        $tamanho = strlen($valor);

        if ($tamanho < 10 || $tamanho > 13) {
            throw $this->erro($campo, "Informe um {$rotulo} com DDD, ex.: (51) 99999-9999.");
        }

        return $valor;
    }

    public function decimal(string $campo, string $rotulo, bool $obrigatorio = true, bool $permiteZero = false, float $maximo = 99999999.99): ?float
    {
        if (!$this->tem($campo)) {
            $this->exigir($campo, $rotulo, $obrigatorio);
            return null;
        }

        $valor = $this->dados[$campo];

        if (is_string($valor) && str_contains($valor, ",")) {
            $valor = str_replace([".", ","], ["", "."], trim($valor));
        }

        if (!is_numeric($valor)) {
            throw $this->erro($campo, "O campo {$rotulo} deve ser um número, ex.: 12.50.");
        }

        $numero = (float) $valor;

        if ($numero < 0 || (!$permiteZero && $numero == 0.0)) {
            $regra = $permiteZero ? "não pode ser negativo" : "deve ser maior que zero";
            throw $this->erro($campo, "O campo {$rotulo} {$regra}.");
        }

        if ($numero > $maximo) {
            throw $this->erro($campo, "O campo {$rotulo} aceita no máximo {$maximo}.");
        }

        return $numero;
    }

    public function inteiro(string $campo, string $rotulo, bool $obrigatorio = true, int $minimo = 1, int $maximo = 2147483647): ?int
    {
        if (!$this->tem($campo)) {
            $this->exigir($campo, $rotulo, $obrigatorio);
            return null;
        }

        $numero = filter_var($this->dados[$campo], FILTER_VALIDATE_INT);

        if ($numero === false || $numero < $minimo || $numero > $maximo) {
            throw $this->erro($campo, "O campo {$rotulo} deve ser um número inteiro entre {$minimo} e {$maximo}.");
        }

        return $numero;
    }

    public function booleano(string $campo, string $rotulo, bool $padrao): bool
    {
        if (!isset($this->dados[$campo])) {
            return $padrao;
        }

        $valor = filter_var($this->dados[$campo], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($valor === null) {
            throw $this->erro($campo, "O campo {$rotulo} deve ser verdadeiro ou falso.");
        }

        return $valor;
    }

    public function enum(string $campo, string $rotulo, string $classe, bool $obrigatorio = true): ?BackedEnum
    {
        if (!$this->tem($campo)) {
            $this->exigir($campo, $rotulo, $obrigatorio);
            return null;
        }

        $valor = $this->dados[$campo];
        $opcao = is_string($valor) ? $classe::tryFrom($valor) : null;

        if ($opcao === null) {
            $opcoes = implode(", ", array_map(fn (BackedEnum $caso) => $caso->value, $classe::cases()));
            throw $this->erro($campo, "Valor inválido para {$rotulo}. Use um destes: {$opcoes}.");
        }

        return $opcao;
    }

    public function cor(string $campo, string $rotulo): string
    {
        $valor = (string) $this->texto($campo, $rotulo, true, 50);

        if (!preg_match("/^#[0-9A-Fa-f]{6}$/", $valor)) {
            throw $this->erro($campo, "Informe a {$rotulo} em hexadecimal, ex.: #1F2937.");
        }

        return strtoupper($valor);
    }

    public function data(string $campo, string $rotulo, bool $obrigatorio = false): ?DateTimeImmutable
    {
        return $this->dataNosFormatos($campo, $rotulo, $obrigatorio, ["!Y-m-d"], "AAAA-MM-DD, ex.: 2026-11-20");
    }

    public function dataHora(string $campo, string $rotulo, bool $obrigatorio = false): ?DateTimeImmutable
    {
        $formatos = ["Y-m-d H:i:s", "Y-m-d\\TH:i:s", "Y-m-d H:i", "Y-m-d\\TH:i", "!Y-m-d"];

        return $this->dataNosFormatos($campo, $rotulo, $obrigatorio, $formatos, "AAAA-MM-DD HH:MM, ex.: 2026-11-20 14:30");
    }

    public function listaTextos(string $campo, string $rotulo, int $maximoItens, int $maximoCaracteres): array
    {
        $lista = $this->lista($campo, $rotulo, false, $maximoItens);
        $textos = [];

        foreach (array_keys($lista) as $indice) {
            $Item = new self($lista, $this->nomeCampo($campo));
            $numero = $indice + 1;
            $textos[] = $Item->texto((string) $indice, "{$rotulo} (item {$numero})", true, $maximoCaracteres);
        }

        return array_values(array_unique($textos));
    }

    public function listaInteiros(string $campo, string $rotulo, int $maximoItens = 500): array
    {
        $lista = $this->lista($campo, $rotulo, false, $maximoItens);
        $numeros = [];

        foreach (array_keys($lista) as $indice) {
            $Item = new self($lista, $this->nomeCampo($campo));
            $numeros[] = $Item->inteiro((string) $indice, $rotulo);
        }

        return array_values(array_unique($numeros));
    }

    public function itens(string $campo, string $rotulo, int $maximoItens = 100): array
    {
        $lista = $this->lista($campo, $rotulo, true, $maximoItens);
        $itens = [];

        foreach ($lista as $indice => $item) {
            if (!is_array($item)) {
                throw $this->erro("{$campo}[{$indice}]", "Cada item de {$rotulo} deve ser um objeto.");
            }

            $prefixo = $this->nomeCampo($campo);
            $itens[] = new self($item, "{$prefixo}[{$indice}]");
        }

        return $itens;
    }

    private function lista(string $campo, string $rotulo, bool $obrigatorio, int $maximoItens): array
    {
        $valor = $this->dados[$campo] ?? [];

        if (!is_array($valor) || !array_is_list($valor)) {
            throw $this->erro($campo, "O campo {$rotulo} deve ser uma lista.");
        }

        if ($obrigatorio && count($valor) === 0) {
            throw $this->erro($campo, "Informe pelo menos um item em {$rotulo}.");
        }

        if (count($valor) > $maximoItens) {
            throw $this->erro($campo, "O campo {$rotulo} aceita no máximo {$maximoItens} itens.");
        }

        return $valor;
    }

    private function dataNosFormatos(string $campo, string $rotulo, bool $obrigatorio, array $formatos, string $exemplo): ?DateTimeImmutable
    {
        $valor = $this->texto($campo, $rotulo, $obrigatorio, 25);

        if ($valor === null) {
            return null;
        }

        foreach ($formatos as $formato) {
            $Data = DateTimeImmutable::createFromFormat($formato, $valor);
            $erros = DateTimeImmutable::getLastErrors();

            if ($Data !== false && ($erros === false || ($erros["warning_count"] === 0 && $erros["error_count"] === 0))) {
                return $Data;
            }
        }

        throw $this->erro($campo, "Informe {$rotulo} no formato {$exemplo}.");
    }

    private function exigir(string $campo, string $rotulo, bool $obrigatorio): void
    {
        if ($obrigatorio) {
            throw $this->erro($campo, "Informe o campo {$rotulo}.");
        }
    }

    private function nomeCampo(string $campo): string
    {
        if ($this->prefixo === "") {
            return $campo;
        }

        if (ctype_digit($campo)) {
            return "{$this->prefixo}[{$campo}]";
        }

        return "{$this->prefixo}.{$campo}";
    }

    private function erro(string $campo, string $mensagem): ValidacaoException
    {
        return new ValidacaoException($mensagem, $this->nomeCampo($campo));
    }

    private static function lerCorpo(): array
    {
        $tipoConteudo = strtolower($_SERVER["CONTENT_TYPE"] ?? "");
        $metodo = strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");

        if (str_contains($tipoConteudo, "application/json")) {
            $conteudo = (string) file_get_contents("php://input");

            if (trim($conteudo) === "") {
                return [];
            }

            $dados = json_decode($conteudo, true);

            if (!is_array($dados)) {
                throw new AppException("O corpo da requisição não é um JSON válido.", 400);
            }

            return $dados;
        }

        if ($metodo === "POST") {
            return $_POST;
        }

        parse_str((string) file_get_contents("php://input"), $dados);

        return $dados;
    }
}
