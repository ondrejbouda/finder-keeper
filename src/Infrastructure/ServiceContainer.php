<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Infrastructure\Http\Actions\Albums\GetAlbums;
use App\Infrastructure\Http\Actions\Get;
use App\Infrastructure\Http\HttpApplication;
use App\Infrastructure\Spotify\Session\SpotifySessionFactory;
use App\Infrastructure\Spotify\SpotifyUserLibraryFacade;
use App\Infrastructure\User\UserSessionManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use SpotifyWebAPI\SpotifyWebAPI;
use function getenv;

final class ServiceContainer
{
    public function httpApplication() : HttpApplication
    {
        return new HttpApplication(
            $this->userSessionManager(),
            $this->spotifySessionFactory(),
            $this,
        );
    }

    private function userSessionManager() : UserSessionManager
    {
        static $userSessionManager;

        return $userSessionManager ?? $userSessionManager = new UserSessionManager();
    }

    private function spotifySessionFactory() : SpotifySessionFactory
    {
        static $spotifySessionFactory;

        return $spotifySessionFactory ?? new SpotifySessionFactory(
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

    /**
     * @throws RuntimeException
     */
    public function getHttpHandler(string $type) : RequestHandlerInterface
    {
        switch ($type) {
            case Get::class:
                return new Get(
                    $this->psr17factory(),
                );
            case GetAlbums::class:
                return new GetAlbums(
                    $this->spotifyUserLibrary(),
                    $this->psr17factory(),
                );
            default:
                throw new RuntimeException('Handler not found.');
        }
    }

    private function psr17factory() : Psr17Factory
    {
        return new Psr17Factory();
    }

    private function spotifyUserLibrary() : SpotifyUserLibraryFacade
    {
        static $spotifyUserLibrary;

        return $spotifyUserLibrary ?? $spotifyUserLibrary = new SpotifyUserLibraryFacade($this->spotifyWebAPI());
    }

    private function spotifyWebAPI() : SpotifyWebAPI
    {
        $api = new SpotifyWebAPI();
        $api->setReturnType(SpotifyWebAPI::RETURN_ASSOC);

        return $api;
    }
}
