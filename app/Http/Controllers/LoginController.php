<?php

namespace App\Http\Controllers;

use App\Models\User;
use Faker\Generator;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginController extends Controller
{

    public function login(){
        dd('test');
    }

    public function register(Request $request)
    {
        $email = $request->input('email', '');
        $name = $request->input('name', '');
        $handle = $request->input('handle', '');
        $password = $request->input('password', '');
        $confirmPassword = $request->input('confirm_password', '');
        if (User::query()->where('email', $email)->exists()) {
            return back()->withErrors([
                'email' => 'The provided email already exists.'
            ]);
        }
        if ($password != $confirmPassword) {
            return back()->withErrors([
                'confirm_password' => 'Confirm password must be the same with password.'
            ]);
        }
        $bio = (app()->make(Generator::class))->sentence(6);
        $userArr = [
            'name' => $name,
            'handle' => $handle,
            'profile_picture'=> 'https://picsum.photos/seed/'.$name.'/100/100',
            'profile_background'=> 'https://picsum.photos/seed/'.$handle.'/600/200',
            'profile_bio'=> $bio,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'remember_token' => Str::random(10),
            'pending_notifications' => 0,
        ];
        User::query()->insert($userArr);
        return redirect('/login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse{
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
