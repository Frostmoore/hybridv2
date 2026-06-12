<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Pannello di controllo per le agenzie">
    <meta name="author" content="GSV Digital Solution SRL">
    <title>@yield('title', 'Pannello Agenzie')</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <style>
        .page-content { display: flex; justify-content: center; padding: 40px 15px; }
    </style>
    @stack('head')
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
        <a class="navbar-brand" href="{{ url('home.php') }}">Pannello Agenzie</a>
        @auth('operatore')
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="{{ url('utenti.php') }}">Utenti</a>
                <a class="nav-link" href="{{ url('new_notification_all.php') }}">Notifica a tutti</a>
                <a class="nav-link" href="{{ url('new_notification_private.php') }}">Notifica privata</a>
                <a class="nav-link" href="{{ url('new_notification_selected.php') }}">Notifica selezionati</a>
                @if (in_array(auth('operatore')->user()->username, config('hybrid.agencies_superadmins'), true))
                    <a class="nav-link" href="{{ url('operators.php') }}">Operatori</a>
                @endif
            </div>
            <span class="navbar-text me-3">{{ auth('operatore')->user()->username }}</span>
            <a class="btn btn-outline-light btn-sm" href="{{ url('api/v1/logout.php') }}">Logout</a>
        @endauth
    </nav>
    @yield('content')
    @stack('scripts')
</body>

</html>
