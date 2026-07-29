@extends('layouts.admin')

@section('title', $titolo)

@php
    use App\Http\Controllers\Web\AgencyAdminController;
    use Illuminate\Support\Str;

    // Campi che ospitano valori multipli / lunghi → textarea a tutta riga
    $textareaFields = ['info_indirizzi_sedi', 'info_orari_sedi', 'numeri_utili_salute',
        'numeri_utili_assistenza', 'numeri_utili_noleggio', 'notifica_testo', 'colori'];

    // Icona per sezione (per indice; fallback generico)
    $sectionIcons = ['fa-id-card', 'fa-share-nodes', 'fa-location-dot', 'fa-bullhorn',
        'fa-phone-volume', 'fa-car-burst', 'fa-file-invoice-dollar', 'fa-folder-open',
        'fa-bolt', 'fa-plug'];

    // Feature speciali: la riga esiste solo se già salvata almeno una volta
    $speciale = $agenzia->exists ? $agenzia->speciale : null;
@endphp

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>{{ $titolo }}</h1>
            <p>{{ $agenzia->exists ? 'Modifica la configurazione white-label dell\'agenzia.' : 'Configura una nuova agenzia white-label.' }}</p>
        </div>
        <div style="display:flex; gap:10px;">
            @if ($agenzia->exists)
                <form method="post" action="{{ url('agenzia/'.$agenzia->id.'/elimina') }}"
                      onsubmit="return confirm('Eliminare DEFINITIVAMENTE l\'agenzia «{{ $agenzia->nome_agenzia }}» e TUTTI i suoi dati (clienti, operatori, notifiche, sinistri, preventivi, documenti, polizze)?\n\nL\'azione è irreversibile.');">
                    @csrf
                    <button type="submit" class="adm-btn adm-btn--ghost" style="color:var(--adm-danger); border-color:#f1c4c4;">
                        <i class="fas fa-trash"></i> Elimina
                    </button>
                </form>
            @endif
            <a href="{{ url('home') }}" class="adm-btn adm-btn--ghost"><i class="fas fa-arrow-left"></i> Torna alle agenzie</a>
        </div>
    </div>

    @if (session('status'))
        <div class="adm-flash adm-flash--ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="adm-flash adm-flash--err">{{ $errors->first() }}</div>
    @endif

    @if ($agenzia->exists)
        <div class="adm-infobar">
            <div><span class="k">ID</span><strong>{{ $agenzia->id }}</strong></div>
            <div><span class="k">Token pubblico</span><span class="adm-chip">{{ $agenzia->token ?: '—' }}</span></div>
            <div><span class="k">Token interno</span><span class="adm-chip">{{ $agenzia->token_interno ?: '—' }}</span></div>
        </div>
    @endif

    <form action="{{ url($action) }}" method="post" enctype="multipart/form-data">
        @csrf
        @if ($agenzia->exists)
            <input type="hidden" name="id" value="{{ $agenzia->id }}">
        @endif

        <div class="adm-field" style="max-width:300px; margin-bottom:18px;">
            <label for="versione_app">Versione app <code>versione_app</code></label>
            <select class="adm-input" id="versione_app" name="versione_app">
                @foreach (AgencyAdminController::APP_VERSIONS as $v)
                    <option value="{{ $v }}" {{ old('versione_app', $agenzia->versione_app ?: 'v1') === $v ? 'selected' : '' }}>{{ strtoupper($v) }}</option>
                @endforeach
            </select>
            <small class="text-muted">v1 = vecchio server · v2 = nuovo. Le notifiche push del nuovo server vanno solo alle <strong>v2</strong>.</small>
        </div>

        <div class="adm-form">
            {{-- Navigazione tab --}}
            <nav class="adm-tabs" role="tablist">
                @foreach (AgencyAdminController::FIELD_GROUPS as $sezione => $campi)
                    @php $short = Str::before($sezione, ' ('); @endphp
                    <button type="button" class="adm-tab {{ $loop->first ? 'is-active' : '' }}" data-tab="sec{{ $loop->index }}">
                        <i class="fas {{ $sectionIcons[$loop->index] ?? 'fa-folder' }}"></i> {{ $short }}
                    </button>
                @endforeach
                <button type="button" class="adm-tab" data-tab="secImg">
                    <i class="fas fa-image"></i> Immagini
                </button>
                <button type="button" class="adm-tab" data-tab="secSpec">
                    <i class="fas fa-wand-magic-sparkles"></i> Speciale
                </button>
            </nav>

            {{-- Pannelli --}}
            <div class="adm-tabbody">
                @foreach (AgencyAdminController::FIELD_GROUPS as $sezione => $campi)
                    @php
                        $short = Str::before($sezione, ' (');
                        $hint = Str::contains($sezione, '(') ? rtrim(Str::after($sezione, ' ('), ')') : null;
                    @endphp
                    <section class="adm-panel {{ $loop->first ? 'is-active' : '' }}" id="sec{{ $loop->index }}" role="tabpanel">
                        <h2 class="adm-panel__title">{{ $short }}</h2>
                        @if ($hint)<p class="adm-panel__hint">{{ ucfirst($hint) }}</p>@endif
                        <div class="adm-fieldgrid">
                            @foreach ($campi as $campo)
                                @php $wide = in_array($campo, $textareaFields, true); @endphp
                                <div class="adm-field {{ $wide ? 'adm-field--wide' : '' }}">
                                    <label for="{{ $campo }}">{{ ucfirst(str_replace('_', ' ', $campo)) }} <code>{{ $campo }}</code></label>
                                    @if ($wide)
                                        <textarea class="adm-textarea" id="{{ $campo }}" name="{{ $campo }}" rows="2">{{ old($campo, $agenzia->{$campo}) }}</textarea>
                                    @else
                                        <input type="text" class="adm-input" id="{{ $campo }}" name="{{ $campo }}" value="{{ old($campo, $agenzia->{$campo}) }}">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                {{-- Immagini --}}
                <section class="adm-panel" id="secImg" role="tabpanel">
                    <h2 class="adm-panel__title">Immagini</h2>
                    <p class="adm-panel__hint">Solo file PNG. Le immagini sono servite agli stessi percorsi pubblici (<code>/res/img/{{ $agenzia->id ?: '<id>' }}/…</code>).</p>
                    <div class="adm-fieldgrid">
                        @foreach (AgencyAdminController::IMAGE_FIELDS as $campo)
                            <div class="adm-imgfield">
                                @if ($agenzia->exists && $agenzia->{$campo})
                                    <img class="adm-imgfield__thumb" src="{{ url('res/' . $agenzia->{$campo}) }}" alt=""
                                         onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
                                    <div class="adm-imgfield__thumb d-none"><i class="fas fa-image"></i></div>
                                @else
                                    <div class="adm-imgfield__thumb"><i class="fas fa-image"></i></div>
                                @endif
                                <div class="adm-imgfield__body">
                                    <label for="{{ $campo }}">
                                        {{ ucfirst(str_replace('_', ' ', $campo)) }}
                                        @if ($campo === 'logo_agenzia' && ! $agenzia->exists)<span class="adm-req">* obbligatorio</span>@endif
                                    </label>
                                    <input class="adm-input" type="file" id="{{ $campo }}" name="{{ $campo }}" accept="image/png">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Speciale: feature fuori contratto, una per agenzia --}}
                <section class="adm-panel" id="secSpec" role="tabpanel">
                    <h2 class="adm-panel__title">Speciale</h2>
                    <p class="adm-panel__hint">
                        Implementazioni <strong>fuori standard</strong> concordate con singole agenzie.
                        Attivarle richiede una build dedicata dell'app: non toccare se non sai di cosa si tratta.
                    </p>

                    {{-- Marcatore: distingue "form inviato con checkbox non spuntata"
                         da "richiesta che non contiene affatto questa tab" --}}
                    <input type="hidden" name="speciale_form" value="1">

                    @foreach (AgencyAdminController::SPECIAL_FEATURES as $chiave => $feature)
                        @php $attivo = (bool) old($feature['flag'], $speciale->{$feature['flag']} ?? false); @endphp
                        <div class="adm-spec" data-feature="{{ $chiave }}" style="border:1px solid var(--adm-line, #e6e6e6); border-radius:12px; padding:16px; margin-bottom:14px;">
                            <div class="pub-check" style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" id="{{ $feature['flag'] }}" name="{{ $feature['flag'] }}"
                                       value="1" {{ $attivo ? 'checked' : '' }}>
                                <label for="{{ $feature['flag'] }}" style="margin:0; font-weight:600;">
                                    {{ $feature['label'] }} <code>{{ $feature['flag'] }}</code>
                                </label>
                            </div>
                            <p class="adm-panel__hint" style="margin:8px 0 14px;">{{ $feature['descrizione'] }}</p>
                            <div class="adm-fieldgrid adm-spec__fields" style="{{ $attivo ? '' : 'opacity:.5;' }}">
                                @foreach ($feature['campi'] as $campo => $meta)
                                    <div class="adm-field">
                                        <label for="{{ $campo }}">{{ $meta['label'] }} <code>{{ $campo }}</code></label>
                                        <input type="text" class="adm-input" id="{{ $campo }}" name="{{ $campo }}"
                                               value="{{ old($campo, $speciale->{$campo} ?? '') }}">
                                        <small class="text-muted">{{ $meta['hint'] }}</small>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </section>

                <div class="adm-formbar">
                    <a href="{{ url('home') }}" class="adm-btn adm-btn--ghost">Annulla</a>
                    <button type="submit" class="adm-btn adm-btn--primary">
                        <i class="fas fa-floppy-disk"></i> {{ $agenzia->exists ? 'Salva modifiche' : 'Crea agenzia' }}
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        const tabs = document.querySelectorAll('.adm-tab');
        const panels = document.querySelectorAll('.adm-panel');
        tabs.forEach(tab => tab.addEventListener('click', function () {
            tabs.forEach(t => t.classList.remove('is-active'));
            panels.forEach(p => p.classList.remove('is-active'));
            this.classList.add('is-active');
            document.getElementById(this.dataset.tab)?.classList.add('is-active');
        }));

        // Tab Speciale: i campi di una feature spenta si sbiadiscono, ma NON si
        // disabilitano — gli input disabled non vengono inviati e il loro
        // contenuto andrebbe perso al primo salvataggio con il flag off.
        document.querySelectorAll('.adm-spec').forEach(box => {
            const flag = box.querySelector('input[type=checkbox]');
            const fields = box.querySelector('.adm-spec__fields');
            flag?.addEventListener('change', () => {
                fields.style.opacity = flag.checked ? '' : '.5';
            });
        });
    })();
</script>
@endpush
