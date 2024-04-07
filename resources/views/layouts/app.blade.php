<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <title>@yield('title')</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @vite(['resources/css/app.css'])

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
    </head>
    <body class="flex justify-center max-w-[1200px] flex-wrap flex-row mx-auto antialiased">
      <header class="flex justify-between basis-full p-5 bg-gray-300 items-center">
        <div>
          Tron Wallet
        </div>
        <div class="flex gap-5 items-center">
          <!-- @if (Auth::check())
            Hi {{ Auth::user()->name }}
            <form action="{{ route('logout') }}" method="post">
              <button type="submit">登出</button>
              @csrf
            </form>
          @else 
            <a href="{{ route('login') }}">登入</a>
            <a href="{{ route('register') }}">註冊</a>
          @endif -->
        </div>
      </header>

      <section class="p-5 flex flex-col gap-2 w-full">
        @foreach ($errors->all() as $error)
          <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative w-full" role="alert">
            <span class="block sm:inline">{{ $error }}</span>
          </div>
        @endforeach
        @if (Session::has('success'))
          <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative w-full" role="alert">
            <span class="block sm:inline">
              {{ Session::get('success') }}
            </span>
          </div>
        @endif
      </section>

      <div class="flex flex-1 basis-full">
        @yield('content')
      </div>

      @yield('script')
    </body>
</html>