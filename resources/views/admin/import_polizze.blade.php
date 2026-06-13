@extends('layouts.admin-bare')

@section('title', 'Import Polizze')
@section('barebar', 'Import Polizze')
@section('barebar-right')
    <a href="{{ url('import-polizze?logout=1') }}" class="adm-logout"><i class="fas fa-arrow-right-from-bracket"></i> Esci</a>
@endsection

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Import Polizze</h1>
            <p>Carica un file CSV o XLSX per popolare la tabella <code>polizze</code> di un'agenzia.</p>
        </div>
    </div>

    @if (is_array($result) && isset($result['error']))
        <div class="adm-flash adm-flash--err">{{ $result['error'] }}</div>
    @elseif (is_array($result) && ($result['success'] ?? false))
        <div class="adm-flash adm-flash--ok">
            Import completato in modalità <strong>{{ $result['mode'] }}</strong>:
            {{ $result['inserted'] }} righe importate, {{ $result['skipped'] }} saltate
            su {{ $result['total_rows'] }} totali.<br>
            Colonne riconosciute: <code>{{ implode(', ', $result['mapped_cols']) }}</code>
        </div>
    @endif

    <div class="adm-block adm-block--narrow">
        <h2 class="adm-panel__title">Carica file polizze</h2>
        <p class="adm-panel__hint">La prima riga deve contenere le intestazioni. Colonne obbligatorie: <strong>CF</strong> e <strong>N.POLIZZA</strong>.</p>
        <form method="post" action="{{ url('import-polizze') }}" enctype="multipart/form-data">
            @csrf
            <div class="adm-field" style="margin-bottom:16px;">
                <label>Agenzia</label>
                <select class="adm-input" name="agency_id" required>
                    <option value="">— Seleziona —</option>
                    @foreach ($agencies as $a)
                        <option value="{{ $a->id }}">{{ $a->id }} — {{ $a->nome_agenzia }}</option>
                    @endforeach
                </select>
            </div>
            <div class="adm-field" style="margin-bottom:16px;">
                <label>File (CSV o XLSX)</label>
                <input type="file" class="adm-input" name="import_file" accept=".csv,.xlsx" required>
            </div>
            <div class="adm-field" style="margin-bottom:18px;">
                <label>Modalità</label>
                <div class="adm-radios">
                    <label class="adm-radio"><input type="radio" name="mode" value="upsert" checked> Upsert <span class="text-muted">— aggiorna + inserisce</span></label>
                    <label class="adm-radio"><input type="radio" name="mode" value="replace"> Sostituisci <span class="text-muted">— cancella e reimporta</span></label>
                </div>
            </div>
            <button type="submit" class="adm-btn adm-btn--primary"><i class="fas fa-file-import"></i> Importa</button>
        </form>
    </div>

    <div class="adm-block">
        <h2 class="adm-panel__title">Token interni agenzie</h2>
        <p class="adm-panel__hint">Token privato usato dall'API polizze v1 di ogni agenzia.</p>
        <div class="adm-tablewrap">
            <table class="adm-table">
                <thead><tr><th>ID</th><th>Agenzia</th><th>token_interno</th></tr></thead>
                <tbody>
                    @foreach ($agencies as $a)
                        <tr>
                            <td>{{ $a->id }}</td>
                            <td>{{ $a->nome_agenzia }}</td>
                            <td>
                                @if ($a->token_interno)
                                    <span class="adm-chip" style="max-width:none">{{ $a->token_interno }}</span>
                                @else
                                    <span class="text-muted">— non configurato —</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
