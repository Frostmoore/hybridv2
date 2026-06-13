@extends('layouts.admin')

@section('title', 'Utenti')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Utenti</h1>
            <p>{{ $clienti->total() }} {{ $clienti->total() === 1 ? 'utente registrato' : 'utenti registrati' }} su tutte le agenzie.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="adm-flash adm-flash--ok">{{ session('status') }}</div>
    @endif

    <form method="get" action="{{ url('utenti') }}">
        <div class="adm-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" name="q" value="{{ $q }}" placeholder="Cerca per username, email, nome, cognome, CF…" autocomplete="off">
            @if ($q !== '')
                <a href="{{ url('utenti') }}" class="adm-chip" style="text-decoration:none;">&times; pulisci</a>
            @endif
        </div>
    </form>

    <div class="adm-tablewrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Player ID</th><th>Username</th><th>Email</th><th>Nome</th>
                    <th>Agenzia</th><th>Ultimo accesso</th><th>Stato</th><th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clienti as $c)
                    <tr>
                        <td class="adm-ellipsis" title="{{ $c->playerid }}">{{ $c->playerid ?: '—' }}</td>
                        <td class="adm-ellipsis" title="{{ $c->username }}">{{ $c->username }}</td>
                        <td class="adm-ellipsis" title="{{ $c->email }}">{{ $c->email }}</td>
                        <td class="adm-ellipsis" title="{{ trim($c->nome.' '.$c->cognome) }}">{{ trim($c->nome.' '.$c->cognome) ?: '—' }}</td>
                        <td class="adm-ellipsis" title="{{ $agenzie[$c->agenziaid] ?? ('#'.$c->agenziaid) }}"><span class="adm-chip">{{ $agenzie[$c->agenziaid] ?? ('#'.$c->agenziaid) }}</span></td>
                        <td class="adm-nowrap">{{ $c->lastlogin ?: '—' }}</td>
                        <td class="adm-nowrap">
                            @if ((string) $c->active === '1')
                                <span class="adm-badge adm-badge--on"><i class="fas fa-circle-check"></i> Attivo</span>
                            @else
                                <span class="adm-badge adm-badge--off"><i class="fas fa-circle-pause"></i> Disattivo</span>
                            @endif
                        </td>
                        <td>
                            <div class="adm-rowactions">
                                @if ((string) $c->active !== '1')
                                    <form method="post" action="{{ url('utenti/'.$c->id.'/attiva') }}"
                                          onsubmit="return confirm('Attivare l\'utente {{ $c->username }}?');">
                                        @csrf
                                        <button type="submit" class="adm-btn adm-btn--sm adm-btn--primary"><i class="fas fa-circle-check"></i> Attiva</button>
                                    </form>
                                @endif
                                <form method="post" action="{{ url('utenti/'.$c->id.'/reset-password') }}"
                                      onsubmit="return confirm('Inviare l\'email di reset password a {{ $c->email }}?');">
                                    @csrf
                                    <button type="submit" class="adm-btn adm-btn--sm adm-btn--ghost"><i class="fas fa-key"></i> Reset</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted" style="padding:24px;">Nessun utente trovato{{ $q !== '' ? ' per «'.$q.'»' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($clienti->hasPages())
        <div class="adm-pager" style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:16px;">
            <span class="text-muted">Pagina {{ $clienti->currentPage() }} di {{ $clienti->lastPage() }}</span>
            <div style="display:flex; gap:8px;">
                @if ($clienti->onFirstPage())
                    <span class="adm-btn adm-btn--sm" style="opacity:.45; pointer-events:none;"><i class="fas fa-chevron-left"></i> Prec.</span>
                @else
                    <a href="{{ $clienti->previousPageUrl() }}" class="adm-btn adm-btn--sm adm-btn--ghost"><i class="fas fa-chevron-left"></i> Prec.</a>
                @endif
                @if ($clienti->hasMorePages())
                    <a href="{{ $clienti->nextPageUrl() }}" class="adm-btn adm-btn--sm adm-btn--ghost">Succ. <i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="adm-btn adm-btn--sm" style="opacity:.45; pointer-events:none;">Succ. <i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        </div>
    @endif
@endsection
