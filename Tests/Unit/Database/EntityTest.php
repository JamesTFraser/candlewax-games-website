<?php

namespace CandlewaxGames\Tests\Unit\Database;

use CandlewaxGames\Database\Entity;
use Exception;
use PHPUnit\Framework\TestCase;

class EntityTest extends TestCase
{
    public function testGetAndSet()
    {
        // Create an Entity to test.
        $data = ['name' => 'test', 'email' => 'test@test.com'];
        $entity = new Entity('test', 1, ['name' => '', 'email' => '']);

        // Update the columns.
        foreach ($data as $key => $value) {
            $entity->setColumn($key, $value);
        }

        // Retrieve the newly modified columns.
        $columns = $entity->getColumns();

        // Assert the return value is an array and has the correct number of entries.
        $this->assertIsArray($columns);
        $this->assertCount(sizeof($data), $columns);

        // Loop through the Entities columns and make sure the $columns array matches the $data array.
        foreach ($data as $key => $value) {
            $this->assertArrayHasKey($key, $columns);
            $this->assertEquals($value, $columns[$key]);
        }

        // Make sure we are unable to set a new array key using Entity->SetColumn.
        $this->expectException(Exception::class);
        $entity->setColumn('test', 'test');
    }
}
