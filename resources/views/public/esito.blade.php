@extends('layouts.public')

@section('title', $title)

@section('content')
    <div class="super-container" style="display:flex;justify-content:center;align-items:center;height:100vh;">
        <div class="container" style="max-width:500px;">
            <h2 style="text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:1.5em;font-weight:bold;">{{ $title }}</h2>
            @isset($gif)
                <div style="text-align:center;"><img src="{{ asset('res/'.$gif) }}" width="150" /></div>
            @endisset
            <p style="text-align:center;margin-top:30px;">{!! $text !!}</p>
            <div id="bottone" style="background-color:green;color:white;padding:10px 30px;width:100%;text-align:center;cursor:pointer;">HO CAPITO</div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $("#bottone").click(function () {
        window.location.href = @json($redirect ?? 'https://www.google.it');
    });
</script>
@endpush
