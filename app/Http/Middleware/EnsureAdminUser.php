<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            $userId = (int) $request->query('user_id', 0);
            if ($userId > 0) {
                $user = User::query()->find($userId);
            }
        }

        if (! $user || ! $user->is_admin) {
            abort(403, 'Acesso restrito para administradores.');
        }

        return $next($request);
    }
}
