@extends('layouts.public')

@section('title', 'Richiesta cancellazione account')

@section('content')
    <div class="adm-authwrap">
        <div class="adm-authcard">
            <div class="pub-icon pub-icon--err"><i class="fas fa-trash-can"></i></div>
            <h1>Elimina il tuo account</h1>
            @if (session('esito'))
                <div class="adm-flash adm-flash--ok" style="text-align:center;">
                    <strong>{{ session('esito.titolo') }}</strong><br>
                    {{ session('esito.testo') }}
                </div>
            @else
                <p class="sub">Inserisci l'indirizzo email associato al tuo account: riceverai un link (valido 60 minuti) per confermare la cancellazione definitiva.</p>
                <form method="post" action="{{ url($action) }}">
                    @csrf
                    <div class="adm-field" style="margin-bottom:16px;">
                        <label for="email">La tua email</label>
                        <input type="email" class="adm-input" id="email" name="email" required>
                    </div>
                    <button type="submit" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center; background:var(--adm-danger);">Invia richiesta</button>
                </form>
            @endif
        </div>
    </div>
@endsection
