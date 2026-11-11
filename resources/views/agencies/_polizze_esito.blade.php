{{-- Resoconto di un import appena concluso. Serve per i due importer: l'export
     SSF e il foglio mappato a mano, che restituiscono conteggi diversi. Le voci
     si mostrano solo se presenti e diverse da zero: un elenco di zeri non dice
     niente, mentre "1.203 scartate perche' senza codice fiscale" dice tutto. --}}
@php
    $conteggi = $esito['conteggi'] ?? [];
    $etichette = [
        'lette' => 'Righe lette dal file',
        'importate' => 'Polizze importate',
        'scartate_non_attive' => 'Scartate: polizza non attiva',
        'scartate_segnaposto' => 'Scartate: numero di polizza usato da piu\' clienti (segnaposto)',
        'scartate_senza_cf' => 'Scartate: senza codice fiscale',
        'scartate_senza_numero' => 'Scartate: senza numero di polizza',
        'cf_non_standard' => 'Importate ma con un codice che non ha la forma del codice fiscale',
        'senza_scadenza_titolo' => 'Importate senza prossima scadenza',
        'date_non_riconosciute' => 'Date che non siamo riusciti a interpretare',
        'targhe_troncate' => 'Targhe accorciate perche' . "'" . ' troppo lunghe',
        'valori_troncati' => 'Valori accorciati perche' . "'" . ' troppo lunghi',
    ];
    $importate = (int) ($conteggi['importate'] ?? 0);
@endphp

<div class="adm-flash {{ $importate > 0 ? 'adm-flash--ok' : 'adm-flash--err' }}">
    @if ($importate > 0)
        <strong>Import completato.</strong>
        {{ number_format($importate, 0, ',', '.') }}
        {{ $importate === 1 ? 'polizza caricata' : 'polizze caricate' }}
        da <em>{{ $esito['nome'] ?? 'file' }}</em>{{ ($esito['tipo'] ?? '') === 'ssf' ? ' (export SSF)' : '' }}.
    @else
        <strong>Nessuna polizza importata</strong> da <em>{{ $esito['nome'] ?? 'file' }}</em>.
        Guarda il dettaglio qui sotto: di solito e' la colonna del codice fiscale o quella del
        numero di polizza abbinata a un campo sbagliato.
    @endif
</div>

<div class="adm-block adm-block--narrow">
    <h2 class="adm-panel__title">Dettaglio dell'import</h2>
    <div class="adm-tablewrap">
        <table class="adm-table">
            <tbody>
                @foreach ($etichette as $chiave => $etichetta)
                    @if (! empty($conteggi[$chiave]))
                        <tr>
                            <td>{{ $etichetta }}</td>
                            <td class="adm-nowrap"><strong>{{ number_format((int) $conteggi[$chiave], 0, ',', '.') }}</strong></td>
                        </tr>
                    @endif
                @endforeach
                @if (! empty($conteggi['sostituite']))
                    <tr>
                        <td colspan="2">Le polizze caricate in precedenza sono state cancellate prima di questo import.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    @if (! empty($esito['mappatura']))
        <h2 class="adm-panel__title" style="margin-top:18px;">Colonne usate</h2>
        <div class="adm-tablewrap">
            <table class="adm-table">
                <tbody>
                    @foreach ($esito['mappatura'] as $indice => $campo)
                        <tr>
                            <td>{{ \App\Services\PolizzeSheetImportService::nomeColonna((int) $indice) }}</td>
                            <td>{{ \App\Services\PolizzeSheetImportService::CAMPI[$campo] ?? $campo }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
