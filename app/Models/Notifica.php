<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notifica privata (tabella `notifiche`).
 * `destinatari`/`letta_da` sono CSV di username (vedi critics.md).
 */
class Notifica extends Model
{
    protected $table = 'notifiche';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return BelongsTo<AgenziaNew, $this> */
    public function agenzia(): BelongsTo
    {
        return $this->belongsTo(AgenziaNew::class, 'agenziaid');
    }

    /**
     * Username dei destinatari (CSV con eventuale virgola finale → lista pulita).
     *
     * @return list<string>
     */
    public function destinatariList(): array
    {
        return self::csvToList((string) $this->destinatari);
    }

    /** @return list<string> */
    public function lettaDaList(): array
    {
        return self::csvToList((string) $this->letta_da);
    }

    public function isLettaDa(string $username): bool
    {
        return in_array(strtolower(trim($username)), array_map('strtolower', $this->lettaDaList()), true);
    }

    /** @return list<string> */
    private static function csvToList(string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $csv)), fn ($v) => $v !== ''));
    }
}
