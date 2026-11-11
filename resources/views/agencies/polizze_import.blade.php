{{-- Passo 1 dell'import polizze nel pannello agenzie: scelta del file.
     L'agenzia di destinazione non si chiede: e' quella dell'operatore. --}}
@extends('layouts.agencies')

@section('title', 'Polizze')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Polizze</h1>
            <p>Carica le polizze della tua agenzia: i clienti le vedranno nell'app.</p>
        </div>
    </div>

    <div class="adm-steps">
        <div class="adm-step is-active"><span class="adm-step__num">1</span> Scegli il file</div>
        <span class="adm-step__bar"></span>
        <div class="adm-step"><span class="adm-step__num">2</span> Abbina le colonne</div>
        <span class="adm-step__bar"></span>
        <div class="adm-step"><span class="adm-step__num">3</span> Importa</div>
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
        @include('agencies._polizze_esito', ['esito' => session('esito')])
    @endif

    @php
        $quando = $ultimoAggiornamento
            ? ', ultimo aggiornamento '.\Illuminate\Support\Carbon::parse($ultimoAggiornamento)->format('d/m/Y H:i')
            : '';
    @endphp
    <div class="adm-infobar">
        <strong>{{ number_format($inArchivio, 0, ',', '.') }}</strong>
        {{ $inArchivio === 1 ? 'polizza' : 'polizze' }} in archivio per la tua agenzia{{ $quando }}.
    </div>

    <div class="adm-block adm-block--narrow">
        <h2 class="adm-panel__title">Carica un file</h2>
        <p class="adm-panel__hint">
            Va bene lo <strong>ZIP dell'export del gestionale (SSF)</strong>, che si importa da solo,
            oppure un foglio <strong>CSV, XLSX o XLS</strong>: in quel caso al passo successivo dirai
            tu che colonna corrisponde a che dato.
        </p>

        <form method="POST" action="{{ url('polizze/carica') }}" enctype="multipart/form-data" class="adm-form">
            @csrf

            <div class="adm-field" style="margin-bottom:16px;">
                <label>File <span class="adm-req">*</span></label>
                <input type="file" class="adm-input" name="file" required
                       accept=".zip,.csv,.txt,.xlsx,.xls">
            </div>

            <div class="adm-field" style="margin-bottom:16px;">
                <label>Cosa fare con le polizze gia' caricate</label>
                <div class="adm-radios">
                    <label class="adm-radio">
                        <input type="radio" name="modalita" value="aggiorna" checked>
                        <span><strong>Aggiorna</strong><br>
                            <small>Le polizze con lo stesso numero vengono aggiornate, le altre restano.</small></span>
                    </label>
                    <label class="adm-radio">
                        <input type="radio" name="modalita" value="sostituisci">
                        <span><strong>Sostituisci tutto</strong><br>
                            <small>Prima cancella tutte le polizze della tua agenzia, poi carica queste.</small></span>
                    </label>
                </div>
            </div>

            <button type="submit" class="adm-btn adm-btn--primary">
                <i class="fas fa-upload"></i> Carica e continua
            </button>
        </form>
    </div>

    <div class="adm-block adm-block--narrow">
        <h2 class="adm-panel__title">Cosa serve nel file</h2>
        <p class="adm-panel__hint">
            Due colonne sono indispensabili: il <strong>codice fiscale</strong> del cliente e il
            <strong>numero di polizza</strong>. Il codice fiscale e' l'unico legame fra la polizza e
            l'utente dell'app: senza quello la polizza non la vede nessuno. Il numero di polizza
            serve a non caricare due volte la stessa: ricaricando lo stesso file le polizze si
            aggiornano, non si duplicano.
        </p>
        <p class="adm-panel__hint">
            Tutto il resto e' facoltativo: compagnia, ramo, prodotto, targa, frazionamento, stato e
            le tre date (decorrenza, prossima scadenza, scadenza del contratto). Le date possono
            essere scritte come preferisci — <em>31/12/2026</em>, <em>2026-12-31</em> o celle data di
            Excel: le convertiamo noi.
        </p>
        <p class="adm-panel__hint">
            Se hai un vecchio file <strong>.xls</strong> (Excel 97-2003) e non si apre, salvalo da
            Excel come <strong>.xlsx</strong> o <strong>.csv</strong> e ricaricalo.
        </p>
    </div>
@endsection
