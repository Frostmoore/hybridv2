@extends('layouts.agencies')

@section('title', 'Notifica a Selezionati')

@section('content')
    <div class="container my-4" style="max-width: 700px;">
        <h2>Invia una notifica a utenti selezionati</h2>
        <form id="notifForm" enctype="multipart/form-data">
            <input type="hidden" name="agenziaid" value="{{ $agid }}">
            <div class="mb-3">
                <label class="form-label">Destinatari <span class="text-danger">*</span></label>
                <div class="border rounded p-2" style="max-height: 240px; overflow-y: auto;">
                    @forelse ($clienti as $c)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="playerid[]" value="{{ $c->playerid }}" id="cli{{ $c->id }}">
                            <label class="form-check-label" for="cli{{ $c->id }}">{{ $c->cognome }} {{ $c->nome }} ({{ $c->username }})</label>
                        </div>
                    @empty
                        <span class="text-muted">Nessun utente con notifiche push attive.</span>
                    @endforelse
                </div>
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
            <button type="submit" class="btn btn-warning">Invia ai selezionati</button>
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
            url: @json(url('api/v1/send_notification_selected.php')),
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
