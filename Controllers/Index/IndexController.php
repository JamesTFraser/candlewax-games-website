<?php

namespace CandlewaxGames\Controllers\Index;

use CandlewaxGames\Controllers\BaseController;
use CandlewaxGames\Controllers\Response;

/**
 * The homepage controller.
 */
class IndexController extends BaseController
{
    /**
     * @return Response Contains the view path and the variables to pass to it.
     */
    public function indexAction(): Response
    {
        return new Response('Index/index', ['name' => 'Candlewax']);
    }

    /**
     * @return Response Contains the view path and the variables to pass to it.
     */
    public function fourZeroFourAction(): Response
    {
        return new Response('Index/404');
    }
}
