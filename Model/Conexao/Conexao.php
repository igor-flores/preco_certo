<?php

namespace Model\Conexao;

use Model\Configuration\Configuration;
use Model\Exception\AppException;
use Utils\Utils;

class Conexao
{
    private static $database;
    private static $con;
    private static string $lastSQL = "";
    private static $lastQuery;

    public function __construct()
    {
        if (isset(self::$con)) {
            return;
        }

        self::connect();
    }

    public static function connect()
    {
        $Configuration = Configuration::get();
        $host = $Configuration->dbHost;
        $port = $Configuration->dbPort;
        $database = $Configuration->dbName;
        $user = $Configuration->dbUser;
        $password = $Configuration->dbPass;

        self::setDatabase($database);

        try {
            self::$con = new \PDO("mysql:dbname={$database};host={$host};port={$port}", $user, $password);
            self::$con->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::setCharset();
            self::setTimezone();
        } catch (\PDOException $e) {
            Utils::logError(["conexao" => $e->getMessage()]);
            throw new AppException("Não foi possível conectar ao banco de dados. Tente novamente em instantes.", 503);
        }
    }

    public static function setDatabase(string $database): void
    {
        self::$database = $database;
    }

    public static function getDatabase(): string
    {
        return self::$database;
    }

    protected static function setCharset($charset = "utf8mb4")
    {
        $sql = "SET names {$charset}";
        self::$con->query($sql);
    }

    protected static function setTimezone()
    {
        $fusoHorario = date("P");
        $sql = "SET time_zone = '{$fusoHorario}'";
        self::$con->query($sql);
    }

    public static function startTransaction()
    {
        return self::$con->beginTransaction();
    }

    public static function commit()
    {
        return self::$con->commit();
    }

    public static function rollback()
    {
        return self::$con->rollback();
    }

    public static function inTransaction(): bool
    {
        return self::$con->inTransaction();
    }

    public static function getError()
    {
        $lastQueryError = self::$lastQuery ? self::$lastQuery->errorInfo() : [];
        if (self::isErrorInfo($lastQueryError)) {
            return $lastQueryError;
        }

        return self::$con->errorInfo();
    }

    public static function hasError(): bool
    {
        return self::isErrorInfo(self::getError());
    }

    private static function isErrorInfo(Array $errorInfo): bool
    {
        return !empty($errorInfo[0]) && $errorInfo[0] !== "00000";
    }

    public static function query($sql)
    {
        try {
            $query = self::$con->query($sql);
            self::$lastQuery = $query;

            return $query;
        } catch (\PDOException $e) {
            // var_dump($sql);
            // var_dump($e->getMessage());
        }
    }

    public static function prepare($sql)
    {
        try {
            self::$lastSQL = $sql;
            $prepare = self::$con->prepare($sql, [\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY]);
            self::$lastQuery = $prepare;

            return $prepare;
        } catch (\PDOException $e) {
        }
    }

    public static function execute(Array $args = [])
    {
        $binds = [];
        foreach ($args as $key => $value) {
            $key = (string) $key;
            if (strpos($key, ":") !== 0) {
                $key = ":{$key}";
            }

            $keyRegex = preg_quote($key, "/");
            if (!preg_match("/{$keyRegex}(?![A-Za-z0-9_])/", self::$lastSQL)) {
                continue;
            }

            if (is_bool($value)) {
                $value = (int) $value;
            }

            $binds[$key] = $value;
        }

        try {
            return self::$lastQuery->execute($binds);
        } catch (\PDOException $e) {
            Utils::logError([
                "sql" => Utils::joinLines(self::$lastSQL),
                "error" => self::getError(),
                "data" => $binds,
                "exception" => $e->getMessage()
            ]);
        }
    }

    public static function rowCount($query = null)
    {
        if (!$query) {
            $query = self::$lastQuery;
        }

        return $query->rowCount();
    }

    public static function fetch($query = null)
    {
        if (!$query) {
            $query = self::$lastQuery;
        }

        return $query->fetch(\PDO::FETCH_OBJ);
    }

    public static function fetchAll($query = null)
    {
        if (!$query) {
            $query = self::$lastQuery;
        }

        return $query->fetchAll(\PDO::FETCH_OBJ);
    }

    public static function getLastInsertId()
    {
        return self::$con->lastInsertId();
    }

    public static function closeConnection()
    {
        if (self::$con === null) {
            return;
        }
        self::$con = null;
    }

    public static function getVersion()
    {
        if (self::$con === null) {
            self::connect();
        }
        self::query("SELECT VERSION() AS versao");
        return self::fetch()->versao;
    }

    public static function getVersionConnector()
    {
        return self::$con->getAttribute(\PDO::ATTR_CLIENT_VERSION);
    }
}
