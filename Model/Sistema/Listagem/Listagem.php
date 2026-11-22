<?php

namespace Model\Sistema\Listagem;

use Model\Exception\AppException;

class Listagem
{
    private $sql;

    private $columnsInfoAux = [];
    private $columns = [];
    private $conditions = [];
    private $orderBy = [];

    private Array $binds = [];

    public function setSQL($sql)
    {
        $this->sql = $sql;
    }

    private function auxInfoColumns()
    {
        if (count($this->columnsInfoAux) > 0) {
            return $this->columnsInfoAux;
        }

        $sql = $this->sql;
        $words = explode(" ", $sql);
        foreach ($words as $key => $word) {
            if (!$word) {
                unset($words[$key]);
            }
        }
        array_shift($words);

        $columns = [];
        $lastWord = "";
        $numberOpenParentese = 0;
        $numberCloseParentese = 0;

        $numberOpenQuery = 0;
        $numberCloseQuery = 0;

        $lengthWords = count($words);

        foreach ($words as $key => $word) {
            $auxWord = trim("{$lastWord} {$word}");
            $numberOpenQuery = substr_count(strtolower($auxWord), "select");
            $numberCloseQuery = substr_count(strtolower($auxWord), "from");
            $numberOpenParentese = substr_count($auxWord, "(");
            $numberCloseParentese = substr_count($auxWord, ")");

            if (
                (
                    $numberOpenParentese === $numberCloseParentese &&
                    $numberCloseQuery > 0 && substr_count(strtolower($word), "from")
                ) ||
                ($lengthWords -1) == $key
            ) {
                $word = trim($auxWord);

                $lastWord = substr($word, strlen($word) -4, 4);
                $lastWord = strtolower($lastWord);
                if ($lastWord === "from") {
                    $word = trim(substr($word, 0, -4));
                }

                $columns[$this->getAlias($word)] = $this->removeAlias($word);
                break;
            }

            if ($numberOpenQuery === $numberCloseQuery) {
                $numberOpenQuery = 0;
                $numberCloseQuery = 0;
            }

            if (substr(trim($word), -1) !== ",") {
                $lastWord .= " {$word}";
                continue;
            }

            $word = trim("{$lastWord} {$word}");

            if ($numberOpenParentese === $numberCloseParentese) {
                $numberOpenParentese = 0;
                $numberCloseParentese = 0;
            }

            if ($numberOpenParentese !== $numberCloseParentese) {
                $lastWord = " {$word}";
                continue;
            }

            if (substr($word, -1) === "," && $numberOpenParentese === 0) {
                $lastWord = "";
            }

            if (substr($word, -1) === ",") {
                $word = substr($word, 0, -1);
            }
            $columns[$this->getAlias($word)] = $this->removeAlias($word);
        }

        $this->columnsInfoAux = $columns;
        return $columns;
    }

    private function getAlias($string)
    {
        if (strpos(strtolower($string), " as ") !== false) {
            if (substr_count(strtolower($string), "as") === 1) {
                $alias = substr($string, 17, strlen($string));
                $alias = preg_replace('/(.*)(as|AS)/m', "", $string);
            } else {
                $explode = explode(" ", $string);
                $alias = end($explode);
            }
        } else {
            $alias = preg_replace('/(.*)\./m', "", $string);
        }
        $alias = str_replace(['`', '"', "'"], "", $alias);

        return trim($alias);
    }

    private function removeAlias($string)
    {
        if (strpos(strtolower($string), " as ") === false) {
            return trim($string);
        }

        $explode = explode(" ", $string);
        $countExplode = count($explode);

        unset($explode[$countExplode -1]);
        unset($explode[$countExplode -2]);
        $alias = implode(" ", $explode);

        return trim($alias);
    }

    public function fixWhere()
    {
        $sql = $this->sql;
        $where = $this->getWhere();

        if (empty($where)) {
            return $sql;
        }

        $posUltimoFrom = strripos(strtolower($sql), "from");
        $sqlDepoisUltimoFrom = substr($sql, $posUltimoFrom + strlen("from"));
        $posUltimoWhere = strripos(strtolower($sql), "where");

        $hasGrouByAfterFrom = strpos(strtolower($sqlDepoisUltimoFrom), "group by");
        $hasOrderByAfterFrom = strpos(strtolower($sqlDepoisUltimoFrom), "order by");
        $hasNotWhereAfterFrom = !strpos(strtolower($sqlDepoisUltimoFrom), "where");

        if ($hasGrouByAfterFrom) {
            $sql = $this->appendWhereAfterFromWithGroupBy($posUltimoWhere, $sqlDepoisUltimoFrom);
        } elseif ($hasOrderByAfterFrom) {
            $sql = $this->appendWhereAfterFromWithOrderBy($posUltimoWhere, $sqlDepoisUltimoFrom);
        } elseif ($hasNotWhereAfterFrom) {
            $sql = "{$this->sql} WHERE [[WHERE]]";
        } else {
            $sql = "{$this->sql} AND [[WHERE]]";
        }

        $sql = str_replace('[[WHERE]]', $where. PHP_EOL, $sql);

        return $sql;
    }

    private function appendWhereAfterFromWithGroupBy($posUltimoWhere, $sqlDepoisUltimoFrom)
    {
        $sql = $this->sql;
        $posGroupBy = strripos(strtolower($sql), "group by");

        $groupByAfterWhere = $posUltimoWhere < $posGroupBy;

        if ($groupByAfterWhere) {
            $stringAntesGroupBy = substr($sql, 0, $posGroupBy);
            $stringDepoisGroupBy = substr($sql, $posGroupBy);

            $hasNotWhereAfterGroupBy = !strpos(strtolower($sqlDepoisUltimoFrom), "where");

            if ($hasNotWhereAfterGroupBy) {
                return "{$stringAntesGroupBy} WHERE [[WHERE]] {$stringDepoisGroupBy}";
            }

            return "{$stringAntesGroupBy} AND [[WHERE]] {$stringDepoisGroupBy}";
        }

        $hasNotWhereAfterFrom = !stripos($sqlDepoisUltimoFrom, "where");
        if ($hasNotWhereAfterFrom) {
            return "{$sql} WHERE [[WHERE]]";
        }

        return "{$sql} AND [[WHERE]]";
    }

    private function appendWhereAfterFromWithOrderBy($posUltimoWhere, $sqlDepoisUltimoFrom)
    {
        $sql = $this->sql;
        $posOrderBy = strripos(strtolower($sql), "order by");

        $orderByAfterWhere = $posUltimoWhere < $posOrderBy;

        if ($orderByAfterWhere) {
            $stringAntesOrderBy = substr($sql, 0, $posOrderBy);
            $stringDepoisOrderBy = substr($sql, $posOrderBy);

            $hasNotWhereAfterOrderBy = !strpos(strtolower($sqlDepoisUltimoFrom), "where");

            if ($hasNotWhereAfterOrderBy) {
                return "{$stringAntesOrderBy} WHERE [[WHERE]] {$stringDepoisOrderBy}";
            }

            return "{$stringAntesOrderBy} AND [[WHERE]] {$stringDepoisOrderBy}";
        }

        $hasNotWhereAfterFrom = !stripos($sqlDepoisUltimoFrom, "where");
        if ($hasNotWhereAfterFrom) {
            return "{$sql} where [[WHERE]]";
        }

        return "{$sql} AND [[WHERE]]";
    }

    private function getWhere()
    {
        $conditions = $this->conditions;
        if (count($conditions) === 0) {
            return "";
        }

        $where = implode(" AND". PHP_EOL, $conditions);

        return $where;
    }

    public function getBinds()
    {
        return $this->binds;
    }

    public function getOrderBy()
    {
        $order = $this->orderBy;
        if (count($order) === 0) {
            return "";
        }

        $order = implode(", ", $order);

        return "ORDER BY {$order}";
    }

    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    public function setColumn($column = "")
    {
        $this->columns[] = $column;
    }

    public function setPage($page = "")
    {
        $this->page = $page;
    }

    public function setEqualCondition($column, $value)
    {
        $columns = $this->auxInfoColumns();

        if (!isset($columns[$column])) {
            return;
        }

        if (strpos($value, "*") !== false) {
            $value = str_replace('*', '%', $value);
            $this->conditions[] = "{$columns[$column]} LIKE '{$value}'";
            return;
        }
        $this->conditions[] = "{$columns[$column]} = '{$value}'";
    }

    public function setBetweenCondition($column, $value1, $value2)
    {
        $columns = $this->auxInfoColumns();
        if (!$value1 || !$value2) {
            return;
        }

        if (!isset($columns[$column])) {
            return;
        }

        $this->conditions[] = "({$columns[$column]} BETWEEN :{$column}1 AND :{$column}2)";
        $this->binds[":{$column}1"] = $value1;
        $this->binds[":{$column}2"] = $value2;
    }

    public function setOrderBy($column, $sort)
    {
        if (!in_array($sort, ["ASC", "DESC"])) {
            return;
        }
        $this->orderBy[] = "{$column} {$sort}";
    }
}
