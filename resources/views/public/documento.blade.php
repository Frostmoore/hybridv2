@extends('layouts.public')

@section('title', 'Carica Documenti — '.$agenzia->nome_agenzia)

@section('content')
    <div class="pub-hero" style="background-image: url('{{ asset('res/img/'.$agenzia->id.'/header_agenzia.png') }}');">
        <h1 class="pub-hero__title">Carica i tuoi documenti su {{ $agenzia->nome_agenzia }}</h1>
    </div>

    <div class="pub-wrap">
        <div class="adm-block">
            <form action="{{ url('res/caricadocumenti.php') }}" method="post" enctype="multipart/form-data" id="form_documenti">
                @csrf
                <div class="adm-fieldgrid">
                    <div class="adm-field">
                        <label for="primo_nome_documenti">Il tuo Nome <span class="adm-req">*</span></label>
                        <input type="text" class="adm-input" id="primo_nome_documenti" name="primo_nome_documenti" placeholder="Es. Mario">
                    </div>
                    <div class="adm-field">
                        <label for="cognome_documenti">Il tuo Cognome <span class="adm-req">*</span></label>
                        <input type="text" class="adm-input" id="cognome_documenti" name="cognome_documenti" placeholder="Es. Rossi">
                    </div>
                </div>
                <div class="adm-field" style="margin-top:14px;">
                    <label for="email_documenti">Il tuo Indirizzo e-mail <span class="adm-req">*</span></label>
                    <input type="text" class="adm-input" id="email_documenti" name="email_documenti" placeholder="Es. mario.rossi@email.it">
                    <small class="text-muted">Questo sarà l'indirizzo al quale riceverai la risposta.</small>
                </div>
                <div class="adm-field" style="margin-top:14px;">
                    <label for="descrizione_documenti">Descrivi brevemente il documento che stai caricando <span class="adm-req">*</span></label>
                    <textarea class="adm-textarea" id="descrizione_documenti" name="descrizione_documenti" rows="5"></textarea>
                </div>
                <div class="adm-field" style="margin-top:14px;">
                    <label for="documenti_documenti">Carica i tuoi documenti <span class="adm-req">*</span></label>
                    <input class="adm-input" type="file" id="documenti_documenti" name="documenti_documenti[]" multiple>
                    <small class="text-muted">Puoi scattare delle foto o caricarli in PDF!</small>
                </div>
                <h4 style="margin:20px 0 6px; font-size:1rem;">Dichiarazione di accettazione della liberatoria privacy</h4>
                <p class="pub-privacy">Dichiaro di aver preso visione della <a href="{{ $agenzia->privacy_agenzia }}">Privacy policy</a> e autorizzo l'agenzia {{ $agenzia->nome_agenzia }} al trattamento dei miei dati personali, che saranno trattati ex Artt. 13-14 del Regolamento (UE) n. 679/2016 (c.d. G.D.P.R.) sulla protezione dei dati personali, per le finalità ivi indicate.</p>
                <div class="pub-check">
                    <input type="checkbox" id="checkbox_privacy_documenti" name="checkbox_privacy_documenti">
                    <label for="checkbox_privacy_documenti">Do il consenso <span class="adm-req">*</span></label>
                </div>
                <input type="hidden" name="agenzia_id_documenti" id="agenzia_id_documenti" value="{{ $agenzia->id }}">
                <div class="errore a_hidden adm-flash adm-flash--err" id="errore" style="margin-top:16px;">
                    <strong>ATTENZIONE!</strong> <span id="p_errore"></span>
                </div>
                <div style="margin-top:18px;">
                    <button type="button" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center;" onclick="caricaDocumenti()">Carica documenti</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    var errore = $("#errore");
    var p_errore = $("#p_errore");
    var validRegex = new RegExp("^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$");

    function caricaDocumenti() {
        if ($("#primo_nome_documenti").val().length < 1) { mostra("Il campo Nome è obbligatorio"); }
        else if ($("#cognome_documenti").val().length < 1) { mostra("Il campo Cognome è obbligatorio"); }
        else if ($("#email_documenti").val().length < 1) { mostra("Il campo e-mail è obbligatorio"); }
        else if ($("#descrizione_documenti").val().length < 1) { mostra("Il campo Descrizione è obbligatorio"); }
        else if ($("#documenti_documenti")[0].files.length < 1) { mostra("Il campo Documenti è obbligatorio"); }
        else if (!$("#checkbox_privacy_documenti")[0].checked) { mostra("Per proseguire, devi accettare la liberatoria privacy"); }
        else if (!$("#email_documenti").val().match(validRegex)) { mostra("Inserisci un indirizzo e-mail valido"); }
        else { $("#form_documenti").submit(); }
    }

    function mostra(msg) {
        errore.removeClass("a_hidden").fadeIn();
        p_errore.html(msg);
    }
</script>
@endpush
