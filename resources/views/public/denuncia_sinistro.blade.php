@extends('layouts.public')

@section('title', 'Denuncia Sinistro — '.$agenzia->nome_agenzia)

@section('content')
    <div class="pub-hero" style="background-image: url('{{ asset('res/img/'.$agenzia->id.'/header_agenzia.png') }}');">
        <h1 class="pub-hero__title">Denuncia il tuo Sinistro su {{ $agenzia->nome_agenzia }}</h1>
    </div>

    <div class="pub-wrap">
        <div class="pub-selector" id="row_selettore">
            <button type="button" id="button_auto" onclick="dropdown_form('auto')"><i class="fas fa-car-burst"></i> Sinistro Auto</button>
            <button type="button" id="button_nonauto" onclick="dropdown_form('nonauto')"><i class="fas fa-house-crack"></i> Altro Sinistro</button>
        </div>

        {{-- ── Sinistro AUTO ─────────────────────────────────────────── --}}
        <div class="form_auto_wrapper a_hidden" id="form_auto_wrapper">
            <div class="adm-block">
                <form action="{{ url('res/denunciasinistro.php') }}" method="post" enctype="multipart/form-data" id="form_auto">
                    @csrf
                    <div class="adm-fieldgrid">
                        <div class="adm-field">
                            <label for="primo_nome_denuncia_auto">Il tuo Nome <span class="adm-req">*</span></label>
                            <input type="text" class="adm-input" id="primo_nome_denuncia_auto" name="primo_nome_denuncia_auto" placeholder="Es. Mario">
                        </div>
                        <div class="adm-field">
                            <label for="cognome_denuncia_auto">Il tuo Cognome <span class="adm-req">*</span></label>
                            <input type="text" class="adm-input" id="cognome_denuncia_auto" name="cognome_denuncia_auto" placeholder="Es. Rossi">
                        </div>
                    </div>
                    <div class="adm-field" style="margin-top:14px;">
                        <label for="email_denuncia_auto">Il tuo Indirizzo e-mail <span class="adm-req">*</span></label>
                        <input type="text" class="adm-input" id="email_denuncia_auto" name="email_denuncia_auto" placeholder="Es. mario.rossi@email.it">
                        <small class="text-muted">Questo sarà l'indirizzo al quale riceverai la risposta.</small>
                    </div>
                    <div class="adm-field" style="margin-top:14px;">
                        <label for="descrizione_denuncia_auto">Descrivi brevemente il tuo sinistro <span class="adm-req">*</span></label>
                        <textarea class="adm-textarea" id="descrizione_denuncia_auto" name="descrizione_denuncia_auto" rows="5"></textarea>
                    </div>
                    <div class="adm-field" style="margin-top:14px;">
                        <label for="cai_denuncia_auto">Carica il tuo CAI compilato <span class="adm-req">*</span></label>
                        <input class="adm-input" type="file" id="cai_denuncia_auto" name="cai_denuncia_auto[]" multiple>
                        <small class="text-muted">Puoi scattargli una foto o caricarlo in PDF!</small>
                    </div>
                    <div class="adm-field" style="margin-top:14px;">
                        <label for="documenti_denuncia_auto">Carica fronte e retro della tua patente <span class="adm-req">*</span></label>
                        <input class="adm-input" type="file" id="documenti_denuncia_auto" name="documenti_denuncia_auto[]" multiple>
                        <small class="text-muted">Puoi scattare due foto o caricarla in PDF!</small>
                    </div>
                    <div class="adm-field" style="margin-top:14px;">
                        <label for="immagini_denuncia_auto">Carica immagini relative al tuo sinistro</label>
                        <input class="adm-input" type="file" id="immagini_denuncia_auto" name="immagini_denuncia_auto[]" multiple>
                        <small class="text-muted">Puoi scattare delle foto o caricarle direttamente.</small>
                    </div>
                    <h4 style="margin:20px 0 6px; font-size:1rem;">Dichiarazione di accettazione della liberatoria privacy</h4>
                    <p class="pub-privacy">Dichiaro di aver preso visione della <a href="{{ $agenzia->privacy_agenzia }}">Privacy policy</a> e autorizzo l'agenzia {{ $agenzia->nome_agenzia }} al trattamento dei miei dati personali, che saranno trattati ex Artt. 13-14 del Regolamento (UE) n. 679/2016 (c.d. G.D.P.R.) sulla protezione dei dati personali, per le finalità ivi indicate.</p>
                    <div class="pub-check">
                        <input type="checkbox" id="checkbox_privacy_auto" name="checkbox_privacy_auto">
                        <label for="checkbox_privacy_auto">Do il consenso <span class="adm-req">*</span></label>
                    </div>
                    <input type="hidden" name="agenzia_id_auto" id="agenzia_id_auto" value="{{ $agenzia->id }}">
                    <div class="errore a_hidden adm-flash adm-flash--err" id="errore" style="margin-top:16px;">
                        <strong>ATTENZIONE!</strong> <span id="p_errore"></span>
                    </div>
                    <div style="margin-top:18px;">
                        <button type="button" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center;" onclick="denunciaSinistro('auto')">Inoltra la denuncia</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Sinistro NON AUTO ─────────────────────────────────────────── --}}
        <div class="form_nonauto_wrapper a_hidden" id="form_nonauto_wrapper">
            <div class="adm-block">
                <form action="{{ url('res/denunciasinistro.php') }}" method="post" enctype="multipart/form-data" id="form_nonauto">
                    @csrf
                    <div class="adm-fieldgrid">
                        <div class="adm-field">
                            <label for="primo_nome_denuncia_nonauto">Il tuo Nome <span class="adm-req">*</span></label>
                            <input type="text" class="adm-input" id="primo_nome_denuncia_nonauto" name="primo_nome_denuncia_nonauto" placeholder="Es. Mario">
                        </div>
                        <div class="adm-field">
                            <label for="cognome_denuncia_nonauto">Il tuo Cognome <span class="adm-req">*</span></label>
                            <input type="text" class="adm-input" id="cognome_denuncia_nonauto" name="cognome_denuncia_nonauto" placeholder="Es. Rossi">
                        </div>
                    </div>
                    <div class="adm-field" style="margin-top:14px;">
                        <label for="email_denuncia_nonauto">Il tuo Indirizzo e-mail <span class="adm-req">*</span></label>
                        <input type="text" class="adm-input" id="email_denuncia_nonauto" name="email_denuncia_nonauto" placeholder="Es. mario.rossi@email.it">
                        <small class="text-muted">Questo sarà l'indirizzo al quale riceverai la risposta.</small>
                    </div>
                    <div class="adm-field" style="margin-top:14px;">
                        <label for="descrizione_denuncia_nonauto">Descrivi brevemente il tuo sinistro <span class="adm-req">*</span></label>
                        <textarea class="adm-textarea" id="descrizione_denuncia_nonauto" name="descrizione_denuncia_nonauto" rows="5"></textarea>
                    </div>
                    <div class="adm-field" style="margin-top:14px;">
                        <label for="documenti_denuncia_nonauto">Carica fronte e retro del documento di identità <span class="adm-req">*</span></label>
                        <input class="adm-input" type="file" id="documenti_denuncia_nonauto" name="documenti_denuncia_nonauto[]" multiple>
                        <small class="text-muted">Puoi scattare due foto o caricarlo in PDF!</small>
                    </div>
                    <div class="adm-field" style="margin-top:14px;">
                        <label for="immagini_denuncia_nonauto">Carica immagini relative al tuo sinistro</label>
                        <input class="adm-input" type="file" id="immagini_denuncia_nonauto" name="immagini_denuncia_nonauto[]" multiple>
                        <small class="text-muted">Puoi scattare delle foto o caricarle direttamente.</small>
                    </div>
                    <h4 style="margin:20px 0 6px; font-size:1rem;">Dichiarazione di accettazione della liberatoria privacy</h4>
                    <p class="pub-privacy">Dichiaro di aver preso visione della <a href="{{ $agenzia->privacy_agenzia }}">Privacy policy</a> e autorizzo l'agenzia {{ $agenzia->nome_agenzia }} al trattamento dei miei dati personali, che saranno trattati ex Artt. 13-14 del Regolamento (UE) n. 679/2016 (c.d. G.D.P.R.) sulla protezione dei dati personali, per le finalità ivi indicate.</p>
                    <div class="pub-check">
                        <input type="checkbox" id="checkbox_privacy_nonauto" name="checkbox_privacy_nonauto">
                        <label for="checkbox_privacy_nonauto">Do il consenso <span class="adm-req">*</span></label>
                    </div>
                    <input type="hidden" name="agenzia_id_nonauto" id="agenzia_id_nonauto" value="{{ $agenzia->id }}">
                    <div class="errore a_hidden adm-flash adm-flash--err" id="nerrore" style="margin-top:16px;">
                        <strong>ATTENZIONE!</strong> <span id="np_errore"></span>
                    </div>
                    <div style="margin-top:18px;">
                        <button type="button" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center;" onclick="denunciaSinistro('nonauto')">Inoltra la denuncia</button>
                    </div>
                </form>
            </div>
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
