<?php

namespace CandlewaxGames\Controllers\Discussion;

use CandlewaxGames\Controllers\BaseController;
use CandlewaxGames\Services\Post;
use CandlewaxGames\Services\Response;
use CandlewaxGames\Services\Validator;

class PostController extends BaseController
{
    private Post $post;
    private Validator $validator;
    private int $postsPerPage = 10;

    public function __construct(Response $response, Post $post, Validator $validator)
    {
        $this->post = $post;
        $this->validator = $validator;
        parent::__construct($response);
    }

    public function indexAction(int $pageNumber = 1): array
    {
        $count = ceil($this->post->PostCount(['parent_id' => null]) / $this->postsPerPage);
        $pageNumber = max($pageNumber, 1);
        $offset = $this->postsPerPage * ($pageNumber - 1);
        $posts = $this->post->findAll($this->postsPerPage, $offset);
        return $this->response->render('Discussion/Post/index', [
            'posts' => $posts,
            'page_count' => $count,
            'current_page' => $pageNumber,
            'page_number' => $pageNumber,
            'loggedIn' => isset($_SESSION['userId'])
        ]);
    }

    public function viewAction(string $slug): array
    {
        $post = $this->post->find(['posts.slug' => $slug]);

        if (!$post) {
            return $this->response->forward(PostController::class, 'notFoundAction', ['slug' => $slug]);
        }

        $replies = $this->post->getReplies($post[0]->posts_id, 'posts.created_at DESC');
        return $this->response->render('Discussion/Post/view', ['article' => $post[0], 'replies' => $replies]);
    }

    public function createAction(): array
    {
        $this->redirectIfLoggedOut();
        return $this->response->render('Discussion/Post/create');
    }

    public function storeAction(array $post): array
    {
        $this->redirectIfLoggedOut();

        // Define the rules to validate the new post with.
        $rules = [
            'title' => ['required' => 'The post must have a title.'],
            'content' => ['required' => 'The post must have content.']
        ];

        // Validate the form data.
        $errors = [];
        $data = $post;
        $this->validator->validate($data, $rules, $errors);
        if (!empty($errors)) {
            $this->response->flash('errors', $errors);
            return $this->response->redirect('/discussion/post/create');
        }

        // Save the new post to the database.
        $slug = $this->createUniqueSlug($data['title']);
        $this->post->create($data['title'], $slug, $data['content'], true, $_SESSION['userId']);

        return $this->response->redirect('/p/' . $slug);
    }

    public function storeReplyAction(array $post): array
    {
        $this->redirectIfLoggedOut();

        // Define the rules to validate the new reply with.
        $rules = [
            'parent_id' => ['alphaNum' => ''],
            'content' => ['required' => 'You need to type a reply.']
        ];

        // Validate the form data.
        $errors = [];
        $data = $post;
        $this->validator->validate($data, $rules, $errors);

        // Retrieve the root post.
        $rootPost = !isset($errors['parent_id']) ? $this->post->findRoot($data['parent_id']) : null;

        // Create a unique slug for the reply.
        $slug = $rootPost ? $this->createUniqueSlug($rootPost->title . '-reply') : '';

        // If the data is valid, save the new post to the database.
        if (empty($errors) && $rootPost) {
            $this->post->create('', $slug, $data['content'], true, $_SESSION['userId'], $data['parent_id']);
        }

        // Flash any errors and redirect back to the root post page.
        $this->response->flash('errors', $errors);
        return $this->response->redirect($rootPost ? '/p/' . $rootPost->slug : '/discussion');
    }

    public function notFoundAction(string $slug): array
    {
        return $this->response->render('/discussion/post/404', ['slug' => $slug]);
    }

    private function createUniqueSlug(string $text): string
    {
        // Convert the given string to the slug format.
        $slug = $this->validator->stringToSlug($text);

        // Check if a post with the same slug exists.
        $matchingSlugs = $this->post->find(['slug' => $slug . '%'], 'like');

        // If posts with the same slug already exist, numerate the slug.
        $slug .= sizeof($matchingSlugs) > 0 ? '-' . sizeof($matchingSlugs) : '';

        return $slug;
    }

    private function redirectIfLoggedOut(): void
    {
        // If the user is not logged in, redirect to the login screen.
        if (!isset($_SESSION['userId'])) {
            $this->response->redirect('/user/account/login');
        }
    }
}
