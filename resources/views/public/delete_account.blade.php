@extends('layouts.public')

@section('title', 'Richiesta cancellazione account')

@section('content')
    <div class="delac-body">
        <div class="delac-container">
            <div class="delac-head">
                <h1>Elimina il tuo account</h1>
            </div>
            @if (session('esito'))
                <div class="delac-intro">
                    <h2>{{ session('esito.titolo') }}</h2>
                    <p>{{ session('esito.testo') }}</p>
                </div>
            @else
                <div class="delac-intro">
                    <p>Inserisci l'indirizzo email associato al tuo account: riceverai un link (valido 60 minuti) per confermare la cancellazione definitiva.</p>
                </div>
                <form method="post" action="{{ url($action) }}" class="delac-form">
                    @csrf
                    <div class="form-group">
                        <label for="email">Inserisci la tua email:</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <button type="submit" class="btn btn-danger mt-3">Invia richiesta</button>
                </form>
            @endif
        </div>
    </div>
@endsection
