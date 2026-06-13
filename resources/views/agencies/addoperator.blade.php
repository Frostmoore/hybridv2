@extends('layouts.agencies')

@section('title', 'Aggiungi Operatore')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Aggiungi Operatore</h1>
            <p>Il nuovo operatore nasce disattivato: andrà attivato dalla gestione operatori.</p>
        </div>
        <a href="{{ url('operatori') }}" class="adm-btn adm-btn--ghost"><i class="fas fa-arrow-left"></i> Operatori</a>
    </div>

    <div class="adm-block adm-block--narrow">
        <form id="addopForm">
            <div class="adm-field" style="margin-bottom:14px;">
                <label for="username">Username</label>
                <input type="text" class="adm-input" id="username" name="username" required>
            </div>
            <div class="adm-field" style="margin-bottom:14px;">
                <label for="email">Email</label>
                <input type="email" class="adm-input" id="email" name="email" required>
            </div>
            <div class="adm-field" style="margin-bottom:18px;">
                <label for="password">Password</label>
                <input type="password" class="adm-input" id="password" name="password" required>
            </div>
            <button type="submit" class="adm-btn adm-btn--primary"><i class="fas fa-user-plus"></i> Crea operatore</button>
        </form>
        <div id="esito" style="margin-top:14px;"></div>
    </div>
@endsection

@push('scripts')
<script>
    $("#addopForm").on("submit", function (e) {
        e.preventDefault();
        $.ajax({
            url: @json(url('api/v1/addop.php')),
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
