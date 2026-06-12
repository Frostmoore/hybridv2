@extends('layouts.agencies')

@section('title', 'Utenti — Pannello Agenzie')

@section('content')
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Utenti dell'agenzia</h2>
            <a href="{{ url('export_utenti.php') }}" class="btn btn-success"><i class="fas fa-file-csv"></i> Esporta CSV</a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th>ID</th><th>Username</th><th>Email</th><th>Telefono</th>
                        <th>Nome</th><th>Cognome</th><th>CF</th><th>Nascita</th>
                        <th>Primo Accesso</th><th>Ultimo Accesso</th><th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clienti as $c)
                        <tr>
                            <td>{{ $c->id }}</td>
                            <td>{{ $c->username }}</td>
                            <td>{{ $c->email }}</td>
                            <td>{{ $c->telefono }}</td>
                            <td>{{ $c->nome }}</td>
                            <td>{{ $c->cognome }}</td>
                            <td>{{ $c->cf }}</td>
                            <td>{{ $c->datadinascita }}</td>
                            <td>{{ $c->firstlogin }}</td>
                            <td>{{ $c->lastlogin }}</td>
                            <td>
                                @if ((string) $c->active === '1')
                                    <span class="badge bg-success">Attivo</span>
                                @else
                                    <span class="badge bg-secondary">Disattivato</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted">Nessun utente per questa agenzia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
