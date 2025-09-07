<?php

namespace CandlewaxGames\Bootstrap;

use CandlewaxGames\Controllers\Discussion\BlogController;
use CandlewaxGames\Controllers\Discussion\PostController;
use CandlewaxGames\Controllers\Games\CatalogueController;
use CandlewaxGames\Controllers\Index\IndexController;
use CandlewaxGames\Controllers\User\ProfileController;
use CandlewaxGames\Services\ParamResolver;
use CandlewaxGames\Services\View;
use ReflectionException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Responsible for routing the user's request to the correct controller and action method based on the given url.
 *
 * The expected url format is /module/controller/action (e.g. /user/account/login would route to the loginAction method
 * of AccountController in the User namespace.) Will use the templating engine defined in the ViewService to render the
 * view. The path to the view is specified in the Response object of the called action method.
 */
class Router
{
    private array $routes = [
        '/u/{username}' => [ProfileController::class, 'indexAction'],
        '/p/{slug}' => [PostController::class, 'viewAction'],
        '/discussion' => [PostController::class, 'indexAction'],
        '/discussion/{pageNumber}' => [PostController::class, 'indexAction'],
        '/blog' => [BlogController::class, 'indexAction'],
        '/blog/{pageNumber}' => [BlogController::class, 'indexAction'],
        '/games' => [CatalogueController::class, 'indexAction'],
        '/games/{slug}' => [CatalogueController::class, 'viewAction']
    ];

    /**
     * @var View The view service containing the template rendering engine.
     */
    private View $view;

    /**
     * @var DependencyInjector Used to resolve the dependencies of the controller.
     */
    private DependencyInjector $injector;

    /**
     * @var ParamResolver Used to match values to method parameters.
     */
    private ParamResolver $resolver;

    /**
     * Sorts the url into an array split by the /.
     *
     * @param View $view The view service containing the template rendering engine.
     * @param DependencyInjector $injector Used to resolve the dependencies of the controller.
     */
    public function __construct(View $view, DependencyInjector $injector, ParamResolver $resolver)
    {
        $this->view = $view;
        $this->injector = $injector;
        $this->resolver = $resolver;
    }

    /**
     * Directs the user to the correct page by finding the controller and action to call based on the current url.
     *
     * Parses the url and calls the corresponding action method. If no controller can be found by the specified url,
     * then a 404 page is rendered instead. Any parts of the url after the action are passed to the controller as
     * optional parameters.
     *
     * If no action is specified in the url, then the index action is used as the default. Base urls are routed to the
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
        // Try to match the url to a controller using the default '/namespace/controller/action/param1/etc' pattern.
        $request = [];

        // If the requested url could not be matched automatically, check for a match in the $routes array.
        if (!$this->lookUpDefaultPath($request)) {
            // If the requested url cannot be matched to a controller action.
            if (!$this->lookUpRoutesArray($request)) {
                // Route the user to a 404 page.
                $request['controller'] = IndexController::class;
                $request['action'] = 'fourZeroFourAction';
                $request['params'] = [];
                header("HTTP/1.0 404 Not Found");
            }
        }

        // Instantiate the controller and call the correct action method.
        $this->resolve($request['controller'], $request['action'], $request['params']);
    }

    /**
     * Calls the action method of the controller.
     *
     * The controller's dependencies are recursively resolved before calling the action. The response object returned by
     * the action is then used to render the view using the view service.
     *
     * @param string $controllerPath The namespace path to the controller class.
     * @param string $action The name of the action method to call.
     * @param array $params The parameters to pass to the controller's action method.
     * @return void
     * @throws LoaderError
     * @throws ReflectionException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function resolve(string $controllerPath, string $action, array $params = []): void
    {
        // Resolve the dependencies of the controller and call its action method.
        $controller = $this->injector->get($controllerPath);
        $response = call_user_func_array([$controller, $action], $params);

        // If the controller returned a redirect response, redirect to the new url.
        if (isset($response['redirect'])) {
            header('Location: ' . $response['redirect']);
            return;
        }

        // If the controller returned a forward response, call the action defined by that response.
        if (isset($response['forward'])) {
            // Get the name space, controller and action names from the response to forward to.
            $fController = $response['forward']['controller'];
            $fAction = $response['forward']['action'];

            // Attempt to resolve the new controller and action returned by the response.
            $this->resolve($fController, $fAction, $response['forward']['params']);
            return;
        }

        // Render the view.
        echo $this->view->render($response['view'], $response['data']);
    }

    /**
     * @throws ReflectionException
     */
    private function lookUpDefaultPath(array &$request = []): bool
    {
        // Split the request url into parts separated by /.
        $urlArray = $this->parseUrl($_SERVER['REQUEST_URI']);

        // Set the name space, class and action according to the url.
        $nameSpace = isset($urlArray[0]) ? ucwords(strtolower($urlArray[0])) : DEFAULT_MODULE;
        $class = isset($urlArray[1]) ? ucwords(strtolower($urlArray[1])) : 'Index';
        $request['action'] = isset($urlArray[2]) ? strtolower($urlArray[2]) . 'Action' : 'indexAction';

        // Put the rest of the url into an array.
        $params = [];
        if (isset($urlArray[3])) {
            $params = $urlArray;
            array_splice($params, 0, 3);
        }

        // Construct the name space and path to the class.
        $request['controller'] = "CandlewaxGames\\Controllers\\$nameSpace\\{$class}Controller";
        $path = ROOT . "Controllers/" . $nameSpace . '/' .  $class . "Controller.php";

        // Check whether the requested class and action exist.
        if (
            !(file_exists($path) &&
            (include_once $path) &&
            method_exists($request['controller'], $request['action']))
        ) {
            return false;
        }

        // Attempt to resolve the $action parameters.
        $request['params'] = $this->resolver->resolveMethodParams($request['controller'], $request['action'], $params);

        // If the action params could not be resolved.
        if ($request['params'] === null) {
            return false;
        }

        return true;
    }

    /**
     * @throws ReflectionException
     */
    private function lookUpRoutesArray(array &$request = []): bool
    {
        foreach ($this->routes as $routeUrl => $route) {
            // Split the urls into arrays separated by /.
            $requestUrlArray = $this->parseUrl($_SERVER['REQUEST_URI']);
            $routeUrlArray = $this->parseUrl($routeUrl);

            // If the route url does not have the same number of parts as the request url, this is not the route.
            $routeUrlCount = count($routeUrlArray);
            if ($routeUrlCount != count($requestUrlArray)) {
                continue;
            }

            // Loop through the route url parts and compare them to the request url parts.
            $params = [];
            $routeMatch = true;
            for ($i = 0; $i < $routeUrlCount; $i++) {
                // If the current part is a parameter, record its name and value.
                if ($routeUrlArray[$i][0] == '{') {
                    $paramName = str_replace(['{', '}'], '', $routeUrlArray[$i]);
                    $params[$paramName] = $this->resolver->typeCastFromString($requestUrlArray[$i]);
                    continue;
                }

                // If the route url part does not match the request url part, this is not the correct route.
                if ($routeUrlArray[$i] != $requestUrlArray[$i]) {
                    $routeMatch = false;
                    break;
                }
            }

            // If the route url did not match the request url, move on to the next in the $routes array.
            if (!$routeMatch) {
                continue;
            }

            // If the urls did match, set the controller and action from the $routes array.
            $request['controller'] = $route[0];
            $request['action'] = $route[1];
            $request['params'] =
                $this->resolver->resolveMethodParams($request['controller'], $request['action'], $params);

            // True if the requested route was found in the $routes array and the action parameters were satisfied.
            return $request['params'] !== null;
        }

        // The requested route does not exist in the $routes array.
        return false;
    }

    private function parseUrl(string $url): array
    {
        // Get the url the user has entered and sort it into an array.
        $url = substr($url, 1);
        $urlArray = explode('/', $url);

        // If the url entry is empty, unset it.
        if (isset($urlArray)) {
            foreach ($urlArray as $key => $url) {
                if ($url == '') {
                    unset($urlArray[$key]);
                }
            }
        }

        return $urlArray;
    }
}
