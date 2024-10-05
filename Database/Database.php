<?php

namespace CandlewaxGames\Database;

use PDO;

class Database
{
    public PDO $pdo;
    protected static Database $database;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function getInstance(): Database
    {
        if (!isset(self::$database)) {
            self::$database = new Database();
            self::$database->connect();
        }

        return self::$database;
    }

    protected function connect(): PDO
    {
        $this->pdo = new PDO(
            "mysql:dbname=" . getenv('DATABASE_NAME') . ";host=" . getenv('DATABASE_HOST'),
            getenv('DATABASE_USER'),
            getenv('DATABASE_PASSWORD')
        );
        return $this->pdo;
    }

    /**
     * Takes a sql query string and returns the result.
     *
     * @param string $sql The sql query string.
     * @param array|null $params The values for populating the sql statement with.
     * @param bool $fetchAll Whether to force PDO to call fetchAll instead of fetch.
     * @return array $result
     */
    public function query(string $sql, array $params = null, bool $fetchAll = false): array
    {
        // Show Database errors if we are in development mode.
        if (DEVELOPMENT_ENVIRONMENT) {
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        if ($params === null) {
            $query = $this->pdo->prepare($sql);
            $query->execute();
        } else {
            $query = $this->pdo->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
            $query->execute($params);
        }

        // Check if the statement produced results.
        if (str_starts_with(strtolower($sql), 'select')) {
            $result = $query->fetchAll();

            if (sizeof($result) == 1 && !$fetchAll) {
                return $result[0];
            } else {
                return $result;
            }
        } else {
            return array();
        }
    }
}
