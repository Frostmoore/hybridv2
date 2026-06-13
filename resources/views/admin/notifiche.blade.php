@extends('layouts.admin')

@section('title', 'Notifiche')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Notifiche</h1>
            <p>Broadcast a tutti gli utenti del sistema (manutenzione, privacy, comunicazioni globali).</p>
        </div>
    </div>

    <div class="adm-flash adm-flash--err" style="display:flex; gap:12px; align-items:flex-start;">
        <i class="fas fa-triangle-exclamation" style="margin-top:2px;"></i>
        <div>
            <strong>Attenzione: invio a TUTTI gli utenti.</strong>
            Questa notifica raggiunge ogni utente di ogni agenzia (in-app + push OneSignal dove configurato).
            Usala solo per comunicazioni di sistema. Per notifiche di una singola agenzia usa il <strong>pannello agenzie</strong>.
        </div>
    </div>

    @if (session('status'))
        <div class="adm-flash adm-flash--ok">{{ session('status') }}</div>
    @endif

    <div class="adm-block adm-block--narrow">
        <h2 class="adm-panel__title">Invia una notifica a tutti</h2>
        <form method="post" action="{{ url('notifiche') }}" id="notificationForm"
              onsubmit="return confirm('Inviare questa notifica a TUTTI gli utenti del sistema?');">
            @csrf
            <div class="adm-field" style="margin-bottom:16px;">
                <label for="notificationTitle">Titolo della notifica</label>
                <input type="text" class="adm-input" id="notificationTitle" name="notificationTitle" placeholder="Es. Manutenzione programmata" required>
            </div>
            <div class="adm-field" style="margin-bottom:16px;">
                <label for="notificationText">Testo della notifica</label>
                <textarea class="adm-textarea" id="notificationText" name="notificationText" rows="4" required></textarea>
            </div>
            <div class="adm-field" style="margin-bottom:18px;">
                <label for="notificationExpiry">Scadenza banner <span class="text-muted">(opzionale, default 30 giorni)</span></label>
                <input type="date" class="adm-input" id="notificationExpiry" name="notificationExpiry">
            </div>
            <button type="submit" class="adm-btn adm-btn--primary"><i class="fas fa-paper-plane"></i> Invia a tutti</button>
        </form>
    </div>
@endsection
