@extends('layouts.agencies')

@section('title', 'Utenti')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Utenti dell'agenzia</h1>
            <p>{{ $clienti->count() }} {{ $clienti->count() === 1 ? 'utente registrato' : 'utenti registrati' }}.</p>
        </div>
        <a href="{{ url('export-utenti') }}" class="adm-btn adm-btn--primary"><i class="fas fa-file-csv"></i> Esporta CSV</a>
    </div>

    <div class="adm-search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="text" id="utSearch" placeholder="Cerca per nome, username, email, CF…" autocomplete="off">
    </div>

    <div class="adm-tablewrap">
        <table class="adm-table" id="utTable">
            <thead>
                <tr>
                    <th>ID</th><th>Username</th><th>Email</th><th>Telefono</th>
                    <th>Nome</th><th>Cognome</th><th>CF</th><th>Ultimo accesso</th><th>Stato</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clienti as $c)
                    <tr data-search="{{ Str::lower($c->username . ' ' . $c->nome . ' ' . $c->cognome . ' ' . $c->email . ' ' . $c->cf) }}">
                        <td>{{ $c->id }}</td>
                        <td>{{ $c->username }}</td>
                        <td>{{ $c->email }}</td>
                        <td>{{ $c->telefono }}</td>
                        <td>{{ $c->nome }}</td>
                        <td>{{ $c->cognome }}</td>
                        <td><span class="adm-chip">{{ $c->cf ?: '—' }}</span></td>
                        <td>{{ $c->lastlogin }}</td>
                        <td>
                            @if ((string) $c->active === '1')
                                <span class="adm-badge adm-badge--on"><i class="fas fa-circle-check"></i> Attivo</span>
                            @else
                                <span class="adm-badge adm-badge--off"><i class="fas fa-circle-pause"></i> Disattivo</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted" style="padding:24px;">Nessun utente per questa agenzia.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const input = document.getElementById('utSearch');
        const rows = Array.from(document.querySelectorAll('#utTable tbody tr[data-search]'));
        input.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            rows.forEach(r => r.style.display = r.dataset.search.includes(q) ? '' : 'none');
        });
    })();
</script>
@endpush
