<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * Create a new user.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): User;

    /**
     * Find a user by email address.
     */
    public function findByEmail(string $email): ?User;
}
