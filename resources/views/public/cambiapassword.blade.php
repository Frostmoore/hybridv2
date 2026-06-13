@extends('layouts.public')

@section('title', 'Reset Password')

@section('content')
    <div class="adm-authwrap">
        <div class="adm-authcard">
            {{-- Form --}}
            <div id="form-container">
                <div class="pub-icon pub-icon--wait"><i class="fas fa-key"></i></div>
                <h1>Reimpostazione Password</h1>
                <p class="sub">Compila il form per reimpostare la tua password.</p>
                <form id="form-reimposta" onsubmit="return false;">
                    <div class="adm-field" style="margin-bottom:14px;">
                        <label for="inputPassword">Password</label>
                        <input type="password" class="adm-input" id="inputPassword" placeholder="Password" name="password">
                    </div>
                    <div class="adm-field" style="margin-bottom:18px;">
                        <label for="ripetiPassword">Ripeti Password</label>
                        <input type="password" class="adm-input" id="ripetiPassword" placeholder="Ripeti Password">
                    </div>
                    <div id="bottone" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center; cursor:pointer;">Invia</div>
                </form>
                <div id="message" class="hide adm-flash adm-flash--err" style="margin-top:16px;"></div>
            </div>

            {{-- Stati esito --}}
            <div id="success" class="hide" style="text-align:center;">
                <div class="pub-icon pub-icon--ok"><i class="fas fa-circle-check"></i></div>
                <h1>Complimenti!</h1>
                <p class="sub">Hai reimpostato con successo la tua password. Potrai accedere al tuo profilo direttamente dall'app.</p>
                <div id="bottone_success" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center; cursor:pointer;">Ho capito</div>
            </div>

            <div id="error" class="hide" style="text-align:center;">
                <div class="pub-icon pub-icon--err"><i class="fas fa-circle-exclamation"></i></div>
                <h1>Attenzione!</h1>
                <p class="sub">Si è verificato un errore. Riprova più tardi o contatta la tua agenzia.</p>
                <div id="bottone_error" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center; cursor:pointer;">Ho capito</div>
            </div>

            <div id="wait" class="hide" style="text-align:center;">
                <div class="pub-icon pub-icon--wait"><i class="fas fa-spinner fa-spin"></i></div>
                <h1>Reimpostazione in corso…</h1>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script type="text/javascript">
    var regex = new RegExp("^(?=.*?[0-9])(?=.*?[A-Z])(?=.*?[#?!@$%^&*\\-_]).{8,}$");

    $("#bottone").click(function () {
        var inputPassword = $("#inputPassword");
        var ripetiPassword = $("#ripetiPassword");
        var message = $("#message");

        if (inputPassword.val() != ripetiPassword.val()) {
            message.html("<strong>Attenzione!</strong> Le password non combaciano").show();
            return;
        }
        if (!regex.test(inputPassword.val())) {
            message.html("<strong>Attenzione!</strong> La password deve essere lunga minimo 8 caratteri e contenere almeno: una lettera maiuscola, una minuscola, un numero e un carattere speciale (@ $ ! % * ? &).").show();
            return;
        }

        // A differenza del legacy, il token del link (b) viene inviato e
        // VERIFICATO dal server prima di cambiare la password.
        var dati = {
            id: @json((string) $idUtente),
            nuova_password: inputPassword.val(),
            id_agenzia: @json((string) $idAgenzia),
            token: @json($token),
        };
        $("#form-container").hide();
        $("#wait").show();
        $.ajax({
            url: @json(url('res/userpasswordhandler.php')),
            type: "POST",
            headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            data: { json: JSON.stringify(dati) },
            complete: function (data) {
                $("#wait").hide();
                if (data.responseText == 'success') {
                    $("#success").show();
                } else {
                    $("#error").show();
                }
            }
        });
    });

    $("#bottone_error, #bottone_success").click(function () {
        window.location.replace("https://www.google.it");
    });
</script>
@endpush
