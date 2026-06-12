@extends('layouts.public')

@section('title', 'Import Polizze — Login')

@section('content')
    <div class="d-flex justify-content-center align-items-center" style="height:100vh;">
        <div class="card shadow" style="min-width: 360px;">
            <div class="card-body">
                <h4 class="card-title text-center mb-3">Import Polizze</h4>
                @if ($error !== '')
                    <div class="alert alert-danger">{{ $error }}</div>
                @endif
                <form method="post" action="{{ url('import_polizze.php') }}">
                    @csrf
                    <input type="password" class="form-control mb-3" name="pw" placeholder="Password" required autofocus>
                    <button type="submit" class="btn btn-primary w-100">Accedi</button>
                </form>
            </div>
        </div>
    </div>
@endsection
