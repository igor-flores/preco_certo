<?php

namespace Model;

use Model\Conexao\Conexao;
use Model\Exception\AppException;
use Model\Sistema\Listagem\Listagem;
use Model\Usuario\Usuario;
use Utils\FiltrosPadrao;
use Utils\Utils;

abstract class Model extends Conexao
{
    protected static int $limitDefault = 101;
    protected bool $setData = false;
    protected bool $showCaminhoImagem = false;

    private static bool $firstInstance = false;
    private static ?Usuario $Usuario = null;

    public function __construct()
    {
        parent::__construct();

        if (isset($_SESSION["idUsuario"]) && is_int($_SESSION["idUsuario"]) && self::$Usuario === null && !self::$firstInstance) {
            self::$firstInstance = true;
            $this->setUsuario($_SESSION["idUsuario"]);
        }
    }

    public function get(): ?Object
    {
        return null;
    }

    public function getAttributeType(string $atributo): ?string
    {
        $class = get_class($this);

        $ReflectionClass = new \ReflectionClass($class);
        $propriedades = $ReflectionClass->getProperties();

        foreach ($propriedades as $propriedade) {
            if ($propriedade->getName() === $atributo) {
                return $propriedade->getType() ? strtolower($propriedade->getType()->getName()) : null;
            }
        }

        return null;
    }

    public function setData(): static
    {
        if ($this->setData) {
            return $this;
        }
        $this->setData = true;
        $this->setAttributes($this->get());

        return $this;
    }

    protected function getAttributes(bool $exceptId = false): Array
    {
        $class = get_class($this);
        $attributes = (Array) $this;
        $data = [];
        foreach ($attributes as $column => $value) {
            $column = str_replace([$class, "*"], "", $column);
            $column = trim($column);

            if ($exceptId && $column === "id") {
                continue;
            }

            if ($value instanceof \UnitEnum) {
                $value = $value->value;
            }

            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            $data[$column] = $value;
        }

        return $data;
    }

    protected function setAttributes(Object $data): void
    {
        $attributes = $this->getAttributes();
        $attributes = array_keys($attributes);
        foreach ($data as $column => $value) {

            $columnToObject = "id". ucfirst($column);
            if (is_object($value) && in_array($columnToObject, $attributes)) {
                $column = $columnToObject;
                $value = $value?->id;
            }

            if (!in_array($column, $attributes)) {
                continue;
            }

            if (strpos((string) $this->getAttributeType($column), "\\") !== false) {
                continue;
            }
            if ($this->getAttributeType($column) !== "array") {
                $this->$column = $value;
                continue;
            }

            $this->$column = [];
            if (!is_array($value)) {
                continue;
            }

            foreach ($value as $row) {
                $this->$column[] = $row->id ?? $row;
            }
        }
    }

    protected function fixGetAll(string $sql, ?FiltrosPadrao $FiltrosPadrao = null, Array $args = []): Object
    {
        if (!$FiltrosPadrao) {
            $FiltrosPadrao = new FiltrosPadrao();
        }

        $limitDefault = self::$limitDefault;
        if ($FiltrosPadrao->getLimit() > $limitDefault) {
            throw new AppException("Limite maior que {$limitDefault} não permitido");
        }

        if (strpos(strtolower($sql), "order by") === false) {
            if ($FiltrosPadrao->hasOrderBy()) {
                $sql .= "
                    ORDER BY
                        ". $FiltrosPadrao->getFixedOrderBy($sql);
            } elseif (strpos(strtolower($sql), "as createdat") !== false) {
                $sql .= "
                    ORDER BY
                        createdAt DESC, id DESC
                ";
            } elseif (strpos(strtolower($sql), "as updatedat") !== false) {
                $sql .= "
                    ORDER BY
                        updatedAt DESC, id DESC
                ";
            }
        }

        if (strpos(strtolower($sql), "limit") === false) {
            $sql .= "
                LIMIT
                    {$FiltrosPadrao->getLimit()}
                OFFSET
                    {$FiltrosPadrao->getOffset()}
            ";
        }

        $Listagem = new Listagem();
        $Listagem->setSQL($sql);
        $Listagem->setBetweenCondition("createdAt", $FiltrosPadrao->getCreatedAtStart(), $FiltrosPadrao->getCreatedAtEnd());
        $Listagem->setBetweenCondition("updatedAt", $FiltrosPadrao->getUpdatedAtStart(), $FiltrosPadrao->getUpdatedAtEnd());
        $sql = $Listagem->fixWhere();
        $args = array_merge($args, $Listagem->getBinds());
        $this->prepare($sql);
        $this->execute($args);

        $response = (Object) [
            "data" => [],
            "limit" => $FiltrosPadrao->getLimit(),
            "offset" => $FiltrosPadrao->getOffset(),
            "total" => 0
        ];

        if ($this->rowCount() === 0) {
            return $response;
        }

        $data = $this->fetchAll();
        foreach ($data as &$row) {
            $row = $this->convertData($row);
        }


        $response->data = $data;
        $response->total = count($data);

        return $response;
    }

    protected function convertData(Object|Array $data): Object|Array
    {
        foreach ($data as $column => &$value) {
            if (
                strpos($column, "caminho") === false &&
                (
                    strpos(strtolower($column), "foto") !== false ||
                    strpos(strtolower($column), "logo") !== false
                )
            ) {
                $value = $this->fixUrlImagem($value, $data->idPublico ?? null);
                continue;
            }

            $attributeType = $this->getAttributeType($column);
            if ($attributeType === "object") {
                $value = $value ?: "{}";
                $value = json_decode($value);
            }
            if ($attributeType === "array") {
                $value = $value ?: "[]";
                $value = json_decode($value);
            }
            if ($attributeType === "bool") {
                $value = (bool) $value;
            }
            if ($attributeType === "int" && $value !== null) {
                $value = (int) $value;
            }
            if ($attributeType === "float" && $value !== null) {
                $value = (float) $value;
            }

            if (is_string($value) && in_array(substr(ltrim($value), 0, 1), ["{", "["], true) && Utils::isJson($value)) {
                $value = json_decode($value);
            }
        }

        return $data;
    }

    protected function fixUrlImagem(?string $caminho, ?string $idPublico, ?Object $class = null): ?string
    {
        if (!$caminho || !file_exists($caminho)) {
            return null;
        }

        $class = $class ?: $this;
        $class = get_class($class);
        $class = explode("\\", $class);
        $class = end($class);
        $class = Utils::camelToSnake($class);

        return _PATH. "imagens/{$class}/{$idPublico}";
    }

    protected static function bindList(string $nome, Array $valores, Array &$bind): string
    {
        $marcadores = [];
        foreach (array_values($valores) as $indice => $valor) {
            $chave = ":{$nome}{$indice}";
            $marcadores[] = $chave;
            $bind[$chave] = $valor;
        }

        return implode(", ", $marcadores);
    }

    protected function checkIdUsuario(): void
    {
        if (empty($this->idUsuario)) {
            throw new AppException("Necessário informar o usuário");
        }
    }

    public function getSetData(): bool
    {
        return $this->setData;
    }

    public function getShowCaminhoImagem(): bool
    {
        return $this->showCaminhoImagem;
    }

    public function setShowCaminhoImagem(bool $showCaminhoImagem = true): self
    {
        $this->showCaminhoImagem = $showCaminhoImagem;

        return $this;
    }

    public function getUsuario(): ?Usuario
    {
        return self::$Usuario;
    }

    public function setUsuario(int $idUsuario): self
    {
        self::$Usuario = new Usuario();
        self::$Usuario->setId($idUsuario);
        self::$Usuario->setData();

        return $this;
    }
}
