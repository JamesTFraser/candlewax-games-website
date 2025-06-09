<?php

namespace CandlewaxGames\Controllers\User;

use CandlewaxGames\Controllers\BaseController;
use CandlewaxGames\Database\Entity;
use CandlewaxGames\Services\Response;
use CandlewaxGames\Services\Validator;
use CandlewaxGames\Services\User;
use Random\RandomException;

class AccountController extends BaseController
{
    private Validator $validator;
    private User $userService;
    private string $verifySuccessMessage = 'Email verified successfully!';
    private string $verifyFailureMessage =
        'Your email could not be verified. Please request a new verification email and try again.';

    public function __construct(Response $response, Validator $validator, User $userService)
    {
        $this->validator = $validator;
        $this->userService = $userService;
        parent::__construct($response);
    }

    public function loginAction(array $post = []): array
    {
        // If the user has not submitted the login form yet.
        if (!$post) {
            return $this->response->render('/User/Account/login');
        }

        // Attempt to log in with the given credentials.
        $user = $this->userService->login($post['email'], $post['password']);
        $errors = $user ? [] : ['email' => ['The email and password combination are incorrect.']];
        return $user ? $this->response->redirect('/u/' . $user->getColumns()['username']) :
            $this->response->render('/user/account/login', ['errors' => $errors]);
    }

    /**
     * @throws RandomException If an appropriate source of randomness cannot be found when generating the email
     * verification token.
     */
    public function registerAction(array $post): array
    {
        // Construct the $rules array with the error messages to be displayed if the form data is invalid.
        $data = $post;
        $rules = [
            'username' => ['alphaNum' => 'Username must contain only letters and numbers.'],
            'email' => ['email' => 'Must be a valid email.'],
            'password' => ['min:8' => 'Password must be at least 8 characters long.'],
        ];

        // If the 'confirm password' field is not empty, add a rule to check that it matches the password field.
        if (isset($data['password'])) {
            $rules['confirm'] = ["identical:{$data['password']}" => 'The password fields must match.'];
        }

        // Validate the form data.
        $errors = [];
        $valid = $this->validateUnique($data, $rules, $errors);

        // Redirect to the profile page with a success message.
        if ($valid) {
            $user = $this->userService->create($data['username'], $data['email'], $data['password']);
            $this->userService->sendVerificationEmail($user->id, $user->getColumns()['email']);
            $this->response->flash('messages', ['Account created!']);
            $this->userService->login($data['email'], $data['password']);
            return $this->response->redirect('/u/' . $data['username']);
        }

        // If the form data was invalid, flash the errors to the session array and redirect back to the sign-up form.
        $this->response->flash('errors', $errors);
        return $this->response->redirect('/user/account/login');
    }

    public function editAction(array $session): array
    {
        $user = $this->userService->find(['id' => $session['userId']])[0];
        $profile = $this->userService->getProfileInfo($user->getColumns()['username']);
        return $this->response->render('User/Account/edit', ['user' => $user->getColumns(), 'profile' => $profile]);
    }

    public function updateAction(array $post, array $session): array
    {
        // If the user is not logged in, redirect to the login page.
        if (!isset($session['userId'])) {
            return $this->response->redirect('/user/account/login');
        }

        // Construct the $rules array with the error messages to be displayed if the form data is invalid.
        $data = $post;
        $rules = [
            'username' => ['alphaNum' => 'Username must contain only letters and numbers.'],
            'email' => ['email' => 'Must be a valid email.'],
            'password' => ['min:8' => 'Password must be at least 8 characters long.'],
        ];

        // Check the submitted password.
        $errors = [];
        $user = $this->userService->find(['id' => $session['userId']])[0];
        if (!password_verify($data['password'], $user->getColumns()['password'])) {
            $errors['password'][] = 'The password was incorrect.';
        }

        // Validate the form data.
        $valid = $this->validateUnique($data, $rules, $errors, $user);

        // Redirect to the user account edit page with a success message.
        if ($valid && empty($errors)) {
            // Update the user.
            $columns = ['username' => $data['username'], 'email' => $data['email']];
            $this->userService->updateUser($session['userId'], $columns);

            // If the user has changed their email.
            if ($data['email'] != $user->getColumns()['email']) {
                // If the user's last email had not been verified, remove the pending verification.
                $this->userService->cancelEmailVerification($session['userId']);

                // Send a verification email to the new address.
                $this->userService->sendVerificationEmail($session['userId'], $data['email']);
            }

            // If no errors were encountered, redirect to the form with a success message.
            $this->response->flash('messages', ['Account details updated.']);
            return $this->response->redirect('/user/account/edit');
        }

        // If the form data was invalid, flash the errors to the session array and redirect back to the edit form.
        $this->response->flash('errors', $errors);
        return $this->response->redirect('/user/account/edit');
    }

    public function updatePasswordAction(array $post, array $session): array
    {
        // If the user is not logged in, redirect to the login page.
        if (!isset($session['userId'])) {
            return $this->response->redirect('/user/account/login');
        }

        // Get the user's data.
        $user = $this->userService->find(['id' => $session['userId']])[0];

        // Make sure both new passwords match.
        $errors = [];
        $data = $post;
        $rules = [
            'old_password' => ['min:8' => 'Password must be at least 8 characters long.'],
            'new_password' => ['min:8' => 'Password must be at least 8 characters long.']
        ];
        if (isset($data['new_confirm'])) {
            $rules['new_confirm'] = ["identical:{$data['new_password']}" => 'The password fields must match.'];
        }
        $this->validator->validate($data, $rules, $errors);

        // Check their old password is correct.
        if (!password_verify($data['old_password'], $user->getColumns()['password'])) {
            $errors['old_password'][] = 'The password was incorrect.';
        }

        // If valid, update the password.
        if (empty($errors)) {
            $this->userService->updateUser($user->id, ['password' => $data['new_password']]);
            $this->response->flash('messages', ['Password updated.']);
            return $this->response->redirect('/user/account/edit');
        }

        // If the form data was invalid, flash the errors to the session array and redirect back to the edit form.
        $this->response->flash('errors', $errors);
        return $this->response->redirect('/user/account/edit');
    }

    public function verifyAction(string $token): array
    {
        // Attempt to verify the user's email.
        $verified = $this->userService->verifyEmail($token);

        // Redirect the user to the log-in page with a success or failure message.
        $statusMessage = $verified ? $this->verifySuccessMessage : $this->verifyFailureMessage;
        $this->response->flash('messages', [$statusMessage]);
        return $this->response->redirect('/user/account/login');
    }

    private function validateUnique(array &$data, array $rules, array &$errors, Entity $user = null): bool
    {
        // Validate the form data.
        $valid = $this->validator->validate($data, $rules, $errors);

        // Check if the submitted username and password has changed from the existing ones.
        $emailChanged = $user && $user->getColumns()['email'] != $data['email'];
        $nameChanged = $user && $user->getColumns()['username'] != $data['username'];

        // If the given username is valid, check that it is not already taken.
        if (
            $nameChanged &&
            !array_key_exists('username', $errors) &&
            !$this->userService->isUsernameUnique($data['username'])
        ) {
            $errors['username'][] = "The username {$data['username']} is already taken.";
            $valid = false;
        }

        // Check if the email is unique.
        if (
            $emailChanged &&
            !array_key_exists('email', $errors) &&
            !$this->userService->isEmailUnique($data['email'])
        ) {
            $errors['email'][] = "The email {$data['email']} is already taken.";
            $valid = false;
        }

        return $valid;
    }

    public function logoutAction(): array
    {
        $this->userService->logout();
        return $this->response->redirect('/user/account/login');
    }
}
