<?php

namespace CandlewaxGames\Controllers;

/**
 * The response object returned by the action methods of controllers.
 */
class Response
{
    /**
     * @var string The path to the view to render, relative to the /Views folder and minus the file extension.
     */
    public string $viewPath;

    /**
     * @var array Contains the variables to pass to the view template. Where the key is the variable name.
     */
    public array $data;

    public function __construct(string $viewPath, array $data = [])
    {
        $this->viewPath = $viewPath;
        $this->data = $data;
    }
}
