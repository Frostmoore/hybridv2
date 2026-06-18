@extends('layouts.agencies')

@section('title', 'Notifiche inviate')

@php use Illuminate\Support\Str; @endphp

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Notifiche</h1>
            <p>Elenco delle comunicazioni inviate dall'agenzia.</p>
        </div>
    </div>

    @include('agencies._notif_tabs', ['current' => 'inviate'])

    @if (session('status'))
        <div class="adm-flash adm-flash--ok">{{ session('status') }}</div>
    @endif

    {{-- ── Generali (a tutti) ── --}}
    <div class="adm-block">
        <h2 class="adm-panel__title">Generali (a tutti)</h2>
        <p class="adm-panel__hint">Restano visibili tra le notifiche generali dell'app finché non scadono.</p>

        @if ($generali->isEmpty())
            <p class="text-muted">Nessuna notifica generale inviata.</p>
        @else
            <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid #e3e6ea; color:#888;">
                        <th style="padding:8px;">Titolo</th>
                        <th style="padding:8px;">Testo</th>
                        <th style="padding:8px;">Stato</th>
                        <th style="padding:8px;">Scadenza</th>
                        <th style="padding:8px; text-align:right;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($generali as $g)
                        @php $attiva = $g->notifica_scadenza && \Carbon\Carbon::parse($g->notifica_scadenza)->isFuture(); @endphp
                        <tr style="border-bottom:1px solid #f0f2f4;">
                            <td style="padding:8px; font-weight:600;">{{ $g->notifica_titolo ?: '—' }}</td>
                            <td style="padding:8px; color:#555;">{{ Str::limit($g->notifica_testo, 80) ?: '—' }}</td>
                            <td style="padding:8px;">
                                <span class="adm-badge {{ $attiva ? 'adm-badge--on' : 'adm-badge--off' }}">
                                    <i class="fas {{ $attiva ? 'fa-circle-check' : 'fa-circle-pause' }}"></i> {{ $attiva ? 'Attiva' : 'Scaduta' }}
                                </span>
                            </td>
                            <td style="padding:8px;">
                                <form method="post" action="{{ url('notifiche/generale/'.$g->id.'/scadenza') }}" style="display:flex; gap:4px; align-items:center;">
                                    @csrf
                                    <input type="datetime-local" name="notifica_scadenza" class="adm-input" style="padding:4px 6px; width:auto;"
                                           value="{{ $g->notifica_scadenza ? \Carbon\Carbon::parse($g->notifica_scadenza)->format('Y-m-d\TH:i') : '' }}">
                                    <button type="submit" class="adm-btn adm-btn--ghost" title="Aggiorna scadenza"><i class="fas fa-clock"></i></button>
                                </form>
                            </td>
                            <td style="padding:8px; text-align:right;">
                                <form method="post" action="{{ url('notifiche/generale/'.$g->id.'/elimina') }}" style="display:inline;"
                                      onsubmit="return confirm('Eliminare la notifica generale «{{ $g->notifica_titolo }}»?');">
                                    @csrf
                                    <button type="submit" class="adm-btn adm-btn--ghost" style="color:var(--adm-danger); border-color:#f1c4c4;">
                                        <i class="fas fa-trash"></i> Elimina
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>

    {{-- ── Mirate (privata / selezionati) ── --}}
    <div class="adm-block">
        <h2 class="adm-panel__title">Mirate (privata / selezionati)</h2>
        <p class="adm-panel__hint">Notifiche inviate a destinatari specifici.</p>

        @if ($mirate->isEmpty())
            <p class="text-muted">Nessuna notifica mirata inviata.</p>
        @else
            <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid #e3e6ea; color:#888;">
                        <th style="padding:8px;">Titolo</th>
                        <th style="padding:8px;">Testo</th>
                        <th style="padding:8px;">Destinatari</th>
                        <th style="padding:8px;">Data</th>
                        <th style="padding:8px; text-align:right;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($mirate as $m)
                        <tr style="border-bottom:1px solid #f0f2f4;">
                            <td style="padding:8px; font-weight:600;">{{ $m->titolo ?: '—' }}</td>
                            <td style="padding:8px; color:#555;">{{ Str::limit($m->contenuto, 70) ?: '—' }}</td>
                            <td style="padding:8px; color:#777;">{{ Str::limit($m->destinatari, 50) ?: '—' }}</td>
                            <td style="padding:8px; white-space:nowrap; color:#777;">{{ $m->dataora ?: '—' }}</td>
                            <td style="padding:8px; text-align:right;">
                                <form method="post" action="{{ url('notifiche/mirata/'.$m->id.'/elimina') }}" style="display:inline;"
                                      onsubmit="return confirm('Eliminare questa notifica?');">
                                    @csrf
                                    <button type="submit" class="adm-btn adm-btn--ghost" style="color:var(--adm-danger); border-color:#f1c4c4;">
                                        <i class="fas fa-trash"></i> Elimina
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>
@endsection
