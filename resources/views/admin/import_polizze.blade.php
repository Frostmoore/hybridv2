@extends('layouts.public')

@section('title', 'Import Polizze')

@section('content')
    <div class="container my-4" style="max-width: 900px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Import Polizze</h2>
            <a href="{{ url('import_polizze.php?logout=1') }}" class="btn btn-outline-secondary btn-sm">Esci</a>
        </div>

        @if (is_array($result) && isset($result['error']))
            <div class="alert alert-danger">{{ $result['error'] }}</div>
        @elseif (is_array($result) && ($result['success'] ?? false))
            <div class="alert alert-success">
                Import completato in modalità <strong>{{ $result['mode'] }}</strong>:
                {{ $result['inserted'] }} righe importate, {{ $result['skipped'] }} saltate
                su {{ $result['total_rows'] }} totali.<br>
                Colonne riconosciute: <code>{{ implode(', ', $result['mapped_cols']) }}</code>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header"><strong>Carica file polizze (CSV o XLSX)</strong></div>
            <div class="card-body">
                <form method="post" action="{{ url('import_polizze.php') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group mb-3">
                        <label>Agenzia</label>
                        <select class="form-control" name="agency_id" required>
                            <option value="">— Seleziona —</option>
                            @foreach ($agencies as $a)
                                <option value="{{ $a->id }}">{{ $a->id }} — {{ $a->nome_agenzia }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label>File</label>
                        <input type="file" class="form-control" name="import_file" accept=".csv,.xlsx" required>
                        <small class="text-muted">La prima riga deve contenere le intestazioni (CF e N.POLIZZA obbligatorie).</small>
                    </div>
                    <div class="form-group mb-3">
                        <label>Modalità</label>
                        <div>
                            <label class="mr-3"><input type="radio" name="mode" value="upsert" checked> Upsert (aggiorna esistenti + inserisce nuovi)</label>
                            <label><input type="radio" name="mode" value="replace"> Sostituisci (cancella le polizze dell'agenzia e reimporta)</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Importa</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Token interni agenzie</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead><tr><th>ID</th><th>Agenzia</th><th>token_interno</th></tr></thead>
                    <tbody>
                        @foreach ($agencies as $a)
                            <tr>
                                <td>{{ $a->id }}</td>
                                <td>{{ $a->nome_agenzia }}</td>
                                <td><code>{{ $a->token_interno ?: '— non configurato —' }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
