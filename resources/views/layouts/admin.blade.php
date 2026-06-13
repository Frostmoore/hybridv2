{{-- Layout admin MODERNO (fase 10.5). Bootstrap 5 (per griglia/utility usate
     dalle pagine) + admin.css per il tema. --}}
@php
    $adminName = auth('admin')->user()?->nomeutente ?? '';
    $nav = [
        ['label' => 'Agenzie', 'url' => url('home'), 'active' => request()->is('home')],
        ['label' => 'Utenti', 'url' => url('utenti'), 'active' => request()->is('utenti')],
        ['label' => 'Importa Polizze', 'url' => url('importa-polizze'), 'active' => request()->is('importa-polizze')],
        ['label' => 'Notifiche', 'url' => url('notifiche'), 'active' => request()->is('notifiche')],
    ];
@endphp
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Pannello Admin') · GSV</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link href="{{ asset('res/admin.css') }}" rel="stylesheet" type="text/css">
    @stack('head')
</head>

<body class="adm">
    <nav class="adm-nav" id="admNav">
        <div class="adm-nav__inner">
            <a href="{{ url('home') }}" class="adm-brand">
                <span class="adm-brand__mark">G</span>
                <span>GSV · Agenzie</span>
            </a>
            <div class="adm-nav__links">
                @foreach ($nav as $item)
                    <a href="{{ $item['url'] }}" class="{{ $item['active'] ? 'is-active' : '' }}">{{ $item['label'] }}</a>
                @endforeach
            </div>
            <span class="adm-nav__spacer"></span>
            <div class="adm-user">
                <span class="adm-user__avatar">{{ mb_substr($adminName, 0, 1) }}</span>
                <span>{{ $adminName }}</span>
            </div>
            <a href="{{ url('logout') }}" class="adm-logout"><i class="fas fa-arrow-right-from-bracket"></i> Esci</a>
            <button class="adm-burger" type="button" aria-label="Menu" onclick="document.getElementById('admNav').classList.toggle('is-open')">☰</button>
        </div>
    </nav>

    <main class="adm-main">
        @yield('content')
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    @stack('scripts')
</body>

</html>
