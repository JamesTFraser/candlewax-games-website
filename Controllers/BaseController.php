<?php

namespace CandlewaxGames\Controllers;

use CandlewaxGames\Services\Response;

/**
 * The base class for all controllers.
 */
abstract class BaseController
{
    /**
     * @var Response The response service used to return responses in the action methods.
     */
    protected Response $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }
}
