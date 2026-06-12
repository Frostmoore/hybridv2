@extends('layouts.public')

@section('title', 'Reset Password')

@push('head')
<style>
    .titolo { text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 1.5em; font-weight: bold; margin-bottom: 15px; }
    .super-container { display: flex; flex-direction: column; justify-content: center; align-items: center; height: 500px; }
    .container { max-width: 500px; }
    .paragrafo { text-align: center; margin-top: 15px; }
    .bottone { background-color: green; color: white; padding: 10px 30px; width: 100%; text-align: center; margin-top: 20px; border: none; cursor: pointer; }
    .bottone:hover { background-color: #007000; }
    .hide { display: none; }
    #message { border: 1px solid red; background-color: lightcoral; padding: 20px; margin-top: 50px; }
    label { text-align: left !important; font-weight: 500; }
    .img-container { text-align: center; }
</style>
@endpush

@section('content')
    <div class="super-container">
        <div class="container">
            <div id="form-container">
                <h2 class="titolo">Reimpostazione Password</h2>
                <p class="paragrafo">Compila il form di seguito per reimpostare la tua Password</p>
                <form action="" id="form-reimposta" class="form" onsubmit="return false;">
                    <div class="form-group">
                        <label for="inputPassword">Password</label>
                        <input type="password" class="form-control" id="inputPassword" placeholder="Password" name="password">
                    </div>
                    <div class="form-group">
                        <label for="ripetiPassword">Ripeti Password</label>
                        <input type="password" class="form-control" id="ripetiPassword" placeholder="Ripeti Password">
                    </div>
                    <div id="bottone" class="bottone">INVIA</div>
                </form>
            </div>
        </div>

        <div id="message" class="hide"></div>

        <div id="success" class="hide">
            <h2 class="titolo">Complimenti!</h2>
            <div class="img-container"><img src="{{ asset('res/success.gif') }}" width="150" /></div>
            <p class="paragrafo">Hai reimpostato con successo la tua Password. Potrai accedere al tuo profilo direttamente dall'app.</p>
            <div id="bottone_success" class="bottone">HO CAPITO</div>
        </div>

        <div id="error" class="hide">
            <h2 class="titolo">Attenzione!</h2>
            <div class="img-container"><img src="{{ asset('res/error.gif') }}" width="150" /></div>
            <p class="paragrafo">Si è verificato un errore. Riprova più tardi o contatta la tua Agenzia.</p>
            <div id="bottone_error" class="bottone">HO CAPITO</div>
        </div>

        <div id="wait" class="hide">
            <h2 class="titolo">Reimpostazione in corso...</h2>
            <div class="img-container"><img src="{{ asset('res/loading.gif') }}" width="150" /></div>
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
            message.html("<p><strong>Attenzione!</strong> Le password non combaciano</p>").show();
            return;
        }
        if (!regex.test(inputPassword.val())) {
            message.html("<p><strong>Attenzione!</strong> La password deve essere lunga minimo 8 caratteri e contenere almeno:<br>- Una lettera Maiuscola<br>- Una lettera Minuscola<br>- Un Numero<br>- Un Carattere Speciale (@ $ ! % * ? &)</p>").show();
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
