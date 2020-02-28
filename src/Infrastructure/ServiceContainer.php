<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Infrastructure\Http\Actions\Albums\GetAlbums;
use App\Infrastructure\Http\Actions\Api\CreateSession;
use App\Infrastructure\Http\Actions\Api\GetHealthCheck;
use App\Infrastructure\Http\Actions\Get;
use App\Infrastructure\Http\HandlerFactoryCollection;
use App\Infrastructure\Http\HttpApplication;
use App\Infrastructure\Http\JsonRequestValidatingMiddleware;
use App\Infrastructure\Http\MiddlewareStack;
use App\Infrastructure\Http\MiddlewareStackByUriPath;
use App\Infrastructure\Http\RequestHandlingMiddleware;
use App\Infrastructure\Http\SpotifySessionMiddleware;
use App\Infrastructure\Http\UserSessionMiddleware;
use App\Infrastructure\Spotify\Session\SpotifySessionFactory;
use App\Infrastructure\Spotify\SpotifyUserLibraryFacade;
use App\Infrastructure\User\UserSessionManager;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Server\RequestHandlerInterface;
use SpotifyWebAPI\SpotifyWebAPI;
use Throwable;
use function file_get_contents;
use function getenv;
use function sprintf;

final class ServiceContainer
{
    public function httpApplication() : HttpApplication
    {
        return new HttpApplication(
            MiddlewareStackByUriPath::from(
                '/api/',
                MiddlewareStack::fromArray(
                    $this->jsonRequestValidatingMiddleware(),
                    $this->apiRequestHandlingMiddleware(),
                ),
            ),
            MiddlewareStackByUriPath::from(
                '/',
                MiddlewareStack::fromArray(
                    $this->userSessionMiddleware(),
                    $this->spotifySessionMiddleware(),
                    $this->webRequestHandlingMiddleware(),
                ),
            ),
        );
    }

    private function jsonRequestValidatingMiddleware() : JsonRequestValidatingMiddleware
    {
        return new JsonRequestValidatingMiddleware(
            $this->serverRequestValidator(),
            $this->psr17factory(),
        );
    }

    private function serverRequestValidator() : ServerRequestValidator
    {
        $specificationFilename = __DIR__ . '/Http/Actions/Api/openapi.yaml';
        $specification = file_get_contents($specificationFilename);

        if ($specification === false) {
            throw new LogicException(sprintf(
                'Api specification not found at "%s".',
                $specificationFilename,
            ));
        }

        static $validator;
        try {
            return $validator
                ?? $validator = (new ValidatorBuilder())->fromYaml($specification)->getServerRequestValidator();
        } catch (Throwable $e) {
            throw new LogicException('Json server request validator initialization failed.', 0, $e);
        }
    }

    private function apiRequestHandlingMiddleware() : RequestHandlingMiddleware
    {
        return new RequestHandlingMiddleware(HandlerFactoryCollection::fromArray([
            'GET /api/v1/health-check' => function () : RequestHandlerInterface {
                return new GetHealthCheck(
                    $this->psr17factory(),
                );
            },
            'POST /api/v1/session' => function () : RequestHandlerInterface {
                return new CreateSession(
                    $this->psr17factory(),
                    $this->psr17factory(),
                );
            },
        ]));
    }

    private function userSessionMiddleware() : UserSessionMiddleware
    {
        return new UserSessionMiddleware($this->userSessionManager());
    }

    private function spotifySessionMiddleware() : SpotifySessionMiddleware
    {
        return new SpotifySessionMiddleware($this->spotifySessionFactory());
    }

    private function webRequestHandlingMiddleware() : RequestHandlingMiddleware
    {
        return new RequestHandlingMiddleware(HandlerFactoryCollection::fromArray([
            'GET /' => function () : RequestHandlerInterface {
                return new Get(
                    $this->psr17factory(),
                );
            },
            'GET /albums' => function () : RequestHandlerInterface {
                return new GetAlbums(
                    $this->spotifyUserLibrary(),
                    $this->psr17factory(),
                );
            },
        ]));
    }

    private function userSessionManager() : UserSessionManager
    {
        return new UserSessionManager();
    }

    private function spotifySessionFactory() : SpotifySessionFactory
    {
        return new SpotifySessionFactory(
            (string) getenv('SPOTIFY_CLIENT_ID'),
            (string) getenv('SPOTIFY_CLIENT_SECRET'),
            [
                // Library
                'user-library-read',
                'user-library-modify',
                // Playlists
                'playlist-read-private',
                'playlist-modify-public',
                'playlist-modify-private',
                'playlist-read-collaborative',
                // Listening History
                'user-read-recently-played',
                'user-top-read',
                // Users
                'user-read-private',
                'user-read-email',
                'user-read-birthdate',
                // Spotify Connect
                'user-modify-playback-state',
                'user-read-currently-playing',
                'user-read-playback-state',
                // Follow
                'user-follow-modify',
                'user-follow-read',
            ]
        );
    }

    private function psr17factory() : Psr17Factory
    {
        static $factory;

        return $factory ?? $factory = new Psr17Factory();
    }

    private function spotifyUserLibrary() : SpotifyUserLibraryFacade
    {
        return new SpotifyUserLibraryFacade($this->spotifyWebAPI());
    }

    private function spotifyWebAPI() : SpotifyWebAPI
    {
        $api = new SpotifyWebAPI();
        $api->setReturnType(SpotifyWebAPI::RETURN_ASSOC);

        return $api;
    }
}
