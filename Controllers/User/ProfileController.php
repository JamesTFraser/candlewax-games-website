<?php

namespace CandlewaxGames\Controllers\User;

use CandlewaxGames\Controllers\BaseController;
use CandlewaxGames\Services\Response;
use CandlewaxGames\Services\Validator;
use CandlewaxGames\Services\User;
use Random\RandomException;

class ProfileController extends BaseController
{
    private User $userService;

    private Validator $validator;

    private string $imagePath = '/public/images/profile/';

    private int $maxImageSize = 2000000;
    public function __construct(Response $response, User $userService, Validator $validator)
    {
        $this->userService = $userService;
        $this->validator = $validator;
        parent::__construct($response);
    }

    public function indexAction(string $username): array
    {
        $profile = $this->userService->getProfileInfo($username);
        $user = $this->userService->find(['username' => $username])[0];
        $profile['id'] = $user->id;

        if (!empty($user)) {
            return $this->response->render('/User/Profile/index', ['user' => $profile]);
        }
        return $this->response->forward(ProfileController::class, 'userNotFoundAction', ['username' => $username]);
    }

    /**
     * @throws RandomException
     */
    public function updateAction(array $post, array $session, array $files = []): array
    {
        $columns = [];
        $errors = [];

        // If the user is not logged in, redirect to the login page.
        if (!isset($session['userId'])) {
            return $this->response->redirect('/user/account/login');
        }

        // Upload the profile image if one was sent in the form.
        if (isset($files['image']['name']) && $files['image']['name'] != '') {
            $imageErrors = [];
            $columns['image'] = $this->uploadProfileImage($files['image']['tmp_name'], $imageErrors);

            // If the image was uploaded successfully.
            if (empty($imageErrors)) {
                $this->deleteOldProfileImage();
            }

            // If there was a problem uploading the image, add a message to the $errors array.
            if (!empty($imageErrors)) {
                $errors['image'] = $imageErrors;
            }
        }

        // Add the users updated bio to the columns, if one was entered.
        if (isset($post['bio'])) {
            $columns['bio'] = $post['bio'];
        }

        // If errors were found with the form data, return to the edit page with the $errors array.
        if (!empty($errors)) {
            $this->response->flash('errors', $errors);
            return $this->response->redirect('/user/account/edit');
        }

        // If the form was not empty, update the user's profile in the database and send a success message to the user.
        if (!empty($columns)) {
            $this->userService->updateProfile($session['userId'], $columns);
            $this->response->flash('messages', ['Your profile was updated successfully!']);
        }

        return $this->response->redirect('/user/account/edit');
    }

    public function userNotFoundAction(string $username): array
    {
        return $this->response->render('/user/profile/404', ['username' => $username]);
    }

    /**
     * @throws RandomException
     */
    private function uploadProfileImage(string $imagePath, &$errors = []): string
    {
        // Check if the uploaded image is valid.
        $valid = $this->validator->validateImage($imagePath, $this->maxImageSize, ['jpg', 'png', 'gif']);
        if (!$valid) {
            $errors[] = 'The uploaded image was an invalid type or too large.';
            return '';
        }

        // Create a unique name for the image and move it to the appropriate folder.
        $imageType = $this->validator->getImageType($imagePath);
        $imageName = bin2hex(random_bytes(3)) . time() . '.' . $imageType;
        $fileUploaded = move_uploaded_file($imagePath, $_SERVER['DOCUMENT_ROOT'] . $this->imagePath . $imageName);

        // Make sure the image was saved correctly.
        if (!$fileUploaded) {
            $errors[] = 'The server encountered an error when saving the image. Please try again later.';
            return '';
        }

        return $imageName;
    }

    private function deleteOldProfileImage(): void
    {
        // Retrieve the path to the old profile image.
        $profile = $this->userService->getProfileInfo($_SESSION['username']);
        $imagePath = $_SERVER['DOCUMENT_ROOT'] . $this->imagePath . $profile['image'];

        // Make sure we are trying to delete a file and not a directory.
        if ($profile['image'] !== '' && is_file($imagePath)) {
            unlink($imagePath);
        }
    }
}
