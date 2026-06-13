@extends('layouts.admin')

@section('title', 'Notifiche')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Notifiche</h1>
            <p>Invio di comunicazioni agli utenti delle app.</p>
        </div>
    </div>

    {{-- ⚠️ Nel legacy questo form NON aveva alcun handler: la pagina era
         incompleta. Resa onesta: placeholder non operativo. Le notifiche push
         reali si inviano dal pannello agenzie. Decisione sul da farsi rimandata
         (vedi critics.md). --}}
    <div class="adm-flash adm-flash--err" style="display:flex; gap:12px; align-items:flex-start;">
        <i class="fas fa-circle-info" style="margin-top:2px;"></i>
        <div>
            <strong>Sezione non ancora operativa.</strong>
            Non lo era nemmeno nel sistema precedente. Le notifiche push agli utenti
            si inviano dal <strong>pannello agenzie</strong> (Notifica a tutti / privata / selezionati).
            Questo modulo è un segnaposto in attesa di specifiche.
        </div>
    </div>

    @if (session('status'))
        <div class="adm-flash adm-flash--ok">{{ session('status') }}</div>
    @endif

    <div class="adm-block adm-block--narrow" style="opacity:.85;">
        <h2 class="adm-panel__title">Invia una notifica</h2>
        <p class="adm-panel__hint">Anteprima del modulo (non invia nulla finché la funzione non sarà definita).</p>
        <form method="post" action="{{ url('notifiche') }}" id="notificationForm">
            @csrf
            <div class="adm-field" style="margin-bottom:16px;">
                <label for="userGroup">Gruppo di utenti</label>
                <select class="adm-input" id="userGroup" name="userGroup" required>
                    <option value="" disabled selected>Seleziona un gruppo…</option>
                    <option value="iscritti">Iscritti</option>
                    <option value="nonIscritti">Non iscritti</option>
                </select>
            </div>
            <div class="adm-field" style="margin-bottom:16px;">
                <label for="notificationTitle">Titolo della notifica</label>
                <input type="text" class="adm-input" id="notificationTitle" name="notificationTitle" placeholder="Inserisci il titolo" required>
            </div>
            <div class="adm-field" style="margin-bottom:18px;">
                <label for="notificationText">Testo della notifica</label>
                <textarea class="adm-textarea" id="notificationText" name="notificationText" rows="4" required></textarea>
            </div>
            <button type="submit" class="adm-btn adm-btn--primary"><i class="fas fa-paper-plane"></i> Invia</button>
        </form>
    </div>
@endsection
