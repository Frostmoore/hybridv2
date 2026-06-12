<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Admin del pannello principale (tabella `utenti`).
 * Guard a sessione `admin` (fase 6). Username column: `nomeutente`.
 */
class Utente extends Authenticatable
{
    protected $table = 'utenti';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'hash'];
}
