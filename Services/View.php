<?php

namespace CandlewaxGames\Services;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

/**
 * Contains the templating engine used to render the views located in the /Views folder.
 */
class View
{
    /**
     * @var Environment The templating engine.
     */
    private Environment $engine;
    /**
     * @var string The file extension of the view files.
     */
    private string $templateSuffix = ".twig";

    /**
     * Initialises the templating engine.
     */
    public function __construct()
    {
        // Initialise the template engine.
        $loader = new FilesystemLoader(ROOT . 'Views');
        $this->engine = new Environment($loader, ['cache' => ROOT . 'Cache/twig', 'debug' => DEVELOPMENT_ENVIRONMENT]);
    }

    /**
     * Renders the template at the given $templatePath using the given $data.
     *
     * @param string $templatePath The path to the template without the file suffix. Relative to the Views folder in the
     * project root.
     * @param array $data An array of variables to pass to the template, with the array key as the variable name.
     *
     * @throws RuntimeError When an error occurred during rendering.
     * @throws SyntaxError When an error occurred during compilation.
     * @throws LoaderError When the template can not be found.
     */
    public function render(string $templatePath, array $data = []): string
    {
        // Pass the session along to the view.
        $data['session'] = $_SESSION;

        return $this->engine->render($templatePath . $this->templateSuffix, $data);
    }
}
