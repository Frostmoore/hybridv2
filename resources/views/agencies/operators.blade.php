@extends('layouts.agencies')

@section('title', 'Operatori')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Gestione Operatori</h1>
            <p>Attiva/disattiva gli operatori e assegna l'agenzia di competenza.</p>
        </div>
        <a href="{{ url('operatori/nuovo') }}" class="adm-btn adm-btn--primary"><i class="fas fa-user-plus"></i> Aggiungi</a>
    </div>

    <div id="esito"></div>

    <div class="adm-tablewrap">
        <table class="adm-table">
            <thead>
                <tr><th>ID</th><th>Username</th><th>Email</th><th>Ultimo accesso</th><th>Agenzia</th><th>Attivo</th></tr>
            </thead>
            <tbody>
                @foreach ($operatori as $op)
                    <tr>
                        <td>{{ $op->id }}</td>
                        <td>{{ $op->username }}</td>
                        <td>{{ $op->email }}</td>
                        <td>{{ $op->last_login }}</td>
                        <td>
                            <select class="adm-input agid-select" data-id="{{ $op->id }}" style="max-width:260px;">
                                <option value="">— Nessuna —</option>
                                @foreach ($agenzie as $a)
                                    <option value="{{ $a->id }}" @selected((int) $op->agid === (int) $a->id)>{{ $a->id }} — {{ $a->nome_agenzia }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input active-toggle" type="checkbox" data-id="{{ $op->id }}" @checked((string) $op->active === '1') style="cursor:pointer;">
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
<script>
    function chiama(url, body) {
        return $.ajax({
            url: url, type: 'POST', contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            data: JSON.stringify(body),
        });
    }
    function esito(r, fallback) {
        $("#esito").html('<div class="adm-flash adm-flash--' + (r.success ? 'ok' : 'err') + '">' + (r.message || fallback) + '</div>');
    }
    $(".active-toggle").change(function () {
        chiama(@json(url('api/v1/activate_operator.php')), { id: $(this).data('id'), active: this.checked ? 1 : 0 })
            .done(r => esito(r, 'Operatore aggiornato.'));
    });
    $(".agid-select").change(function () {
        chiama(@json(url('api/v1/update_agenzia.php')), { id: $(this).data('id'), agid: $(this).val() })
            .done(r => esito(r, 'Agenzia aggiornata.'));
    });
</script>
@endpush
