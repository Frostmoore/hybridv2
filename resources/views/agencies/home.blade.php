@extends('layouts.agencies')

@section('title', 'Home — Pannello Agenzie')

@section('content')
    <div class="text-center mt-5">
        <h1 class="mb-4">Pannello Utente</h1>
        <p class="lead">Benvenuto, <strong>{{ $operatore->username }}</strong>!</p>

        <div class="row justify-content-center mt-4 mx-0">
            <div class="col-md-3 mb-3">
                <a href="{{ url('utenti.php') }}" class="btn btn-primary btn-lg w-100 py-4 shadow-lg">
                    <i class="fas fa-users fa-3x mb-2"></i><br>Gestione Utenti
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="{{ url('new_notification_all.php') }}" class="btn btn-success btn-lg w-100 py-4 shadow-lg">
                    <i class="fas fa-bullhorn fa-3x mb-2"></i><br>Notifica a Tutti
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="{{ url('new_notification_private.php') }}" class="btn btn-info btn-lg w-100 py-4 shadow-lg">
                    <i class="fas fa-user-tag fa-3x mb-2"></i><br>Notifica Privata
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="{{ url('new_notification_selected.php') }}" class="btn btn-warning btn-lg w-100 py-4 shadow-lg">
                    <i class="fas fa-user-check fa-3x mb-2"></i><br>Notifica Selezionati
                </a>
            </div>
            @if (in_array($operatore->username, config('hybrid.agencies_superadmins'), true))
                <div class="col-md-3 mb-3">
                    <a href="{{ url('operators.php') }}" class="btn btn-dark btn-lg w-100 py-4 shadow-lg">
                        <i class="fas fa-user-gear fa-3x mb-2"></i><br>Gestione Operatori
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="{{ url('addoperator.php') }}" class="btn btn-secondary btn-lg w-100 py-4 shadow-lg">
                        <i class="fas fa-user-plus fa-3x mb-2"></i><br>Aggiungi Operatore
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection
