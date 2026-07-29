<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Configurazione white-label di un'agenzia (tabella `agenzie_new`).
 */
class AgenziaNew extends Model
{
    protected $table = 'agenzie_new';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return HasMany<Cliente, $this> */
    public function clienti(): HasMany
    {
        return $this->hasMany(Cliente::class, 'agenziaid');
    }

    /** @return HasMany<NotificaGenerale, $this> */
    public function notificheGenerali(): HasMany
    {
        return $this->hasMany(NotificaGenerale::class, 'notifica_agid');
    }

    /**
     * Feature fuori contratto attive per questa agenzia (tab "Speciale").
     * Può essere null: la riga si crea solo al primo salvataggio.
     *
     * @return HasOne<AgenziaSpeciale, $this>
     */
    public function speciale(): HasOne
    {
        return $this->hasOne(AgenziaSpeciale::class, 'id_agenzia');
    }
}
