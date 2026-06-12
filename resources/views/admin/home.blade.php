@extends('layouts.admin')

@section('title', 'Home Page')

@section('content')
    <div class="page">
        <h1>Agenzie</h1>
    </div>
    <div class="container-age">
        <div class="row">
            @foreach ($agenzie as $agenzia)
                <div class="col-sm">
                    <div class="agenzia-compressed">
                        <div class="logo-compressed">
                            <img src="{{ $agenzia->logo_agenzia === 'placeholder' || $agenzia->logo_agenzia === '' ? 'https://loremflickr.com/128/128' : asset('res/'.$agenzia->logo_agenzia) }}" width="128" height="128" />
                        </div>
                        <div class="info-compressed">
                            <h2>{{ $agenzia->nome_agenzia }}</h2>
                            <p>ID: <strong>{{ $agenzia->id }}</strong></p>
                            <p>TOKEN: <strong>{{ $agenzia->token }}</strong></p>
                        </div>
                        <div class="info-compressed">
                            <a href="{{ url('agenzia.php?id='.$agenzia->id) }}"><button type="button" class="btn btn-outline-danger">Modifica Dati</button></a>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="col-sm">
                <div class="agenzia-compressed">
                    <div class="logo-compressed">
                        <img src="{{ asset('res/plus.png') }}" />
                    </div>
                    <div class="info-compressed">
                        <h2>Nuovo</h2>
                    </div>
                    <div class="info-compressed">
                        <a href="{{ url('creagenzia.php') }}"><button type="button" class="btn btn-outline-success">Nuova Agenzia</button></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
