<?php

namespace App\Http\Controllers\Authentication;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Routing\Controller;
use App\Services\UserServices\UserService;
use App\Http\Requests\AuthRequests\AuthRequest;
use App\Http\Requests\AuthRequests\LoginRequest;
class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('Login.login');
    }
    public function showRegisterForm(){
        return view('Login.register');
    }

    public function register(AuthRequest $request, UserService $userService)
    {
        $data = $request->validated();

        $user = $userService->createUser($data);

        Auth::login($user);

        return redirect('/user/dashboard');
    }


    public function login(LoginRequest $request, UserService $userService)
    {
        $credentials = $request->validated();

        if ($userService->Login($credentials)) {
            $request->session()->regenerate();

            $url = Auth::user()->is_admin ? '/admin' : '/user/dashboard';

            return redirect()->intended($url);
        }

        return back()->withErrors([
            'email' => 'بيانات الاعتماد هذه لا تتطابق مع سجلاتنا.',
        ])->onlyInput('email');
    }

    public function logout(Request $request){
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }


}
