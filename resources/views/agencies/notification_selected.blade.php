@extends('layouts.agencies')

@section('title', 'Notifica a Selezionati')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Notifiche</h1>
            <p>Invia comunicazioni push agli utenti dell'agenzia.</p>
        </div>
    </div>

    @include('agencies._notif_tabs', ['current' => 'selezionati'])

    <div class="adm-block adm-block--narrow">
        <h2 class="adm-panel__title">Notifica a utenti selezionati</h2>
        <p class="adm-panel__hint">Scegli i destinatari tra gli utenti con notifiche push attive.</p>
        <form id="notifForm" enctype="multipart/form-data">
            <input type="hidden" name="agenziaid" value="{{ $agid }}">
            <div class="adm-field" style="margin-bottom:14px;">
                <label>Destinatari <span class="adm-req">*</span></label>
                <div style="border:1px solid var(--adm-border); border-radius:10px; padding:10px 12px; max-height:240px; overflow-y:auto;">
                    @forelse ($clienti as $c)
                        <label style="display:flex; align-items:center; gap:8px; padding:4px 0; cursor:pointer;">
                            <input type="checkbox" name="playerid[]" value="{{ $c->playerid }}" style="accent-color:var(--adm-primary);">
                            {{ $c->cognome }} {{ $c->nome }} <span class="text-muted">({{ $c->username }})</span>
                        </label>
                    @empty
                        <span class="text-muted">Nessun utente con notifiche push attive.</span>
                    @endforelse
                </div>
            </div>
            <div class="adm-field" style="margin-bottom:14px;">
                <label>Titolo <span class="adm-req">*</span></label>
                <input type="text" class="adm-input" name="titolo" required>
            </div>
            <div class="adm-field" style="margin-bottom:14px;">
                <label>Testo <span class="adm-req">*</span></label>
                <textarea class="adm-textarea" name="testo" rows="4" required></textarea>
            </div>
            <div class="adm-field" style="margin-bottom:14px;">
                <label>Immagine (JPG/PNG/WEBP, max 10MB)</label>
                <input type="file" class="adm-input" name="media" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="adm-field" style="margin-bottom:18px;">
                <label>Link (opzionale)</label>
                <input type="url" class="adm-input" name="link" placeholder="https://…">
            </div>
            <button type="submit" class="adm-btn adm-btn--primary"><i class="fas fa-user-check"></i> Invia ai selezionati</button>
        </form>
        <div id="esito" style="margin-top:14px;"></div>
    </div>
@endsection

@push('scripts')
<script>
    $("#notifForm").on("submit", function (e) {
        e.preventDefault();
        var btn = $(this).find('button[type=submit]').prop('disabled', true);
        $.ajax({
            url: @json(url('api/v1/send_notification_selected.php')),
            type: 'POST', headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            data: new FormData(this), processData: false, contentType: false, dataType: 'json',
            success: r => $("#esito").html('<div class="adm-flash adm-flash--' + (r.success ? 'ok' : 'err') + '">' + r.message + '</div>'),
            error: () => $("#esito").html('<div class="adm-flash adm-flash--err">Errore di connessione.</div>'),
            complete: () => btn.prop('disabled', false),
        });
    });
</script>
@endpush
