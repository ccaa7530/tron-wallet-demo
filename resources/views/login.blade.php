@extends('layouts.app')
 
@section('title', 'Login')
 
@section('content')
  <form action="{{ route('authenticate') }}" method="post" class="flex flex-col gap-5 p-5 m-auto bg-blue-200"> 
    <label for="email">
        Email
        <input name="email" type="text" placeholder="輸入帳號">
    </label>
    <label for="password">
        Password
        <input name="password" type="password" placeholder="輸入密碼"> 
    </label>
    <div>
        <button type="submit">提交</button>
        @csrf
    </div>
  </from>
@endsection