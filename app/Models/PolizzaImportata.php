<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Polizza importata via importa_polizze.php (tabella `polizze_importate`, sistema A).
 */
class PolizzaImportata extends Model
{
    protected $table = 'polizze_importate';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['premio' => 'decimal:2'];
    }

    /** @return BelongsTo<AgenziaNew, $this> */
    public function agenzia(): BelongsTo
    {
        return $this->belongsTo(AgenziaNew::class, 'id_agenzia');
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
