<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSkillIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('skill-integration.token');
        $providedToken = $request->bearerToken();

        abort_unless(
            is_string($expectedToken) && strlen($expectedToken) >= 32
                && is_string($providedToken) && hash_equals($expectedToken, $providedToken),
            401,
            'Invalid integration token.',
            ['WWW-Authenticate' => 'Bearer'],
        );

        return $next($request);
    }
}
