<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Caricamento documento (tabella `documenti`).
 */
class Documento extends Model
{
    protected $table = 'documenti';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return BelongsTo<AgenziaNew, $this> */
    public function agenzia(): BelongsTo
    {
        return $this->belongsTo(AgenziaNew::class, 'id_agenzia');
    }
}
