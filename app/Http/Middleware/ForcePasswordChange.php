<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pengguna dengan kata sandi awal wajib menggantinya sebelum memakai aplikasi. */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->must_change_password && ! $request->routeIs('profile.password', 'profile.password.update', 'logout')) {
            return redirect()->route('profile.password')->with('warning', 'Demi keamanan, silakan ganti kata sandi awal Anda terlebih dahulu.');
        }

        return $next($request);
    }
}
