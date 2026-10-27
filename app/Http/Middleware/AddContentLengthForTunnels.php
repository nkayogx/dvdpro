<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AddContentLengthForTunnels
{
    /**
     * Add a Content-Length header to responses served through a tunnel (e.g. VS Code port forwarding).
     *
     * PHP's built-in server ends dynamic responses by closing the connection, which tunnels keep open,
     * so browsers wait until the tunnel times out unless the response length is known.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->hasHeader('X-Forwarded-Host')
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || $response->headers->has('Content-Length')) {
            return $response;
        }

        $response->headers->set('Content-Length', (string) strlen((string) $response->getContent()));

        return $response;
    }
}
