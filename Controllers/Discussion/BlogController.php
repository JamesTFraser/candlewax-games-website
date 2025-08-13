<?php

namespace CandlewaxGames\Controllers\Discussion;

use CandlewaxGames\Controllers\BaseController;
use CandlewaxGames\Services\Post;
use CandlewaxGames\Services\Response;

class BlogController extends BaseController
{
    private Post $post;
    private int $postsPerPage = 9;
    private int $userId = 1;

    public function __construct(Response $response, Post $post)
    {
        $this->post = $post;
        parent::__construct($response);
    }

    public function indexAction(int $pageNumber = 1): array
    {
        $count =
            ceil($this->post->PostCount(['parent_id' => null, 'user_id' => $this->userId]) / $this->postsPerPage);
        $pageNumber = max($pageNumber, 1);
        $offset = $this->postsPerPage * ($pageNumber - 1);
        $posts = $this->post->find(
            ['is_published' => 1, 'posts.user_id' => $this->userId, 'parent_id' => null],
            '=',
            $this->postsPerPage,
            $offset
        );
        return $this->response->render('Discussion/Blog/index', [
            'posts' => $posts,
            'page_count' => $count,
            'current_page' => $pageNumber,
            'page_number' => $pageNumber
        ]);
    }
}
