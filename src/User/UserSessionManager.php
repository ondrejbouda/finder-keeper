<?php

declare(strict_types=1);

namespace Bouda\SpotifyAlbumTagger\User;

use DateTimeImmutable;
use HansOtt\PSR7Cookies\SetCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use function array_key_exists;
use function serialize;
use function unserialize;

class UserSessionManager implements InitializableUserSessionManager, InitializedUserSessionManager
{
    private const COOKIE_NAME = 'userSession';

    /** @var UserSession */
    private $session;

    public function initialize(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) : ResponseInterface {
        $cookies = $request->getCookieParams();

        if (array_key_exists(self::COOKIE_NAME, $cookies)) {
            $this->session = unserialize($cookies['userSession']);
        } else {
            $this->session = new UserSession();
            $response = $this->saveSession($response);
        }

        return $response;
    }

    public function getSession() : UserSession
    {
        return $this->session;
    }

    public function saveSession(ResponseInterface $response) : ResponseInterface
    {
        $response = $response->withoutHeader('Set-Cookie');

        $expiresAt = new DateTimeImmutable();
        $expiresAt = $expiresAt->modify('+ 1 month');

        $cookie = SetCookie::thatExpires(self::COOKIE_NAME, serialize($this->session), $expiresAt);

        return $cookie->addToResponse($response);
    }
}
