@extends('layouts.public')

@section('title', 'Eliminazione Definitiva Account')

@section('content')
    <div class="adm-authwrap">
        <div class="adm-authcard">
            <div class="pub-icon pub-icon--err"><i class="fas fa-triangle-exclamation"></i></div>
            <h1>Eliminazione definitiva</h1>
            @if (session('esito'))
                <div class="adm-flash adm-flash--ok" style="text-align:center;">
                    <strong>{{ session('esito.titolo') }}</strong><br>
                    {{ session('esito.testo') }}
                </div>
            @else
                <p class="sub">Per confermare la cancellazione <strong>definitiva</strong> del tuo account inserisci email e password. L'operazione non è reversibile.</p>
                @if ($errors->any())
                    <div class="adm-flash adm-flash--err">{{ $errors->first() }}</div>
                @endif
                <form method="post" action="{{ url('remove_account.php') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="adm-field" style="margin-bottom:14px;">
                        <label for="email">Email</label>
                        <input type="email" class="adm-input" id="email" name="email" required>
                    </div>
                    <div class="adm-field" style="margin-bottom:18px;">
                        <label for="password">Password</label>
                        <input type="password" class="adm-input" id="password" name="password" required>
                    </div>
                    <button type="submit" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center; background:var(--adm-danger);">Elimina definitivamente</button>
                </form>
            @endif
        </div>
    </div>
@endsection
