<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pemeriksaan hak akses berbasis permission. Pemakaian: ->middleware('permission:students.manage,students.view') (salah satu). */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        abort_unless($user && $user->hasAnyPermission($permissions), 403, 'Anda tidak memiliki hak akses untuk halaman ini.');

        return $next($request);
    }
}
