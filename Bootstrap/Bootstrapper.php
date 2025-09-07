<?php

namespace CandlewaxGames\Bootstrap;

use CandlewaxGames\Database\Database;
use Exception;
use ReflectionException;

/**
 * The starting point for the application. Responsible for initialising all classes and constants necessary for handling
 * the users request.
 */
class Bootstrapper
{
    private DIContainer $diContainer;
    private Database $database;

    public function __construct(DIContainer $diContainer, Database $database)
    {
        $this->diContainer = $diContainer;
        $this->database = $database;
    }

    /**
     * Loads the env variables, sets the current environment and calls the handleRequest method of the Router class.
     *
     * @throws Exception If no .env file is present in the project root directory.
     * @throws ReflectionException If a dependency of the Router class is not resolvable.
     */
    public function init(): void
    {
        // Start the $_SESSION.
        session_start();

        // Set the time localisation.
        date_default_timezone_set('Europe/London');

        // Set the environment variables.
        $this->setEnvVars();

        // Set the constant variables.
        $this->setConstants();

        // Set whether to display errors based on the current environment.
        $this->setEnvironment();

        // Connect to the database.
        $this->database->connect(
            getenv('DATABASE_HOST'),
            getenv('DATABASE_NAME'),
            getenv('DATABASE_USER'),
            getenv('DATABASE_PASSWORD')
        );

        // Parse the url and call the corresponding controller action method.
        $injector = $this->diContainer->getDI();
        $router = $injector->get(Router::class);
        $router->handleRequest();
    }

    /**
     * Loops through each line of the .env file searching for variables and sets them using putenv().
     *
     * @throws Exception If no .env file is present in the project root directory.
     */
    private function setEnvVars(): void
    {
        // Make sure the .env file exists in the project root directory.
        if (!file_exists('.env')) {
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
            throw new Exception('The .env file is missing.');
        }

        // Loop through each line of the .env file and register the variables.
        $envFile = str_replace(' ', '', file_get_contents('.env'));
        foreach (explode("\n", $envFile) as $envLine) {
            // If the line starts with a # or is blank, ignore it.
            if (str_starts_with($envLine, '#') || strlen($envLine) === 0) {
                continue;
            }

            // Remove any quotes from the value and register the var.
            putenv(str_replace("'", '', $envLine));
        }
    }

    /**
     * Defines four constant variables, DEVELOPMENT_ENVIRONMENT, DEFAULT_MODULE, URL and ROOT.
     *
     * The first two are defined by their counterparts in the .env file. The last two are defined by the
     * $_SERVER['REQUEST_URI'] and $_SERVER['DOCUMENT_ROOT'] respectively.
     *
     * @return void
     */
    private function setConstants(): void
    {
        define('DEVELOPMENT_ENVIRONMENT', getenv('DEVELOPMENT_ENVIRONMENT'));
        define('DEFAULT_MODULE', getenv('DEFAULT_MODULE'));
        define('URL', $_SERVER['REQUEST_URI']);
        define('ROOT', $_SERVER['DOCUMENT_ROOT'] . '/');
    }

    /**
     * Defines the current environment (development or production) based on the DEVELOPMENT_ENVIRONMENT .env variable.
     *
     * In development mode all errors are displayed to the user. In production mode, errors are written to a log file
     * located at /Logs/PHP/error.log.
     *
     * @return void
     */
    private function setEnvironment(): void
    {
        // If development environment is on, display errors to the user.
        if (DEVELOPMENT_ENVIRONMENT == false) {
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
            return;
        }

        // If development environment is off, write errors to an error log.
        error_reporting(0);
        ini_set('display_errors', 'Off');
        ini_set('log_errors', 'On');
        ini_set('error_log', ROOT . '/Logs/PHP/error.log');
    }
}
