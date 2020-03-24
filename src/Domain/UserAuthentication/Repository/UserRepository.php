<?php

declare(strict_types=1);

namespace App\Domain\UserAuthentication\Repository;

use App\Domain\UserAuthentication\Aggregate\User;
use App\Domain\UserAuthentication\EmailAddress;
use App\Domain\UserAuthentication\UserSessionToken;
use DateTimeImmutable;

interface UserRepository
{
    public function save(User $user) : void;

    /**
     * @throws UserNotFound
     */
    public function get(EmailAddress $emailAddress) : User;

    /**
     * @throws UserNotFound
     */
    public function getByValidSessionToken(UserSessionToken $token, DateTimeImmutable $at) : User;
}
