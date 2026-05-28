<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;
use Symfony\Component\HttpFoundation\Response;

class RefreshTokenCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->is('oauth/token') &&
            $request->input('grant_type') === 'refresh_token' &&
            ! $request->filled('refresh_token')
        ) {
            $refreshToken = $request->cookie('refresh_token');

            if ($refreshToken) {
                $request->request->set('refresh_token', $refreshToken);
            }
        }

        $response = $next($request);

        if ($request->is('oauth/token') && $response instanceof JsonResponse) {
            $data = $response->getData(true);

            if (is_array($data) && isset($data['refresh_token'])) {
                $refreshToken = $data['refresh_token'];

                unset($data['refresh_token']);

                $response->setData($data);

                $response->headers->setCookie(new HttpCookie(
                    name: 'refresh_token',
                    value: $refreshToken,
                    expire: time() + 60 * 60 * 24 * 30,
                    path: '/',
                    domain: null,
                    secure: $request->isSecure(),
                    httpOnly: true,
                    raw: false,
                    sameSite: 'strict'
                ));
            }
        }

        return $response;
    }
}
