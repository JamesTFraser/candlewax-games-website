<?php

namespace CandlewaxGames\Tests\Unit\Database;

use CandlewaxGames\Database\Database;
use CandlewaxGames\Database\Entity;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    private string $dbHost = 'localhost';
    private string $dbName = 'test';
    private string $dbUser = 'root';
    private string $dbPass = 'root';
    private Database $database;

    public function setUp(): void
    {
        parent::setUp();

        // Initialise the service we will be testing.
        $this->database = new Database();

        // Connect to the database if not already connected.
        if (!$this->database->connected()) {
            $this->database->connect($this->dbHost, $this->dbName, $this->dbUser, $this->dbPass);
        }

        // Create a test table.
        $this->database->pdo()->exec("CREATE TABLE IF NOT EXISTS $this->dbName (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `email` varchar(255) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    public function testCreate(): void
    {
        // Add some rows to the test table.
        $insertRows = [
            ['name' => 'test1', 'email' => 'a@a.com'],
            ['name' => 'test2', 'email' => 'b@b.com'],
        ];
        $entities = $this->database->create('test', $insertRows);

        // Assert the returned Entities contain the correct data.
        $this->assertReturnedEntities($entities, $insertRows);
    }

    public function testRead(): void
    {
        // Add some rows to the test table.
        $rows = [
            ['name' => 'test1', 'email' => 'a@a.com'],
            ['name' => 'test2', 'email' => 'b@b.com'],
        ];
        $query = $this->database->pdo()->prepare("INSERT INTO `test` (`name`, `email`) VALUES (:name, :email)");
        foreach ($rows as $row) {
            $query->execute($row);
        }

        // Read back the newly inserted rows.
        $entities = $this->database->read('test');

        // Assert the returned Entities contain the correct data.
        $this->assertReturnedEntities($entities, $rows);
    }

    public function testUpdate(): void
    {
        $insertRows = [
            ['name' => 'test1', 'email' => 'a@a.com'],
            ['name' => 'test2', 'email' => 'b@b.com'],
        ];
        $updateRows = [
            ['name' => 'update1', 'email' => 'c@c.com'],
            ['name' => 'update2', 'email' => 'd@d.com'],
        ];

        // Prepare a query to add rows which we will attempt to update.
        $query = $this->database->pdo()->prepare("INSERT INTO `test` (`name`, `email`) VALUES (:name, :email)");
        $entities = [];

        for ($i = 0; $i < 2; $i++) {
            // Add a new row to the table.
            $query->execute($insertRows[$i]);

            // Create an Entity to represent the newly created row.
            $entities[$i] = new Entity('test', $i + 1, $insertRows[$i]);

            // Update the Entities columns.
            $entities[$i]->name = $updateRows[$i]['name'];
            $entities[$i]->email = $updateRows[$i]['email'];
        }

        // Attempt to update the Entity.
        $this->database->update($entities);

        // Assert the test table rows have been updated with the correct values.
        foreach ($entities as $entity) {
            $query = $this->database->pdo()->prepare("SELECT * FROM `test` WHERE `id` = ?");
            $query->execute([$entity->id]);
            $result = $query->fetch();
            $this->assertEquals($result['name'], $updateRows[$entity->id - 1]['name']);
            $this->assertEquals($result['email'], $updateRows[$entity->id - 1]['email']);
        }
    }

    public function testDelete(): void
    {
        $rows = [
            ['name' => 'test1', 'email' => 'a@a.com'],
            ['name' => 'test2', 'email' => 'b@b.com'],
        ];

        // Insert some rows into the test table.
        $query = $this->database->pdo()->prepare("INSERT INTO `test` (`name`, `email`) VALUES (:name, :email)");
        foreach ($rows as $row) {
            $query->execute($row);
        }

        // Delete the rows we just inserted.
        $count = sizeof($rows);
        for ($i = 1; $i <= $count; $i++) {
            $this->database->delete('test', ['id' => $i]);
        }

        // Attempt to read back the rows to check if they have been deleted.
        $result = $this->database->pdo()->query("SELECT * FROM `test`")->fetchAll();
        $this->assertCount(0, $result);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        // Remove the test table.
        $this->database->pdo()->exec("DROP TABLE $this->dbName");
    }

    private function assertReturnedEntities(array $entities, array $expected): void
    {
        // Assert the Entities are returned and contain the correct data.
        $this->assertCount(2, $entities);
        $this->assertInstanceOf(Entity::class, $entities[0]);
        $this->assertInstanceOf(Entity::class, $entities[1]);
        $index = 0;
        foreach ($entities as $entity) {
            $this->assertArrayHasKey('name', $entity->getColumns());
            $this->assertArrayHasKey('email', $entity->getColumns());
            $this->assertEquals($entity->getColumns()['name'], $expected[$index]['name']);
            $this->assertEquals($entity->getColumns()['email'], $expected[$index]['email']);
            $index++;
        }

        // Assert the Entities accurately represent their corresponding rows.
        $rows = $this->database->pdo()->query("SELECT * FROM `test`")->fetchAll();
        $this->assertCount(2, $rows);
        for ($i = 0; $i < 2; $i++) {
            $this->assertEquals($rows[$i]['id'], $entities[$i]->id);
            $this->assertEquals($rows[$i]['name'], $entities[$i]->getColumns()['name']);
            $this->assertEquals($rows[$i]['email'], $entities[$i]->getColumns()['email']);
        }
    }
}
