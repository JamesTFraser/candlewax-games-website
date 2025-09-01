<?php

namespace CandlewaxGames\Services;

use CandlewaxGames\Database\Database;

class Game
{
    public string $gamesTable = 'games';
    public string $screenshotsTable = 'game_screenshots';
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function find(array $where = []): array
    {
        return $this->database->read($this->gamesTable, $where);
    }

    public function getScreenshots(array $where): array
    {
        return $this->database->read($this->screenshotsTable, $where);
    }
}
