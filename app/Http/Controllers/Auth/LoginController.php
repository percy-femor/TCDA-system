<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Desks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function choose(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRoute());
        }

        return view('auth.choose', ['desks' => Desks::all()]);
    }

    public function create(string $desk): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->homeRoute());
        }

        $chosen = Desks::get($desk);
        abort_unless($chosen, 404);

        return view('auth.login', ['desk' => $chosen]);
    }

    public function store(Request $request, ?string $desk = null): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $chosen = $desk ? Desks::get($desk) : null;
        abort_if($desk && ! $chosen, 404);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Those credentials were not found in the registry.',
            ])->onlyInput('email');
        }

        $user = $request->user();
        if ($chosen && $user->role->value !== $chosen['role']) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'That account does not belong to the '.$chosen['title'].'. Use the correct desk.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route($user->homeRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
