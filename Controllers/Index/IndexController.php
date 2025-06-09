<?php

namespace CandlewaxGames\Controllers\Index;

use CandlewaxGames\Controllers\BaseController;

/**
 * The homepage controller.
 */
class IndexController extends BaseController
{
    /**
     * @return array Contains the view path and the variables to pass to it.
     */
    public function indexAction(): array
    {
        return $this->response->render('Index/index');
    }

    /**
     * @return array Contains the view path and the variables to pass to it.
     */
    public function fourZeroFourAction(): array
    {
        return $this->response->render('Index/404');
    }
}
