@extends('layouts.public')

@section('title', $title)

@section('content')
    <div class="adm-authwrap">
        <div class="adm-authcard" style="text-align:center;">
            @if (! empty($gif))
                <div class="pub-icon pub-icon--ok"><i class="fas fa-circle-check"></i></div>
            @else
                <div class="pub-icon pub-icon--wait"><i class="fas fa-circle-info"></i></div>
            @endif
            <h1>{{ $title }}</h1>
            <p style="color:var(--adm-muted); margin:6px 0 22px;">{!! $text !!}</p>
            <button type="button" id="bottone" class="adm-btn adm-btn--primary" style="width:100%; justify-content:center;">Ho capito</button>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.getElementById('bottone').addEventListener('click', function () {
        window.location.href = @json($redirect ?? 'https://www.google.it');
    });
</script>
@endpush
