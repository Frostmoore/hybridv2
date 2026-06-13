@extends('layouts.admin-bare')

@section('title', 'Import Polizze')
@section('barebar', 'Import Polizze')

@section('content-raw')
    <div class="adm-authwrap">
        <div class="adm-authcard">
            <div style="text-align:center; margin-bottom:16px;">
                <span class="adm-brand__mark" style="display:inline-grid; width:44px; height:44px; font-size:1.2rem;">
                    <i class="fas fa-file-import"></i>
                </span>
            </div>
            <h1>Import Polizze</h1>
            <p class="sub">Area riservata — inserisci la password di accesso.</p>

            @if ($error !== '')
                <div class="adm-flash adm-flash--err">{{ $error }}</div>
            @endif

            <form method="post" action="{{ url('import-polizze') }}">
                @csrf
                <input type="password" class="adm-input" name="pw" placeholder="Password" required autofocus style="margin-bottom:14px;">
                <button type="submit" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center;">Accedi</button>
            </form>
        </div>
    </div>
@endsection
