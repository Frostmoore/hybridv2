<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notifica pubblica per agenzia (tabella `notifiche_generali`).
 */
class NotificaGenerale extends Model
{
    protected $table = 'notifiche_generali';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return BelongsTo<AgenziaNew, $this> */
    public function agenzia(): BelongsTo
    {
        return $this->belongsTo(AgenziaNew::class, 'notifica_agid');
    }
}
