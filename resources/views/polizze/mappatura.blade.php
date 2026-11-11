{{-- Passo 2 dell'import polizze: si abbina ogni colonna del file a un campo
     della polizza. La preselezione viene dagli alias noti, ma decide l'operatore. --}}
@extends($layout)

@section('title', 'Abbina le colonne')

@push('head')
    @include('polizze._stile')
@endpush

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Abbina le colonne</h1>
            <p>Per ogni colonna del file scegli a quale dato della polizza corrisponde.</p>
        </div>
    </div>

    <div class="pol-steps">
        <div class="pol-step is-done"><span class="pol-step__num"><i class="fas fa-check"></i></span> Scegli il file</div>
        <span class="pol-step__bar"></span>
        <div class="pol-step is-active"><span class="pol-step__num">2</span> Abbina le colonne</div>
        <span class="pol-step__bar"></span>
        <div class="pol-step"><span class="pol-step__num">3</span> Importa</div>
    </div>

    @if (session('errore'))
        <div class="adm-flash adm-flash--err">{{ session('errore') }}</div>
    @endif

    <div class="pol-filebar">
        <i class="fas fa-file-lines" style="color:var(--adm-primary);font-size:1.15rem;"></i>
        <span class="pol-filebar__name">{{ $attesa['nome'] }}</span>
        <span class="pol-filebar__meta">
            {{ number_format($attesa['righe_totali'], 0, ',', '.') }} righe · {{ $attesa['colonne'] }} colonne
        </span>
        @if (! empty($attesa['agenzia']))
            <span class="pol-filebar__meta">
                verso <strong style="color:var(--adm-ink);">{{ $attesa['agenzia'] }}</strong>
            </span>
        @endif
        <span style="flex:1;"></span>
        @if ($attesa['sostituisci'])
            <span class="pol-tag pol-tag--warn">Sostituisce tutto l'archivio</span>
        @else
            <span class="pol-tag pol-tag--ok">Aggiorna l'archivio esistente</span>
        @endif
    </div>

    <form method="POST" action="{{ url($base.'/importa') }}" id="mapForm">
        @csrf

        <div class="pol-card">
            <h2>Colonne del file</h2>
            <p class="pol-card__intro">
                Quello che abbiamo riconosciuto e' gia' selezionato: controllalo, non e' detto che
                sia giusto. Le colonne che non ti servono lasciale su <em>non importare</em>.
                Sotto ogni nome vedi i primi valori veri del tuo file: sono il modo piu' rapido per
                capire cosa contiene davvero quella colonna.
            </p>

            <div id="mapAvvisi"></div>

            <div class="pol-maphead">
                <div>Colonna nel file</div>
                <div>Primi valori</div>
                <div>Corrisponde a</div>
            </div>

            @for ($i = 0; $i < $attesa['colonne']; $i++)
                @php
                    $intestazione = trim($attesa['intestazioni'][$i] ?? '');
                    $scelta = old('colonna.'.$i, $suggerita[$i] ?? '');
                @endphp
                <div class="pol-maprow">
                    <div class="pol-colname">
                        {{ $intestazione !== '' ? $intestazione : \App\Services\PolizzeSheetImportService::nomeColonna($i) }}
                        @if ($intestazione === '')
                            <em>senza intestazione</em>
                        @endif
                    </div>

                    <div class="pol-sample">
                        @forelse ($attesa['anteprima'] as $riga)
                            @php $v = trim((string) ($riga[$i] ?? '')); @endphp
                            <span class="{{ $v === '' ? 'is-empty' : '' }}">{{ $v !== '' ? $v : 'vuoto' }}</span>
                        @empty
                            <span class="is-empty">nessun valore</span>
                        @endforelse
                    </div>

                    <div>
                        <select name="colonna[{{ $i }}]" class="pol-select js-campo" data-colonna="{{ $i }}">
                            <option value="">— non importare —</option>
                            @foreach ($campi as $campo => $etichetta)
                                <option value="{{ $campo }}" @selected($scelta === $campo)>
                                    {{ $etichetta }}@if (in_array($campo, $obbligatori, true)) *@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endfor
        </div>

        <div class="pol-bar">
            <label class="pol-check">
                <input type="checkbox" name="intestazione" value="1" {{ old('intestazione', '1') ? 'checked' : '' }}>
                <span>
                    <strong>La prima riga contiene i nomi delle colonne</strong>
                    <small>Togli la spunta solo se il file inizia subito con i dati: altrimenti la prima polizza non verrebbe importata.</small>
                </span>
            </label>

            <div style="display:flex;gap:10px;align-items:center;">
                <button type="submit" class="pol-btn pol-btn--ghost" form="annullaForm">Annulla</button>
                <button type="submit" class="pol-btn" id="polImporta">
                    <i class="fas fa-database"></i> Importa le polizze
                </button>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ url($base.'/annulla') }}" id="annullaForm">@csrf</form>
@endsection

@push('scripts')
<script>
    // Avvisi dal vivo: campi obbligatori mancanti e campi assegnati due volte.
    // Il server ricontrolla tutto (PolizzeSheetImportService::erroriMappatura):
    // questo serve solo a non far scoprire l'errore dopo l'invio.
    (function () {
        var obbligatori = @json(array_values($obbligatori));
        var etichette = @json($campi);
        var box = document.getElementById('mapAvvisi');
        var bottone = document.getElementById('polImporta');
        var selects = Array.prototype.slice.call(document.querySelectorAll('.js-campo'));

        function controlla() {
            var conta = {};
            selects.forEach(function (s) {
                if (s.value) { conta[s.value] = (conta[s.value] || 0) + 1; }
            });

            var problemi = [];
            obbligatori.forEach(function (c) {
                if (!conta[c]) { problemi.push('Manca la colonna per <strong>' + etichette[c] + '</strong>.'); }
            });
            Object.keys(conta).forEach(function (c) {
                if (conta[c] > 1) {
                    problemi.push('<strong>' + etichette[c] + '</strong> e\' assegnato a ' + conta[c] + ' colonne: scegline una sola.');
                }
            });

            selects.forEach(function (s) {
                var doppio = s.value && conta[s.value] > 1;
                s.classList.toggle('is-set', !!s.value && !doppio);
                s.classList.toggle('is-dup', !!doppio);
                s.closest('.pol-maprow').classList.toggle('is-dup', !!doppio);
            });

            if (problemi.length) {
                box.innerHTML = '<div class="pol-avviso pol-avviso--err">'
                    + problemi.map(function (p) { return '<div>' + p + '</div>'; }).join('') + '</div>';
                bottone.disabled = true;
                bottone.style.opacity = '.5';
                bottone.style.cursor = 'not-allowed';
            } else {
                box.innerHTML = '<div class="pol-avviso pol-avviso--ok">'
                    + 'Le colonne obbligatorie ci sono: puoi importare.</div>';
                bottone.disabled = false;
                bottone.style.opacity = '';
                bottone.style.cursor = '';
            }
        }

        selects.forEach(function (s) { s.addEventListener('change', controlla); });
        controlla();
    })();
</script>
@endpush
