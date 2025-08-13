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

    public function read(string $table, array $columns = [], string $comparison = '='): array
    {
        // Construct the SELECT part of the query. If no columns were specified, exclude the WHERE clause.
        $query = !empty($columns) ? "select * from $table where " : "select * from $table";

        // Loop through the given columns and construct the WHERE part of the query.
        foreach ($columns as $column => $value) {
            $query .= "`$column` $comparison :$column AND ";
        }

        // If columns were added to the string, remove the trailing AND.
        $query = !empty($columns) ? substr($query, 0, -4) : $query;

        // Execute the query and retrieve the results.
        return $this->fetchEntities($table, $query, $columns);
    }

    public function readLeftJoin(
        string $table,
        array $joinColumns,
        array $whereColumns = [],
        string $orderBy = '',
        int $limit = 0,
        int $offset = 0,
        string $comparison = '='
    ): array {
        // Alias the joined table ids to prevent them being overwritten in the result array.
        $query = $this->joinSelectAliases($table, $joinColumns);

        // Loop through the joinColumn pairs and add them to the query.
        $query .= $this->constructJoinString($table, $joinColumns);

        // If where conditions where specified, add them to the query.
        $values = [];
        $query .= !empty($whereColumns) ? $this->constructWhereString($whereColumns, $comparison, $values) : '';

        // If an order by table was passed, add it to the query.
        $query .= $orderBy !== '' ? ' order by ' . $orderBy : '';

        // If the given limit was not zero, add it to the query.
        $query .= $limit !== 0 ? ' limit ' . $limit : '';

        // If the given offset was not zero, add it to the query.
        $query .= $offset !== 0 ? ' offset ' . $offset : '';

        return $this->fetchEntities($table, $query, $values);
    }

    public function readRecursive(
        string $table,
        array $whereColumns,
        array $unionColumns,
        array $joinColumns,
        string $orderBy = ''
    ): array {
        $query = "with recursive virtual_table as (select * from $table";

        // Loop through the given columns and construct the WHERE part of the query.
        $query .= !empty($whereColumns) ? ' where ' : '';
        foreach ($whereColumns as $column => $value) {
            $query .= "`$column` = :$column and ";
        }

        // If columns were added to the string, remove the trailing AND.
        $query = !empty($whereColumns) ? substr($query, 0, -4) : $query;

        // Add the union part of the query.
        $query .=
            " union all select $table.* 
            from $table 
            inner join virtual_table 
            on $table.$unionColumns[0] = virtual_table.$unionColumns[1])";
        $query .= $this->joinSelectAliases('virtual_table', $joinColumns, $table);

        // Loop through the joinColumn pairs and add them to the query.
        $query .= $this->constructJoinString('virtual_table', $joinColumns);

        // If an order by table was passed, add it to the query.
        $orderBy = str_replace($table, 'virtual_table', $orderBy);
        $query .= $orderBy !== '' ? ' order by ' . $orderBy : '';

        return $this->fetchEntities($table, $query, $whereColumns);
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

    public function count(string $table, array $where = []): int
    {
        $values = [];
        $query = "select count(id) from $table";
        $query .= !empty($where) ? $this->constructWhereString($where, '=', $values) : '';

        // Execute the query and retrieve the result count.
        $statement = $this->pdo()->prepare($query);
        $statement->execute($values);
        return $statement->fetchColumn();
    }

    public function findRoot(string $table, int $rootId): ?Entity
    {
        $query = "with recursive chain as ( 
        select * from $table 
        where id = :id 
        union all 
        select t.* from $table t 
        join chain on t.id = chain.parent_id 
        ) select * from chain where parent_id is null limit 1";

        $rootRow = $this->fetchEntities($table, $query, ['id' => $rootId]);
        return $rootRow[0] ?? null;
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

    private function constructWhereString(array $columns, string $comparison = '=', array &$values = null): string
    {
        // Loop through the where columns and construct the WHERE part of the query.
        $query = ' where ';
        $values = [];
        foreach ($columns as $column => $value) {
            // Make sure a null value is constructed in the query properly.
            if ($value === null) {
                $query .= "$column is null and ";
                continue;
            }
            $query .= "$column $comparison ? and ";

            // Add the current columns value to the array in the same order it appears in the query.
            $values[] = $value;
        }

        // Remove the trailing 'AND' and return the query string.
        return substr($query, 0, -4);
    }

    private function constructJoinString(string $table, array $columns): string
    {
        // Loop through the joinColumn pairs and add them to the query string.
        $query = '';
        foreach ($columns as $joinTable => $columnPair) {
            $query .= " left join $joinTable on $table.$columnPair[0] = $joinTable.$columnPair[1]";
        }
        return $query;
    }

    private function joinSelectAliases(string $table, array $joinColumns, string $tableAlias = null): string
    {
        // Alias the joined table ids to prevent them being overwritten in the result array.
        $alias = $tableAlias ?? $table;
        $query = "select *, $table.id as {$alias}_id";
        $query .= ", $table.created_at as {$alias}_created_at";
        $query .= ", $table.updated_at as {$alias}_updated_at";
        foreach ($joinColumns as $joinTable => $columnPairs) {
            $query .= ", $joinTable.id as {$joinTable}_id";
            $query .= ", $joinTable.created_at as {$joinTable}_created_at";
            $query .= ", $joinTable.updated_at as {$joinTable}_updated_at";
        }
        $query .= " from $table";
        return $query;
    }
}
