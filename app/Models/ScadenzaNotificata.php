<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Riga di deduplica notifiche scadenza (tabella `scadenze_notificate`):
 * una per coppia (id_polizza, data_effetto_titolo) già notificata.
 */
class ScadenzaNotificata extends Model
{
    protected $table = 'scadenze_notificate';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }
}
