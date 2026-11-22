<?php

namespace Utils;

use Model\Exception\AppException;

class FiltrosPadrao
{
    private int $limit = 100;
    private int $offset = 0;

    private ?string $createdAtStart = null;
    private ?string $createdAtEnd = null;
    private ?string $updatedAtStart = null;
    private ?string $updatedAtEnd = null;
    private Object $especificos;
    private Array $orderBy = [];

    private Array $idsPublico = [];

    public function __construct()
    {
        $this->especificos = (Object) [];
    }

    public function autoSet(): self
    {
        if (isset($_GET["order"])) {
            $this->setOrderBy($_GET["order"]);
            unset($_GET["order"]);
        }

        foreach ($_GET as $key => $value) {
            if ($value === null) {
                continue;
            }

            $key = Format::fixCamelCase($key);
            if (!property_exists($this, $key)) {
                $this->especificos->$key = $value;
                continue;
            }

            $metodo = "set". ucfirst($key);
            $this->$metodo($value);
        }

        return $this;
    }

    public function setEspecifico(string $key, $value): self
    {
        $this->especificos->$key = $value;

        return $this;
    }

    private function setOrderBy(Array $orders): void
    {
        $orderFixed = [];
        foreach ($orders as $field => $direction) {
            if (!$field || is_numeric($field)) {
                continue;
            }

            $direction = trim($direction);

            if (
                !isset($direction) ||
                !in_array(strtolower($direction), ["asc", "desc"])
            ) {
                $direction = "desc";
            }

            $direction = strtoupper($direction);

            $orderFixed[$field] = $direction;
        }

        $this->orderBy = $orderFixed;
    }

    public function getFixedOrderBy(string $sql): string
    {
        $orderBy = $this->orderBy;
        $orderFixed = [];

        foreach ($orderBy as $field => $direction) {
            if (strpos($sql, $field) === false) {
                continue;
            }

            $orderFixed[] = "{$field} {$direction}";
        }

        return implode(", ", $orderFixed);
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function getCreatedAtStart(): ?string
    {
        return $this->createdAtStart;
    }

    public function setCreatedAtStart(?string $createdAtStart): self
    {
        if (!Date::isValid($createdAtStart)) {
            throw new AppException("Formato da data de criação inicial inválida");
        }

        $this->createdAtStart = $createdAtStart;
        if (!$this->createdAtEnd) {
            $createdAtEnd = explode(" ", $createdAtStart);
            $createdAtEnd[1] = "23:59:59";
            $createdAtEnd = implode(" ", $createdAtEnd);
            $this->setCreatedAtEnd($createdAtEnd);
        }

        return $this;
    }

    public function getCreatedAtEnd(): ?string
    {
        return $this->createdAtEnd;
    }

    public function setCreatedAtEnd(?string $createdAtEnd): self
    {
        if (!Date::isValid($createdAtEnd)) {
            throw new AppException("Formato da data de criação final inválida");
        }
        $this->createdAtEnd = $createdAtEnd;

        return $this;
    }

    public function getUpdatedAtStart(): ?string
    {
        return $this->updatedAtStart;
    }

    public function setUpdatedAtStart(?string $updatedAtStart): self
    {
        if (!Date::isValid($updatedAtStart)) {
            throw new AppException("Formato da data de atualização inicial inválida");
        }

        $this->updatedAtStart = $updatedAtStart;
        if (!$this->updatedAtEnd) {
            $updatedAtEnd = explode(" ", $updatedAtStart);
            $updatedAtEnd[1] = "23:59:59";
            $updatedAtEnd = implode(" ", $updatedAtEnd);
            $this->setUpdatedAtEnd($updatedAtEnd);
        }

        return $this;
    }

    public function getUpdatedAtEnd(): ?string
    {
        return $this->updatedAtEnd;
    }

    public function setUpdatedAtEnd(?string $updatedAtEnd): self
    {
        if (!Date::isValid($updatedAtEnd)) {
            throw new AppException("Formato da data de criação final inválida");
        }
        $this->updatedAtEnd = $updatedAtEnd;

        return $this;
    }

    public function getIdsPublico(): Array
    {
        return $this->idsPublico;
    }

    public function setIdsPublico(Array $idsPublico): self
    {
        $this->idsPublico = $idsPublico;

        return $this;
    }

    public function getEspecificos(): Object
    {
        return $this->especificos;
    }

    public function hasOrderBy(): bool
    {
        return count($this->orderBy) > 0;
    }
}
