<?php

namespace CandlewaxGames\Controllers\Games;

use CandlewaxGames\Controllers\BaseController;
use CandlewaxGames\Services\Game;
use CandlewaxGames\Services\Response;

class CatalogueController extends BaseController
{
    private Game $gamesService;

    public function __construct(Game $gamesService, Response $response)
    {
        $this->gamesService = $gamesService;
        parent::__construct($response);
    }

    public function indexAction(): array
    {
        $games = $this->gamesService->find();

        // Retrieve one screenshot per game to use as a thumbnail.
        $thumbnails = [];
        foreach ($games as $game) {
            $thumbnails[] = $this->gamesService->getScreenshots(['id' => $game->id])[0];
        }

        return $this->response->render('Games/Catalogue/index', ['games' => $games, 'thumbnails' => $thumbnails]);
    }

    public function viewAction(string $slug): array
    {
        $game = $this->gamesService->find(['slug' => $slug])[0];
        $screenshots = $this->gamesService->getScreenshots(['game_id' => $game->id]);
        return $this->response->render('Games/Catalogue/view', ['game' => $game, 'screenshots' => $screenshots]);
    }
}
