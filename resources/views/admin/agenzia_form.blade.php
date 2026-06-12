@extends('layouts.admin')

@section('title', $titolo)

@php use App\Http\Controllers\Web\AgencyAdminController; @endphp

@section('content')
    <div class="page">
        <h1>{{ $titolo }}</h1>
    </div>
    <div class="container" style="max-width: 1000px; margin-bottom: 60px;">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form action="{{ url($action) }}" method="post" enctype="multipart/form-data">
            @csrf
            @if ($agenzia->exists)
                <input type="hidden" name="id" value="{{ $agenzia->id }}">
                <div class="alert alert-secondary">
                    ID: <strong>{{ $agenzia->id }}</strong> — TOKEN: <strong>{{ $agenzia->token }}</strong>
                    @if ($agenzia->token_interno) — TOKEN INTERNO: <strong>{{ $agenzia->token_interno }}</strong> @endif
                </div>
            @endif

            {{-- Campi testuali per sezione --}}
            @foreach (AgencyAdminController::FIELD_GROUPS as $sezione => $campi)
                <div class="card mb-3">
                    <div class="card-header"><strong>{{ $sezione }}</strong></div>
                    <div class="card-body">
                        <div class="row">
                            @foreach ($campi as $campo)
                                <div class="col-md-6 mb-2">
                                    <label for="{{ $campo }}" class="mb-0"><small><strong>{{ $campo }}</strong></small></label>
                                    @if (in_array($campo, ['info_indirizzi_sedi', 'info_orari_sedi', 'numeri_utili_salute', 'numeri_utili_assistenza', 'numeri_utili_noleggio', 'notifica_testo', 'colori']))
                                        <textarea class="form-control form-control-sm" id="{{ $campo }}" name="{{ $campo }}" rows="2">{{ old($campo, $agenzia->{$campo}) }}</textarea>
                                    @else
                                        <input type="text" class="form-control form-control-sm" id="{{ $campo }}" name="{{ $campo }}" value="{{ old($campo, $agenzia->{$campo}) }}">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Immagini --}}
            <div class="card mb-3">
                <div class="card-header"><strong>Immagini (solo PNG)</strong></div>
                <div class="card-body">
                    <div class="row">
                        @foreach (AgencyAdminController::IMAGE_FIELDS as $campo)
                            <div class="col-md-6 mb-3">
                                <label for="{{ $campo }}" class="mb-0"><small><strong>{{ $campo }}</strong>@if ($campo === 'logo_agenzia' && ! $agenzia->exists)<span style="color:red;">* obbligatorio</span>@endif</small></label>
                                @if ($agenzia->exists && $agenzia->{$campo})
                                    <div><img src="{{ asset('res/'.$agenzia->{$campo}) }}" style="max-height:60px;" alt="{{ $campo }}"
                                              onerror="this.style.display='none'"></div>
                                @endif
                                <input class="form-control form-control-sm" type="file" id="{{ $campo }}" name="{{ $campo }}" accept="image/png">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-danger btn-lg w-100">{{ $agenzia->exists ? 'SALVA MODIFICHE' : 'CREA AGENZIA' }}</button>
        </form>
    </div>
@endsection
