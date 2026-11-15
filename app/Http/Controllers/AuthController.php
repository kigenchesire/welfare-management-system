<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** The only password rule in the system: at most six characters, any characters allowed. */
    public const PASSWORD_RULES = ['required', 'string', 'max:6'];

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:100'],
            'password' => self::PASSWORD_RULES,
        ]);

        $throttleKey = Str::lower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages(['login' => 'Too many attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.']);
        }

        // Sign in with National ID / username, email or phone number.
        $login = trim($data['login']);
        $user = User::where('username', $login)->orWhere('email', $login)->orWhere('phone', $login)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['login' => 'The login details are incorrect.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => 'Your account is inactive. Please contact the welfare office.']);
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLogger::log('auth.login', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showChangePassword()
    {
        return view('auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string', 'max:6', 'current_password'],
            'password' => array_merge(self::PASSWORD_RULES, ['confirmed']),
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $request->password, 'must_change_password' => false])->save();
        AuditLogger::log('auth.password_changed', $user);

        return redirect()->route('dashboard')->with('success', 'Your password has been changed.');
    }
}
