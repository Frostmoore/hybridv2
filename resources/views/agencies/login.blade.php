@extends('layouts.agencies')

@section('title', 'Accedi — Pannello Agenzie')

@section('content')
    <div class="page-content">
        <div class="col-md-4">
            <div class="card shadow-lg">
                <div class="card-body">
                    <h3 class="text-center mb-4">Accedi</h3>
                    @if ($sessionExpired)
                        <div class="alert alert-warning">Sessione scaduta, effettua di nuovo l'accesso.</div>
                    @endif
                    <form id="loginForm">
                        <div class="mb-3">
                            <label for="username" class="form-label">Email / Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    <div id="loginMessage" class="mt-3 text-center"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
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
                        $("#loginMessage").html('<div class="alert alert-success">Login riuscito! Reindirizzamento...</div>');
                        setTimeout(function () { window.location.href = "home.php"; }, 1000);
                    } else {
                        $("#loginMessage").html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                },
                error: function () {
                    $("#loginMessage").html('<div class="alert alert-danger">Errore di connessione. Riprova.</div>');
                }
            });
        });
    });
</script>
@endpush
