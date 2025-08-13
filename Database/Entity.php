<?php

namespace CandlewaxGames\Database;

use Exception;

class Entity
{
    public readonly string $table;
    public readonly int $id;
    private array $columns;

    public function __construct(string $table, int $id, array $columns)
    {
        $this->table = $table;
        $this->id = $id;
        $this->columns = $columns;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @throws Exception
     */
    public function __get(string $column): mixed
    {
        // Check the given column exists.
        if (!isset($this->columns[$column])) {
            throw new Exception("Column '$column' does not exist in table '$this->table'.");
        }

        // Return the column value.
        return $this->columns[$column];
    }

    /**
     * Updates the given $column with the given $value.
     *
     * @param string $column The column to update.
     * @param mixed $value The columns new value.
     * @throws Exception If the column to be updated does not exist.
     */
    public function __set(string $column, mixed $value): void
    {
        // Check the given column exists.
        if (!array_key_exists($column, $this->columns)) {
            throw new Exception("Column '$column' does not exist in table '$this->table'.");
        }

        // Update the column.
        $this->columns[$column] = $value;
    }

    public function __isset(string $column): bool
    {
        return isset($this->columns[$column]);
    }
}
