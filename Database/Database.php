<?php

namespace CandlewaxGames\Database;

use Exception;
use PDO;
use PDOException;

class Database
{
    private static PDO $pdo;

    /**
     * Attempts to establish a connection using the given credentials.
     *
     * @param string $host     The address to the database.
     * @param string $database The name of the database.
     * @param string $username The username to connect to the database with.
     * @param string $password The password to connect to the database with.
     * @throws Exception If an existing connection is active.
     * @throws PDOException If the connection attempt failed.
     */
    public function connect(string $host, string $database, string $username, string $password): void
    {
        if (isset(self::$pdo)) {
            throw new Exception('A connection to the database already exists.');
        }

        $dsn = "mysql:dbname=$database;host=$host";
        self::$pdo = new PDO($dsn, $username, $password);
    }

    /**
     * Returns the singleton instance of the PDO object.
     *
     * @return PDO The PDO object used to prepare and execute SQL statements.
     * @throws Exception If no connection to the database has been created.
     */
    public function pdo(): PDO
    {
        if (!isset(self::$pdo)) {
            throw new Exception('No connection to the database exists.');
        }

        return self::$pdo;
    }

    /**
     * Checks if the database connection is currently established.
     *
     * @return bool True if the connection is established, false otherwise.
     */
    public function connected(): bool
    {
        return isset(self::$pdo);
    }

    public function create(string $table, array $rows): array
    {
        // Construct the INSERT part of the query string using the array keys of the first given row.
        $insert = "INSERT INTO $table (";
        foreach ($rows[0] as $column => $value) {
            $insert .= "$column, ";
        }
        $insert = substr($insert, 0, -2) . ') ';

        // Loop through the given rows.
        $values = "VALUES ";
        $data = [];
        foreach ($rows as $row) {
            // Add the value placeholders to the query string.
            $values .= '(';
            $columnCount = count($row);
            $values .= str_repeat('?, ', $columnCount);
            $values = substr($values, 0, -2) . '), ';

            // Sort the column values into an array to pass to PDO->execute().
            $data = array_merge($data, array_values($row));
        }

        // Remove the trailing comma.
        $values = substr($values, 0, -2);

        // Execute the query.
        $statement = $this->pdo()->prepare($insert . $values . " RETURNING id");
        $statement->execute($data);
        $result = $statement->fetchColumn();

        // Return the rows in the form of entities.
        $entities = [];
        $index = 0;
        foreach ($rows as $row) {
            $entities[] = new Entity($table, $result + $index, $row);
            $index++;
        }
        return $entities;
    }

    public function read(string $table, array $columns = []): array
    {
        // Construct the SELECT part of the query. If no columns were specified, exclude the WHERE clause.
        $query = !empty($columns) ? "select * from $table where " : "select * from $table";

        // Loop through the given columns and construct the WHERE part of the query.
        foreach ($columns as $column => $value) {
            $query .= "`$column` = :$column AND ";
        }

        // If columns were added to the string, remove the trailing AND.
        $query = !empty($columns) ? substr($query, 0, -4) : $query;

        // Execute the query and retrieve the results.
        return $this->fetchEntities($table, $query, $columns);
    }

    public function readLeftJoin(array $tables, array $joinColumns, array $whereColumns = []): array
    {
        $query = "select * from $tables[0] left join $tables[1] on $joinColumns[0] = $joinColumns[1]";
        $query .= !empty($whereColumns) ? ' where ' : '';

        // Loop through the where columns and construct the WHERE part of the query.
        foreach ($whereColumns as $column => $value) {
            $query .= "$column = ? AND ";
        }

        // If columns were added to the string, remove the trailing AND.
        $query = !empty($whereColumns) ? substr($query, 0, -4) : $query;

        return $this->fetchEntities($tables[0], $query, array_values($whereColumns));
    }

    public function update(array $entities): void
    {
        // Loop through the given Entities.
        foreach ($entities as $entity) {
            // Construct the UPDATE part of the query string and retrieve the current Entities columns.
            $query = "UPDATE $entity->table SET ";
            $data = $entity->getColumns();

            // Loop through the columns and construct the 'values' part of the query string.
            foreach ($data as $column => $value) {
                $query .= $column . " = :$column, ";
            }

            // Remove the trailing comma from the last value in the query string.
            $query = substr($query, 0, -2);
            $query .= " WHERE id = :id";

            // Prepare the query.
            $statement = $this->pdo()->prepare($query);

            // Include the Entities ID in the $columns array and execute the query.
            $data['id'] = $entity->id;
            $statement->execute($data);
        }
    }

    public function delete(string $table, array $columns): void
    {
        // Construct the DELETE FROM part of the query.
        $query = "DELETE FROM $table WHERE ";
        foreach ($columns as $column => $value) {
            $query .= "`$column` = :$column AND ";
        }

        // Remove the trailing comma from the last WHERE clause.
        $query = substr($query, 0, -4);

        // Prepare and execute the statement.
        $statement = $this->pdo()->prepare($query);
        $statement->execute($columns);
    }

    private function fetchEntities(string $table, string $query, array $values): array
    {
        // Execute the query and retrieve the results.
        $statement = $this->pdo()->prepare($query);
        $statement->execute($values);
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        $result = $statement->fetchAll();

        // Return the results in the form of entities.
        $entities = [];
        foreach ($result as $row) {
            $id = $row['id'];
            unset($row['id']);
            $entities[] = new Entity($table, $id, $row);
        }
        return $entities;
    }
}
