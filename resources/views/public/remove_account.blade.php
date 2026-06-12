@extends('layouts.public')

@section('title', 'Eliminazione Definitiva Account')

@section('content')
    <div class="delac-body">
        <div class="delac-container">
            <div class="delac-head">
                <h1>Eliminazione Definitiva Account</h1>
            </div>
            @if (session('esito'))
                <div class="delac-intro">
                    <h2>{{ session('esito.titolo') }}</h2>
                    <p>{{ session('esito.testo') }}</p>
                </div>
            @else
                <div class="delac-intro">
                    <p>Per confermare la cancellazione <strong>definitiva</strong> del tuo account, inserisci email e password.</p>
                    <p><strong style="color:#a80000!important;">Attenzione:</strong> l'operazione non è reversibile.</p>
                </div>
                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <form method="post" action="{{ url('remove_account.php') }}" class="delac-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="form-group mt-2">
                        <label for="password">Password:</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-danger mt-3">Elimina definitivamente</button>
                </form>
            @endif
        </div>
    </div>
@endsection
