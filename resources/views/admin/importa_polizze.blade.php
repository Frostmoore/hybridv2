@extends('layouts.admin')

@section('title', 'Importa Polizze')

@section('content')
    <div class="adm-pagehead">
        <div>
            <h1>Importa Polizze</h1>
            <p>Wizard di importazione verso <code>polizze_importate</code> (con mapping colonne).</p>
        </div>
    </div>

    {{-- Stepper --}}
    <div class="adm-steps">
        <div class="adm-step {{ $step === 1 ? 'is-active' : 'is-done' }}">
            <span class="adm-step__num">@if ($step === 1)1 @else<i class="fas fa-check"></i>@endif</span> Carica file
        </div>
        <div class="adm-step__bar"></div>
        <div class="adm-step {{ $step === 2 ? 'is-active' : '' }}">
            <span class="adm-step__num">2</span> Mappa colonne
        </div>
    </div>

    @if ($error !== '')
        <div class="adm-flash adm-flash--err">{{ $error }}</div>
    @endif

    @if ($step === 1)
        {{-- STEP 1: upload --}}
        <div class="adm-block adm-block--narrow">
            <h2 class="adm-panel__title">Carica il file (CSV o XML)</h2>
            <form method="post" action="{{ url('importa-polizze') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="step" value="1">
                <div class="adm-field" style="margin-bottom:16px;">
                    <label>Agenzia</label>
                    <select class="adm-input" name="id_agenzia" required>
                        <option value="">— Seleziona —</option>
                        @foreach ($agenzie as $a)
                            <option value="{{ $a->id }}">{{ $a->id }} — {{ $a->nome_agenzia }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="adm-field" style="margin-bottom:16px;">
                    <label>File polizze</label>
                    <input type="file" class="adm-input" name="polizze_file" accept=".csv,.xml" required>
                </div>
                <div class="adm-fieldgrid">
                    <div class="adm-field">
                        <label>Delimitatore CSV</label>
                        <select class="adm-input" name="delimiter">
                            <option value=";">; (punto e virgola)</option>
                            <option value=",">, (virgola)</option>
                            <option value="	">TAB</option>
                        </select>
                    </div>
                    <div class="adm-field">
                        <label>Encoding</label>
                        <select class="adm-input" name="encoding">
                            <option value="UTF-8">UTF-8</option>
                            <option value="ISO-8859-1">ISO-8859-1 (Latin1)</option>
                            <option value="Windows-1252">Windows-1252</option>
                        </select>
                    </div>
                    <div class="adm-field">
                        <label>Polizze duplicate</label>
                        <select class="adm-input" name="on_duplicate">
                            <option value="skip">Salta</option>
                            <option value="update">Aggiorna</option>
                            <option value="always">Inserisci comunque</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top:18px;">
                    <button type="submit" class="adm-btn adm-btn--primary">Avanti <i class="fas fa-arrow-right"></i></button>
                </div>
            </form>
        </div>
    @else
        {{-- STEP 2: mapping --}}
        <div class="adm-block">
            <h2 class="adm-panel__title">Mappa le colonne del file sui campi polizza</h2>
            <p class="adm-panel__hint">Le colonne sono state pre-abbinate automaticamente dove possibile. Verifica e correggi.</p>
            <div class="adm-tablewrap">
                <table class="adm-table">
                    <thead><tr><th>Campo polizza</th><th>Colonna del file</th><th>Formato data</th></tr></thead>
                    <tbody>
                        @foreach ($targetFields as $field => $label)
                            <tr>
                                <td><strong>{{ $label }}</strong><br><code>{{ $field }}</code></td>
                                <td>
                                    <select class="adm-input mapping-select" data-field="{{ $field }}">
                                        <option value="">— Non importare —</option>
                                        @foreach ($columns as $col)
                                            <option value="{{ $col }}" @selected(($autoGuess[$field] ?? null) === $col)>{{ $col }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    @if (str_starts_with($field, 'data_'))
                                        <select class="adm-input datefmt-select" data-field="{{ $field }}">
                                            <option value="auto">Auto</option>
                                            <option value="d/m/Y">gg/mm/aaaa</option>
                                            <option value="Y-m-d">aaaa-mm-gg</option>
                                            <option value="d-m-Y">gg-mm-aaaa</option>
                                            <option value="Ymd">aaaammgg</option>
                                        </select>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h3 class="adm-panel__title" style="font-size:1rem; margin:22px 0 8px;">Anteprima (prime 5 righe)</h3>
            <div class="adm-tablewrap">
                <table class="adm-table">
                    <thead><tr>@foreach ($columns as $col)<th>{{ $col }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($preview as $row)
                            <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:18px; display:flex; gap:10px;">
                <button type="button" id="btn-importa" class="adm-btn adm-btn--primary"><i class="fas fa-file-import"></i> Importa</button>
                <a href="{{ url('importa-polizze') }}" class="adm-btn adm-btn--ghost">Annulla</a>
            </div>
        </div>

        <div id="risultato" class="adm-block" style="display:none;">
            <h2 class="adm-panel__title">Risultato import</h2>
            <p id="riepilogo"></p>
            <pre id="log" class="adm-log"></pre>
        </div>
    @endif
@endsection

@push('scripts')
@if ($step === 2)
<script>
    $("#btn-importa").click(function () {
        var mapping = {};
        var datefmt = {};
        $(".mapping-select").each(function () {
            if ($(this).val() !== '') mapping[$(this).data('field')] = $(this).val();
        });
        $(".datefmt-select").each(function () {
            datefmt[$(this).data('field')] = $(this).val();
        });

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Importazione in corso…');

        $.ajax({
            url: @json(url('importa-polizze/process')),
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            data: JSON.stringify({ mapping: mapping, datefmt: datefmt }),
            success: function (r) {
                $("#risultato").show();
                if (r.error) {
                    $("#riepilogo").html('<span style="color:#d8455f;">' + r.error + '</span>');
                } else {
                    $("#riepilogo").html('<strong>' + r.inseriti + '</strong> inserite, <strong>' + r.aggiornati + '</strong> aggiornate, <strong>' + r.saltati + '</strong> saltate, <strong>' + r.errori + '</strong> errori.');
                    $("#log").text((r.log || []).join('\n'));
                }
                btn.prop('disabled', false).html('<i class="fas fa-file-import"></i> Importa');
            },
            error: function () {
                $("#risultato").show();
                $("#riepilogo").html('<span style="color:#d8455f;">Errore di comunicazione col server.</span>');
                btn.prop('disabled', false).html('<i class="fas fa-file-import"></i> Importa');
            }
        });
    });
</script>
@endif
@endpush
