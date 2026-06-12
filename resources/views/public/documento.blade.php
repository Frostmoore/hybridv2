@extends('layouts.public')

@section('title', 'Carica Documenti — '.$agenzia->nome_agenzia)

@section('content')
    <div class="header_agenzia" style="background-image: url('{{ asset('res/img/'.$agenzia->id.'/header_agenzia.png') }}'); height: 200px; background-position: center; background-size: cover; width: auto;"></div>
    <div class="page_denuncia">
        <h1 style="text-align:center;margin-top:30px;" class="h1_form_denuncia">Carica i tuoi documenti su {{ $agenzia->nome_agenzia }}</h1>
        <div class="form_auto_wrapper" id="form_auto_wrapper">
            <form action="{{ url('res/caricadocumenti.php') }}" method="post" enctype="multipart/form-data" id="form_documenti">
                @csrf
                <div class="row_form_denuncia">
                    <div class="form-group-denuncia">
                        <label for="primo_nome_documenti" class="label_denuncia">Il tuo Nome<span style="color: red;">*</span></label><br />
                        <input type="text" class="form-control" id="primo_nome_documenti" name="primo_nome_documenti" placeholder="Es. Mario">
                    </div>
                    <div class="form-group-denuncia">
                        <label for="cognome_documenti" class="label_denuncia">Il tuo Cognome<span style="color: red;">*</span></label><br />
                        <input type="text" class="form-control" id="cognome_documenti" name="cognome_documenti" placeholder="Es. Rossi">
                    </div>
                </div>
                <div class="form-group">
                    <label for="email_documenti" class="label_denuncia">Il tuo Indirizzo e-mail<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" id="email_documenti" name="email_documenti" placeholder="Es. mario.rossi@email.it">
                    <small class="form-text text-muted">Questo sarà l'indirizzo al quale riceverai la risposta.</small>
                </div>
                <div class="form-group">
                    <label for="descrizione_documenti" class="label_denuncia">Descrivi brevemente il documento che stai caricando<span style="color: red;">*</span></label>
                    <textarea class="form-control" id="descrizione_documenti" name="descrizione_documenti" rows="5"></textarea>
                </div>
                <div class="mb-3">
                    <label for="documenti_documenti" class="label_denuncia">Carica i tuoi documenti<span style="color: red;">*</span></label>
                    <input class="form-control" type="file" id="documenti_documenti" name="documenti_documenti[]" multiple>
                    <small class="form-text text-muted">Puoi scattare delle foto o caricarli in PDF!</small>
                </div>
                <div class="form-group">
                    <h4>Dichiarazione di accettazione della liberatoria privacy</h4>
                    <p class="form_privacy">Dichiaro di aver preso visione della <a href="{{ $agenzia->privacy_agenzia }}">Privacy policy</a> e autorizzo l'agenzia {{ $agenzia->nome_agenzia }} al trattamento dei miei dati personali, che saranno trattati ex Artt. 13-14 del Regolamento (UE) n. 679/2016 (c.d. G.D.P.R.) sulla protezione dei dati personali, per le finalità ivi indicate.</p>
                    <div class="row_form_denuncia_center">
                        <input type="checkbox" id="checkbox_privacy_documenti" name="checkbox_privacy_documenti" class="cb_denuncia">
                        <label for="checkbox_privacy_documenti" class="label_privacy">Do il consenso<span style="color: red;">*</span></label><br>
                    </div>
                </div>
                <input type="hidden" name="agenzia_id_documenti" id="agenzia_id_documenti" value="{{ $agenzia->id }}">
                <div class="errore a_hidden" id="errore">
                    <h2>ATTENZIONE!</h2>
                    <p id="p_errore"></p>
                </div>
                <div class="rowbottone">
                    <button type="button" class="bottone_submit_denuncia_auto" onclick="caricaDocumenti()">CARICA DOCUMENTI</button>
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
