<?php

namespace Everest\Services\Users;

use Ramsey\Uuid\Uuid;
use Everest\Models\User;
use Everest\Facades\Activity;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Contracts\Auth\PasswordBroker;
use Everest\Contracts\Repository\UserRepositoryInterface;

class UserCreationService
{
    /**
     * UserCreationService constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private Hasher $hasher,
        private PasswordBroker $passwordBroker,
        private UserRepositoryInterface $repository,
    ) {
    }

    /**
     * Create a new user on the system.
     *
     * @throws \Exception
     * @throws \Everest\Exceptions\Model\DataValidationException
     */
    public function handle(array $data): User
    {
        if (array_key_exists('password', $data) && !empty($data['password'])) {
            $data['password'] = $this->hasher->make($data['password']);
        }

        $generateResetToken = empty($data['password']);
        if ($generateResetToken) {
            $data['password'] = $this->hasher->make(str_random(30));
        }

        // Stored hashed (not merely encrypted) since it is verified with password_verify()
        // in ForgotPasswordController — an encrypted value can never match a bcrypt check.
        $data['recovery_code'] = $this->hasher->make(str_random(32));

        // A closure transaction rolls back when creation throws, rather than leaving the
        // connection stuck mid-transaction for the rest of the request.
        /** @var User $user */
        $user = $this->connection->transaction(function () use ($data, $generateResetToken) {
            $user = $this->repository->create(array_merge($data, [
                'uuid' => Uuid::uuid4()->toString(),
            ]), true, true);

            if ($generateResetToken) {
                $this->passwordBroker->createToken($user);
            }

            return $user;
        });

        Activity::event('user:user.create')
            ->subject($user)
            ->property([
                'email' => $user->email,
                'username' => $user->username,
                'admin' => $user->root_admin,
            ])
            ->log();

        return $user;
    }
}
