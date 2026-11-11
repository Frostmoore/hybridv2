{{-- Passo 1 dell'import polizze: scelta del file.

     Una pagina sola per due pannelli. Cambia una cosa, e la decide il
     controller passando `$agenzie`:

     - **null** (pannello agenzie) — l'agenzia e' quella dell'operatore in
       sessione e non si sceglie: e' la ragione per cui quella pagina si puo'
       aprire alle agenzie senza che una scriva nelle polizze di un'altra.
     - **una lista** (pannello admin) — compare la tendina, perche' li' si
       carica per conto di chiunque.

     `$layout` e `$base` fanno il resto: quale layout avvolge la pagina e su
     quale prefisso di URL vanno i form. --}}
@extends($layout)

@section('title', 'Importa polizze')

@push('head')
    @include('polizze._stile')
@endpush

@section('content')
    @php
        $quando = $ultimoAggiornamento
            ? \Illuminate\Support\Carbon::parse($ultimoAggiornamento)->format('d/m/Y H:i')
            : null;
        $scegliAgenzia = $agenzie !== null;
        // ⚠️ Queste due righe si costruiscono qui e non con @if dentro il
        // markup: Blade riconosce una direttiva solo se prima della `@` NON
        // c'e' un carattere di parola, quindi `...agenzie@endif` resta testo,
        // l'`@if` non si chiude mai e la pagina non compila. Gia' successo.
        $dettaglio = $scegliAgenzia ? 'in tutte le agenzie' : null;
        $riga2 = array_filter([$dettaglio, $quando ? 'aggiornato il '.$quando : null]);
    @endphp

    <div class="adm-pagehead">
        <div>
            <h1>{{ $scegliAgenzia ? 'Importa polizze' : 'Polizze' }}</h1>
            <p>
                @if ($scegliAgenzia)
                    Carica le polizze di un'agenzia: i suoi clienti le vedranno nell'app.
                @else
                    Carica le polizze della tua agenzia: i tuoi clienti le vedranno nell'app.
                @endif
            </p>
        </div>
        <div class="pol-stat">
            <b>{{ number_format($inArchivio, 0, ',', '.') }}</b>
            <span>
                {{ $inArchivio === 1 ? 'polizza in archivio' : 'polizze in archivio' }}
                @foreach ($riga2 as $pezzo)
                    <br>{{ $pezzo }}
                @endforeach
            </span>
        </div>
    </div>

    <div class="pol-steps">
        <div class="pol-step is-active"><span class="pol-step__num">1</span> Scegli il file</div>
        <span class="pol-step__bar"></span>
        <div class="pol-step"><span class="pol-step__num">2</span> Abbina le colonne</div>
        <span class="pol-step__bar"></span>
        <div class="pol-step"><span class="pol-step__num">3</span> Importa</div>
    </div>

    @if (session('errore'))
        <div class="adm-flash adm-flash--err">{{ session('errore') }}</div>
    @endif

    @if ($errors->any())
        <div class="adm-flash adm-flash--err">
            @foreach ($errors->all() as $e)
                <div>{{ $e }}</div>
            @endforeach
        </div>
    @endif

    @if (session('esito'))
        @include('polizze._esito', ['esito' => session('esito')])
    @endif

    <div class="pol-layout">
        <div>
            <form method="POST" action="{{ url($base.'/carica') }}" enctype="multipart/form-data" id="polForm">
                @csrf
                <div class="pol-card">
                    <h2>Carica un file</h2>
                    <p class="pol-card__intro">
                        Va bene lo <strong>ZIP dell'export del gestionale (SSF)</strong>, che si importa da
                        solo, oppure un foglio <strong>CSV, XLSX o XLS</strong>: in quel caso al passo
                        successivo dirai tu quale colonna corrisponde a quale dato.
                    </p>

                    @if ($scegliAgenzia)
                        <div style="margin-bottom:26px;">
                            <span class="pol-label">Agenzia di destinazione</span>
                            <select name="agenzia_id" class="pol-select" required>
                                <option value="">— scegli l'agenzia —</option>
                                @foreach ($agenzie as $a)
                                    <option value="{{ $a->id }}" @selected((int) old('agenzia_id') === (int) $a->id)>
                                        {{ $a->nome }} — {{ $a->polizze === 0 ? 'nessuna polizza' : number_format($a->polizze, 0, ',', '.').' polizze' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="pol-hint">
                                Accanto al nome c'e' quante polizze ha gia': e' il modo per accorgersi
                                di aver sbagliato riga <em>prima</em> di importare, non dopo.
                            </p>
                        </div>
                    @endif

                    <label class="pol-drop" id="polDrop">
                        <input type="file" name="file" id="polFile" required
                               accept=".zip,.csv,.txt,.xlsx,.xls">
                        <span class="pol-drop__icon" id="polIcon"><i class="fas fa-cloud-arrow-up"></i></span>
                        <span class="pol-drop__main" id="polMain">Trascina qui il file o <u>scegline uno</u></span>
                        <span class="pol-drop__sub" id="polSub">ZIP dell'export SSF, oppure CSV, XLSX o XLS</span>
                    </label>

                    <div style="margin-top:28px;">
                        <span class="pol-label">Cosa fare con le polizze gia' caricate</span>
                        <div class="pol-modes">
                            <label class="pol-mode is-on">
                                <input type="radio" name="modalita" value="aggiorna" checked>
                                <span>
                                    <strong>Aggiorna</strong>
                                    <small>Le polizze con lo stesso numero vengono aggiornate, le altre restano dove sono.</small>
                                </span>
                            </label>
                            <label class="pol-mode is-danger">
                                <input type="radio" name="modalita" value="sostituisci">
                                <span>
                                    <strong>Sostituisci tutto</strong>
                                    <small>Prima cancella tutte le polizze {{ $scegliAgenzia ? "dell'agenzia scelta" : 'della tua agenzia' }}, poi carica queste.</small>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="pol-actions">
                        <p class="pol-actions__note">
                            Niente viene scritto finche' non confermi: i fogli passano prima dalla
                            pagina di abbinamento delle colonne.
                        </p>
                        <button type="submit" class="pol-btn">
                            <i class="fas fa-arrow-right"></i> Carica e continua
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <aside class="pol-card pol-aside">
            <h3>Cosa serve nel file</h3>

            <div class="pol-need">
                <span class="pol-need__dot"><i class="fas fa-asterisk"></i></span>
                <div>
                    <strong>Codice fiscale</strong>
                    <span>E' l'unico legame fra la polizza e l'utente dell'app: senza, la polizza non la vede nessuno.</span>
                </div>
            </div>
            <div class="pol-need">
                <span class="pol-need__dot"><i class="fas fa-asterisk"></i></span>
                <div>
                    <strong>Numero di polizza</strong>
                    <span>Serve a non caricare due volte la stessa: ricaricando lo stesso file le polizze si aggiornano, non si duplicano.</span>
                </div>
            </div>

            <p class="pol-label" style="margin:22px 0 0;">Facoltativi</p>
            <div class="pol-chips">
                @foreach (['Contraente', 'Compagnia', 'Ramo', 'Prodotto', 'Targa', 'Frazionamento', 'Stato', 'Decorrenza', 'Prossima scadenza', 'Scadenza contratto'] as $campo)
                    <span class="pol-chip">{{ $campo }}</span>
                @endforeach
            </div>

            <div class="pol-tip">
                <strong>Le date</strong> puoi scriverle come preferisci — <em>31/12/2026</em>,
                <em>2026-12-31</em> o celle data di Excel: le convertiamo noi.
            </div>

            <div class="pol-tip">
                <strong>Un vecchio .xls</strong> (Excel 97-2003) non si apre: salvalo da Excel come
                <strong>.xlsx</strong> o <strong>.csv</strong> e ricaricalo.
            </div>
        </aside>
    </div>
@endsection

@push('scripts')
<script>
    // Nome del file scelto bene in vista, e trascinamento dal desktop: il
    // controllo nativo mostra il nome in un carattere minuscolo accanto al
    // bottone, dove non lo si legge.
    (function () {
        var drop = document.getElementById('polDrop');
        var input = document.getElementById('polFile');
        var main = document.getElementById('polMain');
        var sub = document.getElementById('polSub');
        var icon = document.getElementById('polIcon');
        if (!drop || !input) { return; }

        function pesa(byte) {
            if (byte < 1024) { return byte + ' byte'; }
            if (byte < 1048576) { return (byte / 1024).toFixed(0) + ' KB'; }
            return (byte / 1048576).toFixed(1) + ' MB';
        }

        function mostra() {
            var f = input.files && input.files[0];
            if (!f) { return; }
            drop.classList.add('is-filled');
            icon.innerHTML = '<i class="fas fa-file-circle-check"></i>';
            main.textContent = f.name;
            sub.textContent = pesa(f.size) + ' — clicca per cambiare file';
        }

        input.addEventListener('change', mostra);

        ['dragenter', 'dragover'].forEach(function (e) {
            drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.add('is-hover'); });
        });
        ['dragleave', 'drop'].forEach(function (e) {
            drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.remove('is-hover'); });
        });
        drop.addEventListener('drop', function (ev) {
            if (ev.dataTransfer && ev.dataTransfer.files.length) {
                input.files = ev.dataTransfer.files;
                mostra();
            }
        });

        // Evidenziazione della scheda scelta (niente :has, cosi' va anche sui
        // browser vecchi che le agenzie hanno davvero).
        var modi = Array.prototype.slice.call(document.querySelectorAll('.pol-mode'));
        modi.forEach(function (m) {
            m.addEventListener('change', function () {
                modi.forEach(function (altro) {
                    altro.classList.toggle('is-on', altro.querySelector('input').checked);
                });
            });
        });
    })();
</script>
@endpush
