@extends('layouts.agencies')

@section('title', 'Registrazione Operatore')

@section('content-raw')
    <div class="adm-authwrap">
        <div class="adm-authcard">
            <div style="text-align:center; margin-bottom:16px;">
                <span class="adm-brand__mark" style="display:inline-grid; width:48px; height:48px; font-size:1.3rem;">
                    <i class="fas fa-user-plus"></i>
                </span>
            </div>
            <h1>Registrati</h1>
            <p class="sub">L'account dovrà essere attivato da un amministratore prima dell'accesso.</p>

            <form id="regForm">
                <div class="adm-field" style="margin-bottom:14px;">
                    <label for="username">Username</label>
                    <input type="text" class="adm-input" id="username" name="username" required autofocus>
                </div>
                <div class="adm-field" style="margin-bottom:14px;">
                    <label for="email">Email</label>
                    <input type="email" class="adm-input" id="email" name="email" required>
                </div>
                <div class="adm-field" style="margin-bottom:18px;">
                    <label for="password">Password</label>
                    <input type="password" class="adm-input" id="password" name="password" required>
                </div>
                <button type="submit" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center;">Registrati</button>
            </form>
            <div id="esito" style="margin-top:14px;"></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $("#regForm").on("submit", function (e) {
        e.preventDefault();
        $.ajax({
            url: @json(url('api/v1/reg.php')),
            type: "POST",
            headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            data: { username: $("#username").val(), email: $("#email").val(), password: $("#password").val() },
            dataType: "json",
            success: r => $("#esito").html('<div class="adm-flash adm-flash--' + (r.success ? 'ok' : 'err') + '">' + r.message + '</div>'),
            error: () => $("#esito").html('<div class="adm-flash adm-flash--err">Errore di connessione.</div>'),
        });
    });
</script>
@endpush
