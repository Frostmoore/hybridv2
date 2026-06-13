@extends('layouts.agencies')

@section('title', 'Accedi')

@section('content-raw')
    <div class="adm-authwrap">
        <div class="adm-authcard">
            <div style="text-align:center; margin-bottom:16px;">
                <span class="adm-brand__mark" style="display:inline-grid; width:48px; height:48px; font-size:1.3rem;">
                    <i class="fas fa-user-tie"></i>
                </span>
            </div>
            <h1>Accedi</h1>
            <p class="sub">Pannello operatori · GSV Agenzie</p>

            @if ($sessionExpired)
                <div class="adm-flash adm-flash--err">Sessione scaduta, effettua di nuovo l'accesso.</div>
            @endif

            <form id="loginForm">
                <div class="adm-field" style="margin-bottom:14px;">
                    <label for="username">Email / Username</label>
                    <input type="text" class="adm-input" id="username" name="username" required autofocus>
                </div>
                <div class="adm-field" style="margin-bottom:18px;">
                    <label for="password">Password</label>
                    <input type="password" class="adm-input" id="password" name="password" required>
                </div>
                <button type="submit" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center;">
                    <i class="fas fa-arrow-right-to-bracket"></i> Login
                </button>
            </form>
            <div id="loginMessage" style="margin-top:14px; text-align:center;"></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $("#loginForm").on("submit", function (e) {
        e.preventDefault();
        $.ajax({
            url: @json(url('api/v1/log.php')),
            type: "POST",
            headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            data: { username: $("#username").val(), password: $("#password").val() },
            dataType: "json",
            success: function (response) {
                if (response.success) {
                    $("#loginMessage").html('<div class="adm-flash adm-flash--ok">Login riuscito! Reindirizzamento…</div>');
                    setTimeout(function () { window.location.href = @json(url('home')); }, 800);
                } else {
                    $("#loginMessage").html('<div class="adm-flash adm-flash--err">' + response.message + '</div>');
                }
            },
            error: function () {
                $("#loginMessage").html('<div class="adm-flash adm-flash--err">Errore di connessione. Riprova.</div>');
            }
        });
    });
</script>
@endpush
