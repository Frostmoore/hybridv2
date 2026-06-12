<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Utente del pannello agencies (tabella `operatori`).
 * Guard a sessione `operatore` (fase 7).
 */
class Operatore extends Authenticatable
{
    protected $table = 'operatori';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['password'];

    /** @return BelongsTo<AgenziaNew, $this> */
    public function agenzia(): BelongsTo
    {
        return $this->belongsTo(AgenziaNew::class, 'agid');
    }
}
