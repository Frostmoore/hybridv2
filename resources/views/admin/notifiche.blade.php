@extends('layouts.admin')

@section('title', 'Notifiche')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Notifiche</h1>
            <p>Broadcast a tutti gli utenti del sistema (manutenzione, privacy, comunicazioni globali).</p>
        </div>
    </div>

    <div class="adm-flash adm-flash--err" style="display:flex; gap:12px; align-items:flex-start;">
        <i class="fas fa-triangle-exclamation" style="margin-top:2px;"></i>
        <div>
            <strong>Attenzione: invio a TUTTI gli utenti.</strong>
            Questa notifica raggiunge ogni utente di ogni agenzia (in-app + push OneSignal dove configurato).
            Usala solo per comunicazioni di sistema. Per notifiche di una singola agenzia usa il <strong>pannello agenzie</strong>.
        </div>
    </div>

    @if (session('status'))
        <div class="adm-flash adm-flash--ok">{{ session('status') }}</div>
    @endif

    <div class="adm-block adm-block--narrow">
        <h2 class="adm-panel__title">Invia una notifica a tutti</h2>
        <form method="post" action="{{ url('notifiche') }}" id="notificationForm"
              onsubmit="return confirm('Inviare questa notifica a TUTTI gli utenti del sistema?');">
            @csrf
            <div class="adm-field" style="margin-bottom:16px;">
                <label for="notificationTitle">Titolo della notifica</label>
                <input type="text" class="adm-input" id="notificationTitle" name="notificationTitle" placeholder="Es. Manutenzione programmata" required>
            </div>
            <div class="adm-field" style="margin-bottom:16px;">
                <label for="notificationText">Testo della notifica</label>
                <textarea class="adm-textarea" id="notificationText" name="notificationText" rows="4" required></textarea>
            </div>
            <div class="adm-field" style="margin-bottom:18px;">
                <label for="notificationExpiry">Scadenza banner <span class="text-muted">(opzionale, default 30 giorni)</span></label>
                <input type="date" class="adm-input" id="notificationExpiry" name="notificationExpiry">
            </div>
            <button type="submit" class="adm-btn adm-btn--primary"><i class="fas fa-paper-plane"></i> Invia a tutti</button>
        </form>
    </div>

    {{-- ── Storico broadcast (raggruppati per contenuto) ── --}}
    <div class="adm-block">
        <h2 class="adm-panel__title">Broadcast inviati</h2>
        <p class="adm-panel__hint">Un broadcast genera una notifica generale per ogni agenzia: qui sono raggruppati per contenuto.</p>

        @if ($broadcasts->isEmpty())
            <p class="text-muted">Nessun broadcast inviato.</p>
        @else
            <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid #e3e6ea; color:#888;">
                        <th style="padding:8px;">Titolo</th>
                        <th style="padding:8px;">Testo</th>
                        <th style="padding:8px;">Agenzie</th>
                        <th style="padding:8px;">Stato</th>
                        <th style="padding:8px;">Scadenza</th>
                        <th style="padding:8px; text-align:right;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($broadcasts as $b)
                        @php $attivo = $b->scadenza && \Carbon\Carbon::parse($b->scadenza)->isFuture(); @endphp
                        <tr style="border-bottom:1px solid #f0f2f4;">
                            <td style="padding:8px; font-weight:600;">{{ $b->titolo ?: '—' }}</td>
                            <td style="padding:8px; color:#555;">{{ \Illuminate\Support\Str::limit($b->testo, 70) ?: '—' }}</td>
                            <td style="padding:8px;"><span class="adm-badge adm-badge--off"><i class="fas fa-building"></i> {{ $b->agenzie }}</span></td>
                            <td style="padding:8px;">
                                <span class="adm-badge {{ $attivo ? 'adm-badge--on' : 'adm-badge--off' }}">
                                    <i class="fas {{ $attivo ? 'fa-circle-check' : 'fa-circle-pause' }}"></i> {{ $attivo ? 'Attivo' : 'Scaduto' }}
                                </span>
                            </td>
                            <td style="padding:8px;">
                                <form method="post" action="{{ url('notifiche/scadenza') }}" style="display:flex; gap:4px; align-items:center;">
                                    @csrf
                                    <input type="hidden" name="ref_id" value="{{ $b->id }}">
                                    <input type="datetime-local" name="nuova_scadenza" class="adm-input" style="padding:4px 6px; width:auto;"
                                           value="{{ $b->scadenza ? \Carbon\Carbon::parse($b->scadenza)->format('Y-m-d\TH:i') : '' }}">
                                    <button type="submit" class="adm-btn adm-btn--ghost" title="Aggiorna scadenza (tutte le agenzie)"><i class="fas fa-clock"></i></button>
                                </form>
                            </td>
                            <td style="padding:8px; text-align:right;">
                                <form method="post" action="{{ url('notifiche/elimina') }}" style="display:inline;"
                                      onsubmit="return confirm('Eliminare il broadcast «{{ $b->titolo }}» da tutte le {{ $b->agenzie }} agenzie?');">
                                    @csrf
                                    <input type="hidden" name="ref_id" value="{{ $b->id }}">
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
