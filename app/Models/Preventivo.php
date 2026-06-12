<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Richiesta di preventivo (tabella `preventivi`).
 */
class Preventivo extends Model
{
    protected $table = 'preventivi';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return BelongsTo<AgenziaNew, $this> */
    public function agenzia(): BelongsTo
    {
        return $this->belongsTo(AgenziaNew::class, 'id_agenzia');
    }
}
