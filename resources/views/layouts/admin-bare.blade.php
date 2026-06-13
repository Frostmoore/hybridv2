{{-- Layout "bare" MODERNO: pagine admin standalone senza sessione admin
     (es. import polizze protetto da password). Tema admin.css, niente nav. --}}
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'GSV') · GSV</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link href="{{ asset('res/admin.css') }}" rel="stylesheet" type="text/css">
    @stack('head')
</head>

<body class="adm">
    <div class="adm-barebar">
        <div class="adm-barebar__inner">
            <a href="{{ url('home') }}" class="adm-brand">
                <span class="adm-brand__mark">G</span>
                <span>GSV · @yield('barebar', 'Strumenti')</span>
            </a>
            @yield('barebar-right')
        </div>
    </div>

    @hasSection('content-raw')
        @yield('content-raw')
    @else
        <main class="adm-main">
            @yield('content')
        </main>
    @endif

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    @stack('scripts')
</body>

</html>
