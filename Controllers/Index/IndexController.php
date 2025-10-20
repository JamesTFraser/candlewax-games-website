<?php

namespace CandlewaxGames\Controllers\Index;

use CandlewaxGames\Controllers\BaseController;
use CandlewaxGames\Services\Game;
use CandlewaxGames\Services\Post;
use CandlewaxGames\Services\Response;

/**
 * The homepage controller.
 */
class IndexController extends BaseController
{
    private int $firstFeaturedPostId = 11;
    private int $secondFeaturedPostId = 2;
    private int $featuredGameId = 1;
    private Post $postService;
    private Game $gameService;

    public function __construct(Post $postService, Game $gameService, Response $response)
    {
        $this->postService = $postService;
        $this->gameService = $gameService;
        parent::__construct($response);
    }

    /**
     * @return array Contains the view path and the variables to pass to it.
     */
    public function indexAction(): array
    {
        $firstPost = $this->postService->find(['posts.id' => $this->firstFeaturedPostId])[0] ?? null;
        $secondPost = $this->postService->find(['posts.id' => $this->secondFeaturedPostId])[0] ?? null;
        $game = $this->gameService->find(['id' => $this->featuredGameId])[0] ?? null;
        $screenshot = $this->gameService->getScreenshots(['game_id' => $this->featuredGameId])[0];
        return $this->response->render(
            'Index/index',
            ['first_post' => $firstPost, 'second_post' => $secondPost, 'game' => $game, 'game_image' => $screenshot]
        );
    }

    /**
     * @return array Contains the view path and the variables to pass to it.
     */
    public function fourZeroFourAction(): array
    {
        return $this->response->render('Index/404');
    }
}
