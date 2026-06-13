@extends('layouts.admin-bare')

@section('title', 'Accedi')
@section('barebar', 'Pannello Agenzie')

@section('content-raw')
    <div class="adm-authwrap">
        <div class="adm-authcard">
            <div style="text-align:center; margin-bottom:16px;">
                <span class="adm-brand__mark" style="display:inline-grid; width:48px; height:48px; font-size:1.3rem;">
                    <i class="fas fa-building-shield"></i>
                </span>
            </div>
            <h1>Accedi</h1>
            <p class="sub">Pannello amministrativo GSV · Agenzie</p>

            @if ($errors->any())
                <div class="adm-flash adm-flash--err">{{ $errors->first() }}</div>
            @endif

            <form action="{{ url('login') }}" method="post">
                @csrf
                <div class="adm-field" style="margin-bottom:14px;">
                    <label for="nomeutente">Nome utente</label>
                    <input type="text" class="adm-input" id="nomeutente" name="nomeutente"
                           placeholder="Nome utente o email" required autofocus>
                </div>
                <div class="adm-field" style="margin-bottom:18px;">
                    <label for="password">Password</label>
                    <input type="password" class="adm-input" id="password" name="password"
                           placeholder="Password" required>
                </div>
                <button type="submit" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center;">
                    <i class="fas fa-arrow-right-to-bracket"></i> Accedi
                </button>
            </form>
        </div>
    </div>
@endsection
