<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Interruttori delle feature fuori contratto di una singola agenzia
 * (tabella `agenzie_speciale`, 1:1 con agenzie_new). Assenza di riga = tutto
 * spento: chi legge deve gestire il null, non dare per scontata la riga.
 */
class AgenziaSpeciale extends Model
{
    protected $table = 'agenzie_speciale';

    protected $primaryKey = 'id_agenzia';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'consulenza_attiva' => 'boolean',
        ];
    }
}
