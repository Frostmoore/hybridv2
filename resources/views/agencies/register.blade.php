@extends('layouts.agencies')

@section('title', 'Registrazione Operatore')

@section('content')
    <div class="page-content">
        <div class="col-md-4">
            <div class="card shadow-lg">
                <div class="card-body">
                    <h3 class="text-center mb-4">Registrati</h3>
                    <p class="text-muted text-center"><small>L'account dovrà essere attivato da un amministratore prima di poter accedere.</small></p>
                    <form id="regForm">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Registrati</button>
                    </form>
                    <div id="esito" class="mt-3 text-center"></div>
                </div>
            </div>
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
            success: r => $("#esito").html('<div class="alert alert-' + (r.success ? 'success' : 'danger') + '">' + r.message + '</div>'),
            error: () => $("#esito").html('<div class="alert alert-danger">Errore di connessione.</div>'),
        });
    });
</script>
@endpush
