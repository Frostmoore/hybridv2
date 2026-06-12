@extends('layouts.public')

@section('title', 'Login')

@section('content')
    <div class="login">
        <h1>Login</h1>
        @if ($errors->any())
            <div class="alert alert-danger" style="max-width:400px;margin:10px auto;">{{ $errors->first() }}</div>
        @endif
        <form action="{{ url('authenticate.php') }}" method="post">
            @csrf
            <label for="nomeutente">
                <i class="fas fa-user"></i>
            </label>
            <input type="text" name="nomeutente" placeholder="Nome Utente" id="nomeutente" required>
            <label for="password">
                <i class="fas fa-lock"></i>
            </label>
            <input type="password" name="password" placeholder="Password" id="password" required>
            <input type="submit" value="Login">
        </form>
    </div>
@endsection
