@extends('layouts.admin')

@section('title', 'Agenzie')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Agenzie</h1>
            <p>{{ $agenzie->count() }} {{ $agenzie->count() === 1 ? 'agenzia configurata' : 'agenzie configurate' }}</p>
        </div>
        <a href="{{ url('agenzia/nuova') }}" class="adm-btn adm-btn--primary">
            <i class="fas fa-plus"></i> Nuova Agenzia
        </a>
    </div>

    @if (session('status'))
        <div class="adm-flash adm-flash--ok">{{ session('status') }}</div>
    @endif

    <div class="adm-search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="text" id="admSearch" placeholder="Cerca per nome, ID o token…" autocomplete="off">
    </div>

    <div class="adm-grid" id="admGrid">
        @foreach ($agenzie as $agenzia)
            @php
                $attiva = (string) $agenzia->attiva === '1';
                $versione = strtoupper($agenzia->versione_app ?: 'v1');
            @endphp
            <article class="adm-card" data-search="{{ Str::lower($agenzia->nome_agenzia . ' ' . $agenzia->id . ' ' . $agenzia->token . ' ' . $versione) }}">
                <div class="adm-card__top">
                    @if ($agenzia->logo_agenzia && $agenzia->logo_agenzia !== 'placeholder')
                        <img class="adm-card__logo" src="{{ url('res/' . $agenzia->logo_agenzia) }}" alt=""
                             onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
                        <div class="adm-card__logo adm-card__logo--ph d-none"><i class="fas fa-building"></i></div>
                    @else
                        <div class="adm-card__logo adm-card__logo--ph"><i class="fas fa-building"></i></div>
                    @endif
                    <div>
                        <h2 class="adm-card__name">{{ $agenzia->nome_agenzia ?: '(senza nome)' }}</h2>
                        <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                            <span class="adm-badge {{ $attiva ? 'adm-badge--on' : 'adm-badge--off' }}">
                                <i class="fas {{ $attiva ? 'fa-circle-check' : 'fa-circle-pause' }}"></i>
                                {{ $attiva ? 'Attiva' : 'Sospesa' }}
                            </span>
                            <span class="adm-badge {{ $versione === 'V2' ? 'adm-badge--on' : 'adm-badge--off' }}" title="Versione app">
                                <i class="fas fa-mobile-screen"></i> {{ $versione }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="adm-card__meta">
                    <div class="row">
                        <span>ID</span>
                        <strong>{{ $agenzia->id }}</strong>
                    </div>
                    <div class="row">
                        <span>Token</span>
                        <span class="adm-chip" title="{{ $agenzia->token }}">{{ $agenzia->token ?: '—' }}</span>
                    </div>
                </div>

                <div class="adm-card__actions">
                    <a href="{{ url('agenzia/' . $agenzia->id) }}" class="adm-btn adm-btn--ghost">
                        <i class="fas fa-pen"></i> Modifica
                    </a>
                </div>
            </article>
        @endforeach

        <a href="{{ url('agenzia/nuova') }}" class="adm-card adm-card--add" id="admAddCard">
            <i class="fas fa-plus"></i>
            Nuova Agenzia
        </a>
    </div>

    <div class="adm-empty" id="admNoResults" style="display:none;">
        Nessuna agenzia corrisponde alla ricerca.
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const input = document.getElementById('admSearch');
        const cards = Array.from(document.querySelectorAll('#admGrid .adm-card[data-search]'));
        const addCard = document.getElementById('admAddCard');
        const noResults = document.getElementById('admNoResults');

        input.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            let visible = 0;
            cards.forEach(card => {
                const match = card.dataset.search.includes(q);
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            // La card "aggiungi" sparisce durante una ricerca attiva
            addCard.style.display = q === '' ? '' : 'none';
            noResults.style.display = (visible === 0 && q !== '') ? 'block' : 'none';
        });
    })();
</script>
@endpush
