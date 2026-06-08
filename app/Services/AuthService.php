<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    /**
     * Register a new consumer account and issue an API token.
     *
     * Admins are intentionally NOT creatable here — they are provisioned via seeder only.
     *
     * @param array<string, mixed> $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = $this->users->create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'], // hashed automatically via the model cast
            'role'     => UserRole::CONSUMER,
        ]);

        return [
            'user'  => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }

    /**
     * Authenticate a user (admin or consumer) and issue an API token.
     *
     * @param array<string, mixed> $credentials
     * @return array{user: User, token: string}
     *
     * @throws BusinessRuleException
     */
    public function login(array $credentials): array
    {
        $user = $this->users->findByEmail($credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw new BusinessRuleException('The provided credentials are incorrect.', 401);
        }

        return [
            'user'  => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }

    /**
     * Revoke the user's current access token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
