@extends('layouts.agencies')

@section('title', 'Notifica a Tutti')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Notifiche</h1>
            <p>Invia comunicazioni push agli utenti dell'agenzia.</p>
        </div>
    </div>

    @include('agencies._notif_tabs', ['current' => 'tutti'])

    <div class="adm-block adm-block--narrow">
        <h2 class="adm-panel__title">Notifica a tutti gli utenti</h2>
        <p class="adm-panel__hint">Pubblicata anche tra le notifiche generali dell'app (fino alla scadenza) e inviata come push a tutti gli iscritti.</p>
        <form id="notifForm" enctype="multipart/form-data">
            <input type="hidden" name="agenziaid" value="{{ $agid }}">
            <div class="adm-field" style="margin-bottom:14px;">
                <label>Titolo <span class="adm-req">*</span></label>
                <input type="text" class="adm-input" name="titolo" required>
            </div>
            <div class="adm-field" style="margin-bottom:14px;">
                <label>Testo <span class="adm-req">*</span></label>
                <textarea class="adm-textarea" name="testo" rows="4" required></textarea>
            </div>
            <div class="adm-fieldgrid" style="margin-bottom:14px;">
                <div class="adm-field">
                    <label>Data di scadenza <span class="adm-req">*</span></label>
                    <input type="date" class="adm-input" name="data_scadenza" id="data_scadenza" required>
                </div>
                <div class="adm-field">
                    <label>Ora di scadenza <span class="adm-req">*</span></label>
                    <input type="time" class="adm-input" name="ora_scadenza" id="ora_scadenza" required>
                </div>
            </div>
            <div class="adm-field" style="margin-bottom:14px;">
                <label>Immagine (JPG/PNG/WEBP, max 20MB)</label>
                <input type="file" class="adm-input" name="notifica_immagine" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="adm-field" style="margin-bottom:14px;">
                <label>Oppure URL immagine</label>
                <input type="url" class="adm-input" name="media" placeholder="https://…">
            </div>
            <div class="adm-field" style="margin-bottom:18px;">
                <label>Link (opzionale)</label>
                <input type="url" class="adm-input" name="link" placeholder="https://…">
            </div>
            <button type="submit" class="adm-btn adm-btn--primary"><i class="fas fa-bullhorn"></i> Invia a tutti</button>
        </form>
        <div id="esito" style="margin-top:14px;"></div>
    </div>
@endsection

@push('scripts')
<script>
    $("#notifForm").on("submit", function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        fd.append('notifica_scadenza', $("#data_scadenza").val() + ' ' + $("#ora_scadenza").val() + ':00');
        var btn = $(this).find('button[type=submit]').prop('disabled', true);
        $.ajax({
            url: @json(url('api/v1/send_notification_all.php')),
            type: 'POST', headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            data: fd, processData: false, contentType: false, dataType: 'json',
            success: r => $("#esito").html('<div class="adm-flash adm-flash--' + (r.success ? 'ok' : 'err') + '">' + r.message + '</div>'),
            error: () => $("#esito").html('<div class="adm-flash adm-flash--err">Errore di connessione.</div>'),
            complete: () => btn.prop('disabled', false),
        });
    });
</script>
@endpush
