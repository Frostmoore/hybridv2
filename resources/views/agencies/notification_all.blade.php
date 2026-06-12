@extends('layouts.agencies')

@section('title', 'Notifica a Tutti')

@section('content')
    <div class="container my-4" style="max-width: 700px;">
        <h2>Invia una notifica a tutti gli utenti</h2>
        <p class="text-muted">La notifica verrà pubblicata anche tra le notifiche generali dell'app (fino alla scadenza) e inviata come push a tutti gli iscritti.</p>
        <form id="notifForm" enctype="multipart/form-data">
            <input type="hidden" name="agenziaid" value="{{ $agid }}">
            <div class="mb-3">
                <label class="form-label">Titolo <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="titolo" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Testo <span class="text-danger">*</span></label>
                <textarea class="form-control" name="testo" rows="4" required></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Data di scadenza <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="data_scadenza" id="data_scadenza" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Ora di scadenza <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" name="ora_scadenza" id="ora_scadenza" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Immagine (JPG/PNG/WEBP, max 20MB)</label>
                <input type="file" class="form-control" name="notifica_immagine" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="mb-3">
                <label class="form-label">Oppure URL immagine</label>
                <input type="url" class="form-control" name="media" placeholder="https://...">
            </div>
            <div class="mb-3">
                <label class="form-label">Link (opzionale)</label>
                <input type="url" class="form-control" name="link" placeholder="https://...">
            </div>
            <button type="submit" class="btn btn-success">Invia a tutti</button>
        </form>
        <div id="esito" class="mt-3"></div>
    </div>
@endsection

@push('scripts')
<script>
    $("#notifForm").on("submit", function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        // Combina data+ora nel formato atteso dal server (Y-m-d H:i:s)
        fd.append('notifica_scadenza', $("#data_scadenza").val() + ' ' + $("#ora_scadenza").val() + ':00');

        var btn = $(this).find('button[type=submit]').prop('disabled', true);
        $.ajax({
            url: @json(url('api/v1/send_notification_all.php')),
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: r => $("#esito").html('<div class="alert alert-' + (r.success ? 'success' : 'danger') + '">' + r.message + '</div>'),
            error: () => $("#esito").html('<div class="alert alert-danger">Errore di connessione.</div>'),
            complete: () => btn.prop('disabled', false),
        });
    });
</script>
@endpush
