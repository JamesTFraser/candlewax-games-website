<?php

namespace CandlewaxGames\Services;

use CandlewaxGames\Database\Database;
use CandlewaxGames\Database\Entity;
use Random\RandomException;

class User
{
    private Database $database;
    private Mail $mail;
    private string $userTable = 'users';
    private string $verifyTable = 'email_verification_tokens';
    private string $profileTable = 'user_profiles';

    public function __construct(Database $database, Mail $mail)
    {
        $this->database = $database;
        $this->mail = $mail;
    }

    /**
     * Adds a new user to the database along with a corresponding entry in the user_profile table.
     *
     * @param string $name The new user's username.
     * @param string $email The new user's email.
     * @param string $password The unencrypted form of the new user's password.
     * @return Entity The newly created user.
     */
    public function create(string $name, string $email, string $password): Entity
    {
        $userData = [0 => [
            'username' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT)
        ]];
        $user = $this->database->create($this->userTable, $userData)[0];
        $this->database->create($this->profileTable, [0 => ['user_id' => $user->id, 'bio' => 'No bio entered.']]);
        return $user;
    }

    public function login(string $email, string $password): ?Entity
    {
        $user = $this->database->read($this->userTable, ['email' => $email]);
        if ($user && password_verify($password, $user[0]->GetColumns()['password'])) {
            // Add the logged-in user's data to the $_SESSION.
            $_SESSION['userId'] = $user[0]->id;
            $_SESSION['username'] = $user[0]->getColumns()['username'];

            return $user[0];
        }
        return null;
    }

    public function logout(): void
    {
        unset($_SESSION['userId']);
        unset($_SESSION['username']);
    }

    public function find(array $where): array
    {
        return $this->database->read($this->userTable, $where);
    }

    /**
     * Creates a verification token in the database and sends an email to the given user's address with a link
     * containing the token.
     *
     * @param int $userId The id of the user whose email to verify.
     * @param string $email The recipient of the verification email.
     * @return void
     * @throws RandomException If an appropriate source of randomness cannot be found when generating the verification
     * token.
     */
    public function sendVerificationEmail(int $userId, string $email): void
    {
        $token = bin2hex(random_bytes(6));
        $message = "Thank you for creating an account at candlewax.games. Please verify your email address by clicking
        the following link: <a href=\"https://candlewax.games/user/account/verify/$token\">Verify your email.</a>";
        $this->database->create($this->verifyTable, [0 => ['user_id' => $userId, 'token' => $token]]);
        $this->mail->send($email, "Verify your email address", $message, 'no-reply@candlewax.games');
    }

    public function verifyEmail(string $token): bool
    {
        // Search for an unverified email with the given token.
        $verificationData = $this->database->read($this->verifyTable, ['token' => $token]);

        // If the token exists in the database, the user has passed verification.
        if (!empty($verificationData)) {
            // Delete the entry in the database as it is no longer necessary.
            $this->database->delete($this->verifyTable, ['token' => $token]);
            return true;
        }

        return false;
    }

    public function cancelEmailVerification(int $userId): void
    {
        $this->database->delete($this->verifyTable, ['user_id' => $userId]);
    }

    /**
     * Returns true if no user with the given username exists in the database.
     *
     * @param string $username The username to check against existing usernames.
     * @return bool True if the username is not present in the database.
     */
    public function isUsernameUnique(string $username): bool
    {
        return empty($this->database->read($this->userTable, ['username' => $username]));
    }

    /**
     * Returns true if no user with the given email exists in the database.
     *
     * @param string $email The email to check against existing emails.
     * @return bool True if the email is not present in the database.
     */
    public function isEmailUnique(string $email): bool
    {
        return empty($this->database->read($this->userTable, ['email' => $email]));
    }

    public function getProfileInfo(string $username): array
    {
        $user = $this->database->readLeftJoin(
            ['users', 'user_profiles'],
            ['users.id', 'user_profiles.user_id'],
            ['users.username' => $username]
        )[0] ?? null;

        return $user ? [
            'username' => $user->GetColumns()['username'],
            'bio' => $user->GetColumns()['bio'],
            'image' => $user->GetColumns()['image']
            ] : [];
    }

    public function updateUser(int $userId, array $columns): bool
    {
        // If a password field is present, encrypt it.
        if (array_key_exists('password', $columns)) {
            $columns['password'] = password_hash($columns['password'], PASSWORD_DEFAULT);
        }

        return $this->update($this->userTable, ['id' => $userId], $columns);
    }

    public function updateProfile(int $userId, array $columns): bool
    {
        return $this->update($this->profileTable, ['user_id' => $userId], $columns);
    }

    private function update(string $table, array $where, array $columns): bool
    {
        // Retrieve the Entity to update.
        $entity = $this->database->read($table, $where)[0];
        if (!$entity) {
            return false;
        }

        // Update the Entity.
        foreach ($columns as $name => $value) {
            $entity->setColumn($name, $value);
        }
        $this->database->update([$entity]);
        return true;
    }
}
