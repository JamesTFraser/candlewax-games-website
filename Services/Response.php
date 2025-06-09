<?php

namespace CandlewaxGames\Services;

/**
 * Constructs the response array to be given to the Router by the action methods of Controllers.
 */
class Response
{
    public function render(string $view, array $data = []): array
    {
        // Add the flashed session vars to the view data.
        if (isset($_SESSION['flash'])) {
            $data = array_merge($data, $_SESSION['flash']);
        }

        // If $_POST data is present, pass it along to the view.
        if (!empty($_POST)) {
            $data['post'] = $_POST;
        }

        // Now that the previously saved flash messages have been passed to the view, remove them from the $_SESSION.
        unset($_SESSION['flash']);

        return ['view' => $view, 'data' => $data];
    }

    public function redirect(string $url): array
    {
        // If $_POST data is present, pass it along to the next page.
        if (!empty($_POST)) {
            $this->flash('post', $_POST);
        }

        return ['redirect' => $url];
    }

    public function forward(string $controller, string $action, array $params = []): array
    {
        return ['forward' => ['controller' => $controller, 'action' => $action, 'params' => $params]];
    }

    public function flash(string $key, array $messages): void
    {
        $_SESSION['flash'][$key] = $messages;
    }
}
