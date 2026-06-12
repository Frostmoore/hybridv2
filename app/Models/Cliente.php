<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Utente delle app (tabella `clienti`).
 * Estende Authenticatable per un eventuale guard dedicato; l'auth delle API
 * resta comunque via JWT (vedi JwtService), non via sessione.
 */
class Cliente extends Authenticatable
{
    protected $table = 'clienti';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['password'];

    /** @return BelongsTo<AgenziaNew, $this> */
    public function agenzia(): BelongsTo
    {
        return $this->belongsTo(AgenziaNew::class, 'agenziaid');
    }
}
