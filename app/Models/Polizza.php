<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Polizza importata via import_polizze.php (tabella `polizze`, sistema B).
 * Ha solo `updated_at` (niente created_at), gestito dal DB.
 */
class Polizza extends Model
{
    protected $table = 'polizze';

    public const CREATED_AT = null;

    protected $guarded = ['id'];

    /** @return BelongsTo<AgenziaNew, $this> */
    public function agenzia(): BelongsTo
    {
        return $this->belongsTo(AgenziaNew::class, 'id_agenzia');
    }
}
