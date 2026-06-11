<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Ingresa tu correo.',
            'email.email' => 'Ingresa un correo valido.',
            'password.required' => 'Ingresa tu contrasena.',
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['login' => 'Usuario o contrasena incorrectos. Verifica los datos e intenta nuevamente.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('base-asignada.index');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
