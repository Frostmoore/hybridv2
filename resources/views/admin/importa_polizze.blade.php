@extends('layouts.admin')

@section('title', 'Importa Polizze')
@section('header', 'Hybrid&Go - Importa Polizze')

@section('content')
    <div class="page">
        <h1>Importa Polizze (wizard)</h1>
    </div>
    <div class="container" style="max-width: 1000px; margin-bottom: 60px;">
        @if ($error !== '')
            <div class="alert alert-danger">{{ $error }}</div>
        @endif

        @if ($step === 1)
            {{-- STEP 1: upload --}}
            <div class="card">
                <div class="card-header"><strong>Step 1 — Carica il file (CSV o XML)</strong></div>
                <div class="card-body">
                    <form method="post" action="{{ url('importa_polizze.php') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="step" value="1">
                        <div class="form-group mb-3">
                            <label>Agenzia</label>
                            <select class="form-control" name="id_agenzia" required>
                                <option value="">— Seleziona —</option>
                                @foreach ($agenzie as $a)
                                    <option value="{{ $a->id }}">{{ $a->id }} — {{ $a->nome_agenzia }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>File polizze</label>
                            <input type="file" class="form-control" name="polizze_file" accept=".csv,.xml" required>
                        </div>
                        <div class="form-row row">
                            <div class="form-group col-md-4">
                                <label>Delimitatore CSV</label>
                                <select class="form-control" name="delimiter">
                                    <option value=";">; (punto e virgola)</option>
                                    <option value=",">, (virgola)</option>
                                    <option value="	">TAB</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Encoding</label>
                                <select class="form-control" name="encoding">
                                    <option value="UTF-8">UTF-8</option>
                                    <option value="ISO-8859-1">ISO-8859-1 (Latin1)</option>
                                    <option value="Windows-1252">Windows-1252</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Polizze duplicate (stesso numero)</label>
                                <select class="form-control" name="on_duplicate">
                                    <option value="skip">Salta</option>
                                    <option value="update">Aggiorna</option>
                                    <option value="always">Inserisci comunque</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Avanti →</button>
                    </form>
                </div>
            </div>
        @else
            {{-- STEP 2: mapping --}}
            <div class="card mb-3">
                <div class="card-header"><strong>Step 2 — Mappa le colonne del file sui campi polizza</strong></div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead><tr><th>Campo polizza</th><th>Colonna del file</th><th>Formato data</th></tr></thead>
                        <tbody>
                            @foreach ($targetFields as $field => $label)
                                <tr>
                                    <td><strong>{{ $label }}</strong> <small class="text-muted">({{ $field }})</small></td>
                                    <td>
                                        <select class="form-control form-control-sm mapping-select" data-field="{{ $field }}">
                                            <option value="">— Non importare —</option>
                                            @foreach ($columns as $col)
                                                <option value="{{ $col }}" @selected(($autoGuess[$field] ?? null) === $col)>{{ $col }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        @if (str_starts_with($field, 'data_'))
                                            <select class="form-control form-control-sm datefmt-select" data-field="{{ $field }}">
                                                <option value="auto">Auto</option>
                                                <option value="d/m/Y">gg/mm/aaaa</option>
                                                <option value="Y-m-d">aaaa-mm-gg</option>
                                                <option value="d-m-Y">gg-mm-aaaa</option>
                                                <option value="Ymd">aaaammgg</option>
                                            </select>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <h6 class="mt-3">Anteprima (prime 5 righe)</h6>
                    <div style="overflow-x:auto;">
                        <table class="table table-sm table-bordered">
                            <thead><tr>@foreach ($columns as $col)<th><small>{{ $col }}</small></th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($preview as $row)
                                    <tr>@foreach ($row as $cell)<td><small>{{ $cell }}</small></td>@endforeach</tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <button type="button" id="btn-importa" class="btn btn-success">IMPORTA</button>
                    <a href="{{ url('importa_polizze.php') }}" class="btn btn-outline-secondary">Annulla</a>
                </div>
            </div>

            <div id="risultato" class="card d-none">
                <div class="card-header"><strong>Risultato import</strong></div>
                <div class="card-body">
                    <p id="riepilogo"></p>
                    <pre id="log" style="max-height:300px;overflow:auto;background:#f8f9fa;padding:10px;"></pre>
                </div>
            </div>
        @endif
    </div>
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
        btn.prop('disabled', true).text('Importazione in corso…');

        $.ajax({
            url: @json(url('res/import_process.php')),
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': @json(csrf_token()) },
            data: JSON.stringify({ mapping: mapping, datefmt: datefmt }),
            success: function (r) {
                $("#risultato").removeClass('d-none');
                if (r.error) {
                    $("#riepilogo").html('<span class="text-danger">' + r.error + '</span>');
                } else {
                    $("#riepilogo").html('<strong>' + r.inseriti + '</strong> inserite, <strong>' + r.aggiornati + '</strong> aggiornate, <strong>' + r.saltati + '</strong> saltate, <strong>' + r.errori + '</strong> errori.');
                    $("#log").text((r.log || []).join('\n'));
                }
                btn.prop('disabled', false).text('IMPORTA');
            },
            error: function () {
                $("#risultato").removeClass('d-none');
                $("#riepilogo").html('<span class="text-danger">Errore di comunicazione col server.</span>');
                btn.prop('disabled', false).text('IMPORTA');
            }
        });
    });
</script>
@endif
@endpush
