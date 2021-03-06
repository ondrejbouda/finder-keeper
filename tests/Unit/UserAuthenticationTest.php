<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\UserAuthentication\GetUserAuthenticatedByCredentials;
use App\Application\UserAuthentication\RegisterUser;
use App\Domain\UserAuthentication\Aggregate\CannotRegisterUser;
use App\Domain\UserAuthentication\Aggregate\IsUserRegistered;
use App\Domain\UserAuthentication\Aggregate\User;
use App\Domain\UserAuthentication\Aggregate\UserCannotBeAuthenticated;
use App\Domain\UserAuthentication\AuthenticatedUserId;
use App\Domain\UserAuthentication\EmailAddress;
use App\Domain\UserAuthentication\PlaintextUserPassword;
use App\Domain\UserAuthentication\Repository\UserNotFound;
use App\Domain\UserAuthentication\Repository\UserRepository;
use App\Domain\UserAuthentication\UserCredentials;
use App\Domain\UserAuthentication\UserId;
use App\Domain\UserAuthentication\Username;
use App\Domain\UserAuthentication\UserPasswordHash;
use App\Domain\UserAuthentication\UserSessionToken;
use App\Infrastructure\UserAuthentication\PhpPasswordHashing;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class UserAuthenticationTest extends TestCase
{
    public function testUserCanRegisterAndAfterwardsIsAuthenticated() : void
    {
        $isUserRegistered = new class implements IsUserRegistered
        {
            public function __invoke(Username $username) : bool
            {
                return false;
            }
        };

        $userRepository = new class implements UserRepository
        {
            private ?User $savedUser = null;

            public function save(User $user) : void
            {
                $this->savedUser = $user;
            }

            public function get(AuthenticatedUserId $id) : User
            {
                throw new LogicException('Should not be called.');
            }

            /**
             * @throws void
             */
            public function getByUsername(Username $username) : User
            {
                if ($this->savedUser === null) {
                    throw new LogicException('User should have been saved.');
                }

                return $this->savedUser;
            }

            public function getByValidSessionToken(UserSessionToken $token, DateTimeImmutable $at) : User
            {
                throw new LogicException('Should not be called.');
            }
        };

        $username = 'ondrej@sample.com';
        $password = '1234567';

        $register = new RegisterUser($isUserRegistered, $userRepository, new PhpPasswordHashing());
        $register->__invoke(
            EmailAddress::fromString($username),
            PlaintextUserPassword::fromString($password),
        );

        $credentials = UserCredentials::fromStrings($username, $password);
        $this->assertUserIsAuthenticated($userRepository, $credentials);
    }

    public function testUserRegistrationFailsWhenEmailAddressAlreadyRegistered() : void
    {
        $isUserRegistered = new class implements IsUserRegistered
        {
            public function __invoke(Username $username) : bool
            {
                return true;
            }
        };

        $userRepository = new class implements UserRepository
        {
            public function save(User $user) : void
            {
                throw new LogicException('Should not be called.');
            }

            public function get(AuthenticatedUserId $id) : User
            {
                throw new LogicException('Should not be called.');
            }

            /**
             * @throws void
             */
            public function getByUsername(Username $username) : User
            {
                throw new LogicException('Should not be called.');
            }

            public function getByValidSessionToken(UserSessionToken $token, DateTimeImmutable $at) : User
            {
                throw new LogicException('Should not be called.');
            }
        };

        $username = 'ondrej@sample.com';
        $password = '1234567';

        $register = new RegisterUser($isUserRegistered, $userRepository, new PhpPasswordHashing());

        self::expectException(CannotRegisterUser::class);
        $register->__invoke(
            EmailAddress::fromString($username),
            PlaintextUserPassword::fromString($password),
        );
    }

    public function testUserIsNotAuthenticatedWhenEmailAddressNotFound() : void
    {
        $userRepository = new class implements UserRepository
        {
            public function save(User $user) : void
            {
                throw new LogicException('Should not be called.');
            }

            public function get(AuthenticatedUserId $id) : User
            {
                throw new LogicException('Should not be called.');
            }

            /**
             * @throws void
             */
            public function getByUsername(Username $username) : User
            {
                throw UserNotFound::byUsername($username);
            }

            public function getByValidSessionToken(UserSessionToken $token, DateTimeImmutable $at) : User
            {
                throw new LogicException('Should not be called.');
            }
        };

        $credentials = UserCredentials::fromStrings('ondrej@sample.com', '1234567');

        self::expectException(UserCannotBeAuthenticated::class);
        $this->assertUserIsAuthenticated($userRepository, $credentials);
    }

    public function testUserIsNotAuthenticatedWithWrongPassword() : void
    {
        $userRepository = new class implements UserRepository
        {
            public function save(User $user) : void
            {
                throw new LogicException('Should not be called.');
            }

            public function get(AuthenticatedUserId $id) : User
            {
                throw new LogicException('Should not be called.');
            }

            /**
             * @throws void
             */
            public function getByUsername(Username $username) : User
            {
                $password = PlaintextUserPassword::fromString('abcdefgh');
                $passwordHash =  UserPasswordHash::fromPassword($password, new PhpPasswordHashing());

                return User::fromStoredValues(EmailAddress::fromString($username->toString()), $passwordHash);
            }

            public function getByValidSessionToken(UserSessionToken $token, DateTimeImmutable $at) : User
            {
                throw new LogicException('Should not be called.');
            }
        };

        $credentials = UserCredentials::fromStrings('ondrej@sample.com', '1234567');

        self::expectException(UserCannotBeAuthenticated::class);
        $this->assertUserIsAuthenticated($userRepository, $credentials);
    }

    private function assertUserIsAuthenticated(
        UserRepository $userRepository,
        UserCredentials $credentials
    ) : void {
        $getUserAuthenticatedByCredentials = new GetUserAuthenticatedByCredentials(
            $userRepository,
            new PhpPasswordHashing(),
        );

        self::assertEquals(
            AuthenticatedUserId::fromId(UserId::fromString($credentials->username())),
            $getUserAuthenticatedByCredentials->__invoke($credentials),
        );
    }
}
