@extends('layouts.public')

@section('title', 'Denuncia Sinistro — '.$agenzia->nome_agenzia)

@section('content')
    <div class="header_agenzia" style="background-image: url('{{ asset('res/img/'.$agenzia->id.'/header_agenzia.png') }}'); height: 200px; background-position: center; background-size: cover; width: auto;"></div>
    <div class="page_denuncia">
        <h1 style="text-align:center;" class="h1_form_denuncia">Denuncia il tuo Sinistro su {{ $agenzia->nome_agenzia }}</h1>
        <div class="row_selettore" id="row_selettore">
            <button type="button" class="tasto_selezione_sinistro" id="button_auto" onclick="dropdown_form('auto')">Sinistro Auto</button>
            <button type="button" class="tasto_selezione_sinistro" id="button_nonauto" onclick="dropdown_form('nonauto')">Altro Sinistro</button>
        </div>

        {{-- ── Sinistro AUTO ─────────────────────────────────────────── --}}
        <div class="form_auto_wrapper a_hidden" id="form_auto_wrapper">
            <form action="{{ url('res/denunciasinistro.php') }}" method="post" enctype="multipart/form-data" id="form_auto">
                @csrf
                <div class="row_form_denuncia">
                    <div class="form-group-denuncia">
                        <label for="primo_nome_denuncia_auto" class="label_denuncia">Il tuo Nome<span style="color: red;">*</span></label><br />
                        <input type="text" class="form-control" id="primo_nome_denuncia_auto" name="primo_nome_denuncia_auto" placeholder="Es. Mario">
                    </div>
                    <div class="form-group-denuncia">
                        <label for="cognome_denuncia_auto" class="label_denuncia">Il tuo Cognome<span style="color: red;">*</span></label><br />
                        <input type="text" class="form-control" id="cognome_denuncia_auto" name="cognome_denuncia_auto" placeholder="Es. Rossi">
                    </div>
                </div>
                <div class="form-group">
                    <label for="email_denuncia_auto" class="label_denuncia">Il tuo Indirizzo e-mail<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" id="email_denuncia_auto" name="email_denuncia_auto" placeholder="Es. mario.rossi@email.it">
                    <small class="form-text text-muted">Questo sarà l'indirizzo al quale riceverai la risposta.</small>
                </div>
                <div class="form-group">
                    <label for="descrizione_denuncia_auto" class="label_denuncia">Descrivi brevemente il tuo sinistro<span style="color: red;">*</span></label>
                    <textarea class="form-control" id="descrizione_denuncia_auto" name="descrizione_denuncia_auto" rows="5"></textarea>
                </div>
                <div class="mb-3">
                    <label for="cai_denuncia_auto" class="label_denuncia">Carica il tuo CAI compilato<span style="color: red;">*</span></label>
                    <input class="form-control" type="file" id="cai_denuncia_auto" name="cai_denuncia_auto[]" multiple>
                    <small class="form-text text-muted">Puoi scattargli una foto o caricarlo in PDF!</small>
                </div>
                <div class="mb-3">
                    <label for="documenti_denuncia_auto" class="label_denuncia">Carica fronte e retro della tua patente<span style="color: red;">*</span></label>
                    <input class="form-control" type="file" id="documenti_denuncia_auto" name="documenti_denuncia_auto[]" multiple>
                    <small class="form-text text-muted">Puoi scattare due foto o caricarla in PDF!</small>
                </div>
                <div class="mb-3">
                    <label for="immagini_denuncia_auto" class="label_denuncia">Carica immagini relative al tuo sinistro</label>
                    <input class="form-control" type="file" id="immagini_denuncia_auto" name="immagini_denuncia_auto[]" multiple>
                    <small class="form-text text-muted">Puoi scattare delle foto o caricarle direttamente.</small>
                </div>
                <div class="form-group">
                    <h4>Dichiarazione di accettazione della liberatoria privacy</h4>
                    <p class="form_privacy">Dichiaro di aver preso visione della <a href="{{ $agenzia->privacy_agenzia }}">Privacy policy</a> e autorizzo l'agenzia {{ $agenzia->nome_agenzia }} al trattamento dei miei dati personali, che saranno trattati ex Artt. 13-14 del Regolamento (UE) n. 679/2016 (c.d. G.D.P.R.) sulla protezione dei dati personali, per le finalità ivi indicate.</p>
                    <div class="row_form_denuncia_center">
                        <input type="checkbox" id="checkbox_privacy_auto" name="checkbox_privacy_auto" class="cb_denuncia">
                        <label for="checkbox_privacy_auto" class="label_privacy">Do il consenso<span style="color: red;">*</span></label><br>
                    </div>
                </div>
                <input type="hidden" name="agenzia_id_auto" id="agenzia_id_auto" value="{{ $agenzia->id }}">
                <div class="errore a_hidden" id="errore">
                    <h2>ATTENZIONE!</h2>
                    <p id="p_errore"></p>
                </div>
                <div class="rowbottone">
                    <button type="button" class="bottone_submit_denuncia_auto" onclick="denunciaSinistro('auto')">INOLTRA LA DENUNCIA</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Sinistro NON AUTO ─────────────────────────────────────────── --}}
    <div class="page_denuncia">
        <div class="form_nonauto_wrapper a_hidden" id="form_nonauto_wrapper">
            <form action="{{ url('res/denunciasinistro.php') }}" method="post" enctype="multipart/form-data" id="form_nonauto">
                @csrf
                <div class="row_form_denuncia">
                    <div class="form-group-denuncia">
                        <label for="primo_nome_denuncia_nonauto" class="label_denuncia">Il tuo Nome<span style="color: red;">*</span></label><br />
                        <input type="text" class="form-control" id="primo_nome_denuncia_nonauto" name="primo_nome_denuncia_nonauto" placeholder="Es. Mario">
                    </div>
                    <div class="form-group-denuncia">
                        <label for="cognome_denuncia_nonauto" class="label_denuncia">Il tuo Cognome<span style="color: red;">*</span></label><br />
                        <input type="text" class="form-control" id="cognome_denuncia_nonauto" name="cognome_denuncia_nonauto" placeholder="Es. Rossi">
                    </div>
                </div>
                <div class="form-group">
                    <label for="email_denuncia_nonauto" class="label_denuncia">Il tuo Indirizzo e-mail<span style="color: red;">*</span></label>
                    <input type="text" class="form-control" id="email_denuncia_nonauto" name="email_denuncia_nonauto" placeholder="Es. mario.rossi@email.it">
                    <small class="form-text text-muted">Questo sarà l'indirizzo al quale riceverai la risposta.</small>
                </div>
                <div class="form-group">
                    <label for="descrizione_denuncia_nonauto" class="label_denuncia">Descrivi brevemente il tuo sinistro<span style="color: red;">*</span></label>
                    <textarea class="form-control" id="descrizione_denuncia_nonauto" name="descrizione_denuncia_nonauto" rows="5"></textarea>
                </div>
                <div class="mb-3">
                    <label for="documenti_denuncia_nonauto" class="label_denuncia">Carica fronte e retro del documento di identità<span style="color: red;">*</span></label>
                    <input class="form-control" type="file" id="documenti_denuncia_nonauto" name="documenti_denuncia_nonauto[]" multiple>
                    <small class="form-text text-muted">Puoi scattare due foto o caricarlo in PDF!</small>
                </div>
                <div class="mb-3">
                    <label for="immagini_denuncia_nonauto" class="label_denuncia">Carica immagini relative al tuo sinistro</label>
                    <input class="form-control" type="file" id="immagini_denuncia_nonauto" name="immagini_denuncia_nonauto[]" multiple>
                    <small class="form-text text-muted">Puoi scattare delle foto o caricarle direttamente.</small>
                </div>
                <div class="form-group">
                    <h4>Dichiarazione di accettazione della liberatoria privacy</h4>
                    <p class="form_privacy">Dichiaro di aver preso visione della <a href="{{ $agenzia->privacy_agenzia }}">Privacy policy</a> e autorizzo l'agenzia {{ $agenzia->nome_agenzia }} al trattamento dei miei dati personali, che saranno trattati ex Artt. 13-14 del Regolamento (UE) n. 679/2016 (c.d. G.D.P.R.) sulla protezione dei dati personali, per le finalità ivi indicate.</p>
                    <div class="row_form_denuncia_center">
                        <input type="checkbox" id="checkbox_privacy_nonauto" name="checkbox_privacy_nonauto" class="cb_denuncia">
                        <label for="checkbox_privacy_nonauto" class="label_privacy">Do il consenso<span style="color: red;">*</span></label><br>
                    </div>
                    <input type="hidden" name="agenzia_id_nonauto" id="agenzia_id_nonauto" value="{{ $agenzia->id }}">
                    <div class="errore a_hidden" id="nerrore">
                        <h2>ATTENZIONE!</h2>
                        <p id="np_errore"></p>
                    </div>
                    <div class="rowbottone">
                        <button type="button" class="bottone_submit_denuncia_nonauto" onclick="denunciaSinistro('nonauto')">INOLTRA LA DENUNCIA</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    var form_auto_wrapper = $("#form_auto_wrapper");
    var form_nonauto_wrapper = $("#form_nonauto_wrapper");
    var form_auto = $("#form_auto");
    var form_nonauto = $("#form_nonauto");
    var row_selettore = $("#row_selettore");
    var errore = $("#errore");
    var p_errore = $("#p_errore");
    var nerrore = $("#nerrore");
    var np_errore = $("#np_errore");
    var validRegex = new RegExp("^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$");

    function dropdown_form(value) {
        if (value === 'auto') {
            form_auto_wrapper.fadeIn();
            form_nonauto_wrapper.fadeOut();
        } else {
            form_auto_wrapper.fadeOut();
            form_nonauto_wrapper.fadeIn();
        }
        row_selettore.fadeOut();
    }

    function denunciaSinistro(tipo) {
        if (tipo === 'auto') {
            if ($("#primo_nome_denuncia_auto").val().length < 1) { mostra(errore, p_errore, "Il campo Nome è obbligatorio"); }
            else if ($("#cognome_denuncia_auto").val().length < 1) { mostra(errore, p_errore, "Il campo Cognome è obbligatorio"); }
            else if ($("#email_denuncia_auto").val().length < 1) { mostra(errore, p_errore, "Il campo e-mail è obbligatorio"); }
            else if ($("#descrizione_denuncia_auto").val().length < 1) { mostra(errore, p_errore, "Il campo Descrizione è obbligatorio"); }
            else if ($("#cai_denuncia_auto")[0].files.length < 1) { mostra(errore, p_errore, "Il campo CAI è obbligatorio"); }
            else if ($("#documenti_denuncia_auto")[0].files.length < 1) { mostra(errore, p_errore, "Il campo Documenti è obbligatorio"); }
            else if (!$("#checkbox_privacy_auto")[0].checked) { mostra(errore, p_errore, "Per proseguire, devi accettare la liberatoria privacy"); }
            else if (!$("#email_denuncia_auto").val().match(validRegex)) { mostra(errore, p_errore, "Inserisci un indirizzo e-mail valido"); }
            else { form_auto.submit(); }
        } else {
            if ($("#primo_nome_denuncia_nonauto").val().length < 1) { mostra(nerrore, np_errore, "Il campo Nome è obbligatorio"); }
            else if ($("#cognome_denuncia_nonauto").val().length < 1) { mostra(nerrore, np_errore, "Il campo Cognome è obbligatorio"); }
            else if ($("#email_denuncia_nonauto").val().length < 1) { mostra(nerrore, np_errore, "Il campo e-mail è obbligatorio"); }
            else if ($("#descrizione_denuncia_nonauto").val().length < 1) { mostra(nerrore, np_errore, "Il campo Descrizione è obbligatorio"); }
            else if ($("#documenti_denuncia_nonauto")[0].files.length < 1) { mostra(nerrore, np_errore, "Il campo Documenti è obbligatorio"); }
            else if (!$("#checkbox_privacy_nonauto")[0].checked) { mostra(nerrore, np_errore, "Per proseguire, devi accettare la liberatoria privacy"); }
            else { form_nonauto.submit(); }
        }
    }

    function mostra(box, target, msg) {
        box.removeClass("a_hidden").fadeIn();
        target.html(msg);
    }
</script>
@endpush
