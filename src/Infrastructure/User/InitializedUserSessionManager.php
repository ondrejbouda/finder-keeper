<?php

declare(strict_types=1);

namespace App\Infrastructure\User;

use Psr\Http\Message\ResponseInterface;

interface InitializedUserSessionManager
{
    public function getSession() : UserSession;

    public function saveSession(ResponseInterface $response) : ResponseInterface;
}