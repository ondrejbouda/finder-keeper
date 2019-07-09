<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Relay\Relay;
use Zend\HttpHandlerRunner\Emitter\SapiEmitter;

class HttpApplication implements RequestHandlerInterface
{
    /** @var MiddlewareInterface[] */
    private $middleware;

    public function __construct(MiddlewareInterface ...$middleware)
    {
        $this->middleware = $middleware;
    }

    public function run() : void
    {
        $psr17Factory = new Psr17Factory();

        $creator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        $request = $creator->fromGlobals();

        $response = $this->handle($request);

        (new SapiEmitter())->emit($response);
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $relay = new Relay($this->middleware);

        return $relay->handle($request);
    }
}