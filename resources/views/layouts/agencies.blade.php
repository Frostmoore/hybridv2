{{-- Layout pannello agenzie MODERNO (fase 10.5). admin.css + Bootstrap 5. --}}
@php
    $op = auth('operatore')->user();
    $isSuper = $op && in_array($op->username, config('hybrid.agencies_superadmins'), true);
    $nav = $op ? array_filter([
        ['label' => 'Home', 'url' => url('home'), 'active' => request()->is('home')],
        ['label' => 'Utenti', 'url' => url('utenti'), 'active' => request()->is('utenti')],
        ['label' => 'Notifiche', 'url' => url('notifiche/tutti'), 'active' => request()->is('notifiche/*')],
        $isSuper ? ['label' => 'Operatori', 'url' => url('operatori'), 'active' => request()->is('operatori*')] : null,
    ]) : [];
@endphp
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Pannello di controllo per le agenzie">
    <title>@yield('title', 'Pannello Agenzie') · GSV</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link href="{{ asset('res/admin.css') }}" rel="stylesheet" type="text/css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
    @stack('head')
</head>

<body class="adm">
    <nav class="adm-nav" id="agNav">
        <div class="adm-nav__inner">
            <a href="{{ $op ? url('home') : url('login') }}" class="adm-brand">
                <span class="adm-brand__mark">A</span>
                <span>GSV · Agenzie</span>
            </a>
            @if ($op)
                <div class="adm-nav__links">
                    @foreach ($nav as $item)
                        <a href="{{ $item['url'] }}" class="{{ $item['active'] ? 'is-active' : '' }}">{{ $item['label'] }}</a>
                    @endforeach
                </div>
                <span class="adm-nav__spacer"></span>
                <div class="adm-user">
                    <span class="adm-user__avatar">{{ mb_substr($op->username, 0, 1) }}</span>
                    <span>{{ $op->username }}</span>
                </div>
                <a href="{{ url('api/v1/logout.php') }}" class="adm-logout"><i class="fas fa-arrow-right-from-bracket"></i> Esci</a>
                <button class="adm-burger" type="button" aria-label="Menu" onclick="document.getElementById('agNav').classList.toggle('is-open')">☰</button>
            @else
                <span class="adm-nav__spacer"></span>
            @endif
        </div>
    </nav>

    @hasSection('content-raw')
        @yield('content-raw')
    @else
        <main class="adm-main">
            @yield('content')
        </main>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    @stack('scripts')
</body>

</html>
