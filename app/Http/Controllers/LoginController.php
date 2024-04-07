<?php
 
namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class LoginController extends Controller
{
  
  public function registerPage() 
  {
    return view('register');
  }
  
  public function loginPage()
  {
    return view('login');
  }

  public function testUser(Request $request)
  {
    $time = time();
    User::create([
      'name' => "Test_{$time}",
      'email' => "test{$time}@test.com",
      'password' => Hash::make('123123')
    ]);

    return redirect()->back();
  }
  
  public function register(Request $request)
  {

    $request->validate([
      'name' => 'required',
      'email' => 'required|email|max:250|unique:users',
      'password' => 'required'
    ]);

    User::create([
      'name' => $request->name,
      'email' => $request->email,
      'password' => Hash::make($request->password)
    ]);

    $credentials = $request->only('email', 'password');
    Auth::attempt($credentials);
    $request->session()->regenerate();

    return redirect()->route('wallet.list')->withSuccess('You have successfully registered & logged in!');
  }

  /**
   * Handle an authentication attempt.
   */
  public function authenticate(Request $request): RedirectResponse
  {
      $credentials = $request->validate([
          'email' => ['required', 'email'],
          'password' => ['required'],
      ]);

      if (Auth::attempt($credentials)) {
          $request->session()->regenerate();

          return redirect()->route('wallet.list')->withSuccess('You have successfully logged in!');
      }
      return back()->withErrors([
          'email' => 'The provided credentials do not match our records.',
      ])->onlyInput('email');
  }

  
  /**
   * Log out the user from application.
   */
  public function logout(Request $request)
  {
      Auth::logout();
      $request->session()->invalidate();
      $request->session()->regenerateToken();
      return redirect()->route('welcome')
        ->withSuccess('You have logged out successfully!');;
  }
}