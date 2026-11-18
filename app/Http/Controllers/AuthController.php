<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', ['school' => \App\Models\SchoolProfile::current()]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($data['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $sec = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages(['username' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$sec} detik."]);
        }

        $field = filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::where($field, $data['username'])->first();

        if (! $user || ! Auth::attempt([$field => $data['username'], 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            ActivityLogger::log('login_gagal', 'autentikasi', 'Percobaan masuk gagal untuk "'.Str::limit($data['username'], 60).'"');
            $msg = $user && ! $user->is_active ? 'Akun Anda tidak aktif. Hubungi Operator atau Super Admin.' : 'Nama pengguna atau kata sandi salah.';
            throw ValidationException::withMessages(['username' => $msg]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $u = Auth::user();
        $u->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        ActivityLogger::log('login', 'autentikasi', "{$u->name} masuk ke aplikasi.");

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        ActivityLogger::log('logout', 'autentikasi', (Auth::user()?->name ?? '-').' keluar dari aplikasi.');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Anda telah keluar.');
    }
}
