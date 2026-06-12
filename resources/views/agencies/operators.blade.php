@extends('layouts.agencies')

@section('title', 'Operatori — Pannello Agenzie')

@section('content')
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Gestione Operatori</h2>
            <a href="{{ url('addoperator.php') }}" class="btn btn-primary"><i class="fas fa-user-plus"></i> Aggiungi</a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
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
                                <select class="form-select form-select-sm agid-select" data-id="{{ $op->id }}" style="max-width: 260px;">
                                    <option value="">— Nessuna —</option>
                                    @foreach ($agenzie as $a)
                                        <option value="{{ $a->id }}" @selected((int) $op->agid === (int) $a->id)>{{ $a->id }} — {{ $a->nome_agenzia }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input active-toggle" type="checkbox" data-id="{{ $op->id }}" @checked((string) $op->active === '1')>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div id="esito" class="mt-2"></div>
    </div>
@endsection

@push('scripts')
<script>
    function chiama(url, body) {
        return $.ajax({
            url: url,
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            data: JSON.stringify(body),
        });
    }

    $(".active-toggle").change(function () {
        chiama(@json(url('api/v1/activate_operator.php')), {
            id: $(this).data('id'),
            active: this.checked ? 1 : 0,
        }).done(r => $("#esito").html('<div class="alert alert-' + (r.success ? 'success' : 'danger') + ' py-1">' + r.message + '</div>'));
    });

    $(".agid-select").change(function () {
        chiama(@json(url('api/v1/update_agenzia.php')), {
            id: $(this).data('id'),
            agid: $(this).val(),
        }).done(r => $("#esito").html('<div class="alert alert-' + (r.success ? 'success' : 'danger') + ' py-1">' + (r.message || 'Agenzia aggiornata.') + '</div>'));
    });
</script>
@endpush
