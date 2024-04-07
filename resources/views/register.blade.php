@extends('layouts.app')
 
@section('title', 'Register')

@section('content')
    <h1 class="">註冊</h1>
    <form action="register" method="post"> 
        <input name="email" type="text" placeholder="輸入帳號"> 
        <input name="name" type="text" placeholder="輸入名稱"> 
        <input name="password" type="password" placeholder="輸入密碼"> 
        <input type="submit" value="提交"> <input type="hidden" name="_token" value="{{ csrf_token() }}"> 
    </from>
@endsection