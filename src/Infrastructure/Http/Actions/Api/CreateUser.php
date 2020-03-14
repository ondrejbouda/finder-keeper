<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Actions\Api;

use App\Application\UserAuthentication\RegisterUser;
use App\Domain\UserAuthentication\Aggregate\CannotRegisterUser;
use App\Domain\UserAuthentication\UserCredentials;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function json_decode;

final class CreateUser implements RequestHandlerInterface
{
    private ResponseFactoryInterface $responseFactory;
    private RegisterUser $registerUser;

    public function __construct(RegisterUser $registerUser, ResponseFactoryInterface $responseFactory)
    {
        $this->registerUser = $registerUser;
        $this->responseFactory = $responseFactory;
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $requestPayloadString = (string) $request->getBody();
        $requestPayload = json_decode($requestPayloadString, true);

        // TODO: handle validation
        $credentials = UserCredentials::fromStrings(
            (string) $requestPayload['email'],
            (string) $requestPayload['password'],
        );

        try {
            $this->registerUser->__invoke($credentials);
        } catch (CannotRegisterUser $e) {
            return $this->responseFactory
                ->createResponse(400, $e->getMessage());
        }

        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json');
    }
}
