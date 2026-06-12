@extends('layouts.agencies')

@section('title', 'Notifica Privata')

@section('content')
    <div class="container my-4" style="max-width: 700px;">
        <h2>Invia una notifica privata</h2>
        <form id="notifForm" enctype="multipart/form-data">
            <input type="hidden" name="agenziaid" value="{{ $agid }}">
            <div class="mb-3">
                <label class="form-label">Destinatario <span class="text-danger">*</span></label>
                <select class="form-select" name="playerid" required>
                    <option value="">— Seleziona utente —</option>
                    @foreach ($clienti as $c)
                        <option value="{{ $c->playerid }}">{{ $c->cognome }} {{ $c->nome }} ({{ $c->username }})</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Titolo <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="titolo" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Testo <span class="text-danger">*</span></label>
                <textarea class="form-control" name="testo" rows="4" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Immagine (JPG/PNG/WEBP, max 10MB)</label>
                <input type="file" class="form-control" name="media" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="mb-3">
                <label class="form-label">Link (opzionale)</label>
                <input type="url" class="form-control" name="link" placeholder="https://...">
            </div>
            <button type="submit" class="btn btn-info">Invia notifica</button>
        </form>
        <div id="esito" class="mt-3"></div>
    </div>
@endsection

@push('scripts')
<script>
    $("#notifForm").on("submit", function (e) {
        e.preventDefault();
        var btn = $(this).find('button[type=submit]').prop('disabled', true);
        $.ajax({
            url: @json(url('api/v1/send_notification_private.php')),
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            data: new FormData(this),
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
