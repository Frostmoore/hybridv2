{{-- Resoconto di un import appena concluso. Serve per i due importer: l'export
     SSF e il foglio mappato a mano, che restituiscono conteggi diversi. Le voci
     si mostrano solo se presenti e diverse da zero: una griglia di zeri non dice
     niente, mentre "1.203 scartate perche' senza codice fiscale" dice tutto. --}}
@php
    $conteggi = $esito['conteggi'] ?? [];
    $etichette = [
        'lette' => ['Righe lette dal file', ''],
        'importate' => ['Polizze importate', 'ok'],
        'scartate_non_attive' => ['Scartate: polizza non attiva', 'warn'],
        'scartate_segnaposto' => ['Scartate: numero usato da piu\' clienti', 'warn'],
        'scartate_senza_cf' => ['Scartate: senza codice fiscale', 'warn'],
        'scartate_senza_numero' => ['Scartate: senza numero di polizza', 'warn'],
        'cf_non_standard' => ['Importate con un codice che non ha la forma del codice fiscale', 'warn'],
        'senza_scadenza_titolo' => ['Importate senza prossima scadenza', 'warn'],
        'date_non_riconosciute' => ['Date che non siamo riusciti a interpretare', 'warn'],
        'targhe_troncate' => ['Targhe accorciate perche\' troppo lunghe', 'warn'],
        'valori_troncati' => ['Valori accorciati perche\' troppo lunghi', 'warn'],
    ];
    $importate = (int) ($conteggi['importate'] ?? 0);
@endphp

<div class="pol-esito {{ $importate > 0 ? 'pol-esito--ok' : 'pol-esito--err' }}">
    @if ($importate > 0)
        <strong>Import completato.</strong>
        {{ number_format($importate, 0, ',', '.') }}
        {{ $importate === 1 ? 'polizza caricata' : 'polizze caricate' }}
        da <em>{{ $esito['nome'] ?? 'file' }}</em>{{ ($esito['tipo'] ?? '') === 'ssf' ? ' (export SSF)' : '' }}@if (! empty($esito['agenzia'])), in <strong>{{ $esito['agenzia'] }}</strong>@endif.
        @if (! empty($conteggi['sostituite']))
            Le polizze caricate in precedenza sono state cancellate prima di questo import.
        @endif
    @else
        <strong>Nessuna polizza importata</strong> da <em>{{ $esito['nome'] ?? 'file' }}</em>.
        Guarda il dettaglio qui sotto: di solito e' la colonna del codice fiscale o quella del
        numero di polizza abbinata a un campo sbagliato.
    @endif
</div>

<div class="pol-card" style="margin-bottom:24px;">
    <h2>Dettaglio dell'import</h2>
    <p class="pol-card__intro" style="margin-bottom:18px;">
        Le voci a zero non sono elencate.
    </p>

    <div class="pol-tiles">
        @foreach ($etichette as $chiave => [$etichetta, $tono])
            @if (! empty($conteggi[$chiave]))
                <div class="pol-tile {{ $tono ? 'pol-tile--'.$tono : '' }}">
                    <b>{{ number_format((int) $conteggi[$chiave], 0, ',', '.') }}</b>
                    <span>{{ $etichetta }}</span>
                </div>
            @endif
        @endforeach
    </div>

    @if (! empty($esito['mappatura']))
        <p class="pol-label" style="margin:26px 0 0;">Colonne usate</p>
        <div class="pol-usate">
            @foreach ($esito['mappatura'] as $indice => $campo)
                <span class="pol-usata">
                    {{ \App\Services\PolizzeSheetImportService::nomeColonna((int) $indice) }}
                    <i class="fas fa-arrow-right"></i>
                    <b>{{ \App\Services\PolizzeSheetImportService::CAMPI[$campo] ?? $campo }}</b>
                </span>
            @endforeach
        </div>
    @endif
</div>
