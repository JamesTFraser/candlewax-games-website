<?php

namespace CandlewaxGames\Services;

use CandlewaxGames\Database\Database;
use CandlewaxGames\Database\Entity;

class Post
{
    private Database $database;
    private string $postTable = 'posts';
    private string $userTable = 'users';
    private string $profileTable = 'user_profiles';

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function create(
        string $title,
        string $slug,
        string $content,
        bool $isPublished,
        int $userId,
        int $parentId = null
    ): void {
        $this->database->create($this->postTable, [[
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'is_published' => $isPublished,
            'user_id' => $userId,
            'parent_id' => $parentId
        ]]);
    }

    public function find(array $where, string $comparison = '=', int $amount = 0, int $offset = 0): array
    {
        return $this->database->readLeftJoin(
            $this->postTable,
            [$this->userTable => ['user_id', 'id'], $this->profileTable => ['user_id', 'user_id']],
            $where,
            $this->postTable . '.created_at DESC',
            $amount,
            $offset,
            $comparison
        );
    }

    public function findAll(int $amount, int $offset): array
    {
        return $this->database->readLeftJoin(
            $this->postTable,
            [$this->userTable => ['user_id', 'id'], $this->profileTable => ['user_id', 'user_id']],
            ['is_published' => 1, 'parent_id' => null],
            $this->postTable . '.created_at DESC',
            $amount,
            $offset
        );
    }

    public function getReplies(int $id, string $order = ''): array
    {
        $replies = $this->database->readRecursive(
            $this->postTable,
            ['parent_id' => $id],
            ['parent_id', 'id'],
            [$this->userTable => ['user_id', 'id'], $this->profileTable => ['user_id', 'user_id']],
            $order
        );

        // Sort the replies into a tree by their parent post id.
        return $this->sortReplies($replies, $id);
    }

    public function findRoot(int $id): ?Entity
    {
        return $this->database->findRoot($this->postTable, $id);
    }

    public function postCount(array $where = []): int
    {
        return $this->database->count($this->postTable, $where);
    }

    private function sortReplies(array $posts, int $parentId): array
    {
        $branch = [];

        foreach ($posts as $post) {
            if ($post->parent_id === $parentId) {
                $branch[] = [
                  'post' => $post,
                  'children' => $this->sortReplies($posts, $post->posts_id)
                ];
            }
        }
        return $branch;
    }
}
