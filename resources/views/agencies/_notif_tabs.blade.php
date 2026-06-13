{{-- Sub-tab condiviso delle 3 pagine notifiche. Variabile attesa: $current --}}
<div class="adm-subtabs">
    <a href="{{ url('notifiche/tutti') }}" class="{{ $current === 'tutti' ? 'is-active' : '' }}">A tutti</a>
    <a href="{{ url('notifiche/privata') }}" class="{{ $current === 'privata' ? 'is-active' : '' }}">Privata</a>
    <a href="{{ url('notifiche/selezionati') }}" class="{{ $current === 'selezionati' ? 'is-active' : '' }}">Selezionati</a>
</div>
