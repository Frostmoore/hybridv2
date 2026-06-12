@extends('layouts.admin')

@section('title', 'Notifiche')
@section('header', 'Hybrid&Go - Notifiche')

@section('content')
    <div class="page">
        <h1>Notifiche</h1>
    </div>
    <div class="container-age">
        <div class="container mt-5">
            <h1>Invia una Notifica</h1>
            @if (session('status'))
                <div class="alert alert-warning">{{ session('status') }}</div>
            @endif
            {{-- ⚠️ Nel legacy questo form NON aveva alcun handler: la pagina era
                 incompleta. Portata per parità visiva; il submit segnala che la
                 funzione non è operativa (in attesa di specifiche — critics.md). --}}
            <form method="post" action="{{ url('notifiche.php') }}" id="notificationForm">
                @csrf
                <div class="mb-3">
                    <label for="userGroup" class="form-label">Seleziona il Gruppo di Utenti</label>
                    <select class="form-control" id="userGroup" name="userGroup" required>
                        <option value="" disabled selected>Seleziona un gruppo...</option>
                        <option value="iscritti">Iscritti</option>
                        <option value="nonIscritti">Non Iscritti</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="notificationTitle" class="form-label">Titolo della Notifica</label>
                    <input type="text" class="form-control" id="notificationTitle" name="notificationTitle" placeholder="Inserisci il titolo" required>
                </div>
                <div class="mb-3">
                    <label for="notificationText" class="form-label">Testo della Notifica</label>
                    <textarea class="form-control" id="notificationText" name="notificationText" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Invia</button>
            </form>
        </div>
    </div>
@endsection
