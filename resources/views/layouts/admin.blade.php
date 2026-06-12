<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Pannello Admin')</title>
    <link href="{{ asset('res/style.css') }}" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.7/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    @stack('head')
</head>

<body class="loggedin">
    <nav class="navtop">
        <div>
            <h1>@yield('header', 'GSV - Agenzie')</h1>
            <a href="{{ url('importa_polizze.php') }}"><i class="fas fa-file-import"></i>Importa Polizze</a>
            <a href="#">{{ auth('admin')->user()?->nomeutente }}</a>
            <a href="{{ url('logout.php') }}"><i class="fas fa-sign-out-alt"></i>Logout</a>
        </div>
    </nav>
    @yield('content')
    @stack('scripts')
</body>

</html>
