@extends('layouts.agencies')

@section('title', 'Home')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Ciao, {{ $operatore->username }}</h1>
            <p>Pannello operatori — gestisci utenti e notifiche della tua agenzia.</p>
        </div>
    </div>

    @php $isSuper = in_array($operatore->username, config('hybrid.agencies_superadmins'), true); @endphp

    <div class="adm-tiles">
        <a href="{{ url('utenti') }}" class="adm-tile">
            <span class="adm-tile__icon"><i class="fas fa-users"></i></span>
            <span class="adm-tile__label">Gestione Utenti</span>
        </a>
        <a href="{{ url('notifiche/tutti') }}" class="adm-tile adm-tile--green">
            <span class="adm-tile__icon"><i class="fas fa-bullhorn"></i></span>
            <span class="adm-tile__label">Notifica a Tutti</span>
        </a>
        <a href="{{ url('notifiche/privata') }}" class="adm-tile">
            <span class="adm-tile__icon"><i class="fas fa-user-tag"></i></span>
            <span class="adm-tile__label">Notifica Privata</span>
        </a>
        <a href="{{ url('notifiche/selezionati') }}" class="adm-tile adm-tile--amber">
            <span class="adm-tile__icon"><i class="fas fa-user-check"></i></span>
            <span class="adm-tile__label">Notifica Selezionati</span>
        </a>
        @if ($isSuper)
            <a href="{{ url('operatori') }}" class="adm-tile adm-tile--dark">
                <span class="adm-tile__icon"><i class="fas fa-user-gear"></i></span>
                <span class="adm-tile__label">Gestione Operatori</span>
            </a>
            <a href="{{ url('operatori/nuovo') }}" class="adm-tile adm-tile--dark">
                <span class="adm-tile__icon"><i class="fas fa-user-plus"></i></span>
                <span class="adm-tile__label">Aggiungi Operatore</span>
            </a>
        @endif
    </div>
@endsection
