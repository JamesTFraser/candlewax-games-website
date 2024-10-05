<?php

namespace CandlewaxGames\Bootstrap;

use CandlewaxGames\Services\View;
use ReflectionException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Responsible for routing the users request to the correct controller and action method based on the given url.
 *
 * The expected url format is /module/controller/action (e.g /user/account/login would route to the loginAction method
 * of AccountController in the User namespace.) Will use the templating engine defined in the ViewService to render the
 * view. The path to the view is specified in the Response object of the called action method.
 */
class Router
{
    /**
     * @var View The view service containing the template rendering engine.
     */
    private View $view;
    /**
     * @var DependencyInjector Used to resolve the dependencies of the controller.
     */
    private DependencyInjector $injector;
    /**
     * @var string[] A string array of the url split by the /.
     */
    private array $urlArray;
    /**
     * @var string[] A string array or parameters passed through the url. Any part of the url beyond the action is
     * considered a parameter.
     */
    public array $paramArray;
    /**
     * @var string The namespace of the controller whose action method will be called.
     */
    public string $moduleName;
    /**
     * @var string The class name of the controller whose action method will be called.
     */
    public string $controllerName;
    /**
     * @var string The action method of the controller to call.
     */
    public string $actionName;

    /**
     * Sorts the url into an array split by the /.
     *
     * @param View $view The view service containing the template rendering engine.
     * @param DependencyInjector $injector Used to resolve the dependencies of the controller.
     */
    public function __construct(View $view, DependencyInjector $injector)
    {
        $this->view = $view;
        $this->injector = $injector;
        $this->paramArray = [];

        // Get the url the user has entered and sort it into an array.
        $url = strtolower($_SERVER['REQUEST_URI']);
        $url = substr($url, 1);
        $this->urlArray = explode('/', $url);

        // If the url entry is empty, unset it.
        if (isset($this->urlArray)) {
            foreach ($this->urlArray as $key => $url) {
                if ($url == '') {
                    unset($this->urlArray[$key]);
                }
            }
        }
    }

    /**
     * Directs the user to the correct page by finding the controller and action to call based on the current url.
     *
     * Parses the url and calls the corresponding action method. If no controller can be found by the specified url then
     * a 404 page is rendered instead. Any parts of the url after the action are passed to the controller as optional
     * parameters.
     *
     * If no action is specified in the url then the index action is used as the default. Base urls are routed to the
     * index controller of the default module defined in the .env file.
     *
     * @return void
     * @throws ReflectionException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function handleRequest(): void
    {
        // Set the module, controller and action according to the url.
        $this->moduleName = isset($this->urlArray[0]) ? strtolower($this->urlArray[0]) : DEFAULT_MODULE;
        $this->controllerName = isset($this->urlArray[1]) ? ucwords(strtolower($this->urlArray[1])) : 'Index';
        $this->actionName = isset($this->urlArray[2]) ? strtolower($this->urlArray[2]) : 'index';

        // Put the rest of the url into an array called paramArray.
        if (isset($this->urlArray[3])) {
            array_splice($this->urlArray, 0, 3);
            $this->paramArray = $this->urlArray;
        }

        // Include the correct controller if it exists else go to a 404 page.
        $path = ROOT . "Controllers/" . $this->moduleName . '/' .  $this->controllerName . "Controller.php";
        if (file_exists($path)) {
            include_once($path);

            // Check to see if the action exists else go to the indexAction and pass the action as a parameter.
            if (!method_exists($this->controllerName . 'Controller', $this->actionName . 'Action')) {
                array_unshift($this->paramArray, $this->actionName);
                $this->actionName = 'index';
            }
        } else {
            $this->moduleName = DEFAULT_MODULE;
            $this->controllerName = 'Index';
            $this->actionName = 'fourZeroFour';
            include_once(ROOT . "Controllers/$this->moduleName/$this->controllerName" . "Controller.php");
            header("HTTP/1.0 404 Not Found");
        }

        // Instantiate the controller and call the correct action method.
        $this->resolve();
    }

    /**
     * Forwards the user to a different page without changing the url, useful for authentication pages.
     *
     * @param string $module The namespace of the new controller.
     * @param string $controller The controller to forward to.
     * @param string $action The specific action of the controller to call.
     * @return void
     * @throws ReflectionException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function forward(string $module, string $controller, string $action): void
    {
        // Change the module, controller and action in the router to reflect the new location.
        $this->moduleName = $module;
        $this->controllerName = $controller;
        $this->actionName = $action;

        $this->resolve();
    }

    /**
     * Calls the action method on the controller specified by the url before rendering the view.
     *
     * The controllers dependencies are recursively resolved before calling the action. The response object returned by
     * the action is then used to render the view using the view service.
     *
     * @return void
     * @throws LoaderError
     * @throws ReflectionException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function resolve(): void
    {
        // Construct the controller and action names from the url parts.
        $controllerClass = "CandlewaxGames\Controllers\\$this->moduleName\\{$this->controllerName}Controller";
        $actionMethod = $this->actionName . 'Action';

        // Resolve the dependencies of the controller and call its action method.
        $controller = $this->injector->get($controllerClass);
        $response = $controller->$actionMethod();

        // Render the view.
        echo $this->view->render($response->viewPath, $response->data);
    }
}
