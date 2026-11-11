{{-- Passo 2 dell'import polizze: si abbina ogni colonna del file a un campo
     della polizza. La preselezione viene dagli alias noti, ma decide l'operatore. --}}
@extends('layouts.agencies')

@section('title', 'Abbina le colonne')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Abbina le colonne</h1>
            <p>Per ogni colonna del file scegli a che dato della polizza corrisponde.</p>
        </div>
    </div>

    <div class="adm-steps">
        <div class="adm-step is-done"><span class="adm-step__num"><i class="fas fa-check"></i></span> Scegli il file</div>
        <span class="adm-step__bar"></span>
        <div class="adm-step is-active"><span class="adm-step__num">2</span> Abbina le colonne</div>
        <span class="adm-step__bar"></span>
        <div class="adm-step"><span class="adm-step__num">3</span> Importa</div>
    </div>

    @if (session('errore'))
        <div class="adm-flash adm-flash--err">{{ session('errore') }}</div>
    @endif

    <div class="adm-infobar">
        <i class="fas fa-file-lines"></i> <strong>{{ $attesa['nome'] }}</strong> —
        {{ number_format($attesa['righe_totali'], 0, ',', '.') }} righe,
        {{ $attesa['colonne'] }} colonne.
        @if ($attesa['sostituisci'])
            <span class="adm-badge adm-badge--off">Sostituisce tutto l'archivio</span>
        @else
            <span class="adm-badge adm-badge--on">Aggiorna l'archivio esistente</span>
        @endif
    </div>

    <form method="POST" action="{{ url('polizze/importa') }}" id="mapForm">
        @csrf

        <div class="adm-block">
            <h2 class="adm-panel__title">Colonne del file</h2>
            <p class="adm-panel__hint">
                Quello che abbiamo riconosciuto e' gia' selezionato: controllalo, non e' detto
                che sia giusto. Le colonne che non ti servono lasciale su
                <em>&mdash; non importare &mdash;</em>.
                Sotto ogni colonna vedi i primi valori veri del tuo file, che sono il modo piu'
                rapido per capire cosa contiene davvero.
            </p>

            <div id="mapAvvisi"></div>

            <div class="adm-tablewrap">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th style="width:26%;">Colonna nel file</th>
                            <th style="width:34%;">Primi valori</th>
                            <th style="width:40%;">Corrisponde a</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 0; $i < $attesa['colonne']; $i++)
                            @php
                                $intestazione = trim($attesa['intestazioni'][$i] ?? '');
                                $scelta = old('colonna.'.$i, $suggerita[$i] ?? '');
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $intestazione !== '' ? $intestazione : \App\Services\PolizzeSheetImportService::nomeColonna($i) }}</strong>
                                    @if ($intestazione === '')
                                        <br><small class="adm-ellipsis">senza intestazione</small>
                                    @endif
                                </td>
                                <td>
                                    @forelse ($attesa['anteprima'] as $riga)
                                        @php $v = trim((string) ($riga[$i] ?? '')); @endphp
                                        <div class="adm-ellipsis"><small>{{ $v !== '' ? $v : '—' }}</small></div>
                                    @empty
                                        <small>—</small>
                                    @endforelse
                                </td>
                                <td>
                                    <select name="colonna[{{ $i }}]" class="adm-input js-campo" data-colonna="{{ $i }}">
                                        <option value="">&mdash; non importare &mdash;</option>
                                        @foreach ($campi as $campo => $etichetta)
                                            <option value="{{ $campo }}" @selected($scelta === $campo)>
                                                {{ $etichetta }}@if (in_array($campo, $obbligatori, true)) (obbligatorio)@endif
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>

        <div class="adm-block adm-block--narrow">
            <div class="adm-field" style="margin-bottom:14px;">
                <label>
                    <input type="checkbox" name="intestazione" value="1" {{ old('intestazione', '1') ? 'checked' : '' }}>
                    La prima riga del file contiene i nomi delle colonne
                </label>
                <small class="adm-panel__hint">
                    Togli la spunta solo se il file inizia subito con i dati: altrimenti la prima
                    polizza non verrebbe importata.
                </small>
            </div>

            <div class="adm-formbar">
                <button type="submit" class="adm-btn adm-btn--primary">
                    <i class="fas fa-database"></i> Importa le polizze
                </button>
                <button type="submit" class="adm-btn adm-btn--ghost"
                        form="annullaForm">Annulla</button>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ url('polizze/annulla') }}" id="annullaForm">@csrf</form>
@endsection

@push('scripts')
<script>
    // Avvisi dal vivo: campi obbligatori mancanti e campi assegnati due volte.
    // Il server ricontrolla tutto (erroriMappatura), questo serve solo a non far
    // scoprire l'errore dopo l'invio.
    (function () {
        var obbligatori = @json(array_values($obbligatori));
        var etichette = @json($campi);
        var box = document.getElementById('mapAvvisi');
        var selects = Array.prototype.slice.call(document.querySelectorAll('.js-campo'));

        function controlla() {
            var scelti = selects.map(function (s) { return s.value; }).filter(Boolean);
            var conta = {};
            scelti.forEach(function (c) { conta[c] = (conta[c] || 0) + 1; });

            var problemi = [];
            obbligatori.forEach(function (c) {
                if (!conta[c]) { problemi.push('Manca la colonna per "' + etichette[c] + '".'); }
            });
            Object.keys(conta).forEach(function (c) {
                if (conta[c] > 1) { problemi.push('"' + etichette[c] + '" e\' assegnato a ' + conta[c] + ' colonne.'); }
            });

            selects.forEach(function (s) {
                s.style.borderColor = (s.value && conta[s.value] > 1) ? '#d8455f' : '';
            });

            box.innerHTML = problemi.length
                ? '<div class="adm-flash adm-flash--err">' + problemi.map(function (p) {
                      return '<div>' + p + '</div>';
                  }).join('') + '</div>'
                : '<div class="adm-flash adm-flash--ok">Le colonne obbligatorie ci sono: puoi importare.</div>';
        }

        selects.forEach(function (s) { s.addEventListener('change', controlla); });
        controlla();
    })();
</script>
@endpush
