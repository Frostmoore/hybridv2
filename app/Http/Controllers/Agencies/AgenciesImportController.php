<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agencies;

use App\Http\Controllers\Concerns\ImportaLePolizze;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\AdminImportPolizzeController;
use App\Models\Operatore;
use App\Models\Polizza;
use App\Services\PolizzeSheetImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Import delle polizze dentro il pannello agenzie.
 *
 * Finora le polizze si caricavano **solo** dal dominio principale, con una
 * pagina protetta da una password condivisa e una tendina per scegliere
 * l'agenzia: in pratica le caricava l'amministratore per conto di tutti. Qui
 * le carica l'agenzia stessa, e l'agenzia di destinazione **non e' un campo del
 * form**: viene dall'operatore in sessione (`$operatore->agid`). E' questo che
 * rende la pagina apribile alle agenzie senza che una possa scrivere nelle
 * polizze di un'altra, anche manomettendo la richiesta.
 *
 * Il giro di import vero e proprio sta in {@see ImportaLePolizze}, condiviso
 * con la pagina gemella del pannello admin
 * ({@see AdminImportPolizzeController}), dove invece
 * l'agenzia si sceglie da una tendina.
 */
class AgenciesImportController extends Controller
{
    use ImportaLePolizze;

    public function page()
    {
        $operatore = $this->operatore();
        $agid = (int) $operatore->agid;

        return view('polizze.carica', [
            'layout' => $this->layoutPagina(),
            'base' => $this->rottaBase(),
            'operatore' => $operatore,
            'agenzie' => null,          // qui l'agenzia non si sceglie
            'inArchivio' => Polizza::where('id_agenzia', $agid)->count(),
            'ultimoAggiornamento' => Polizza::where('id_agenzia', $agid)->max('updated_at'),
            'estensioni' => PolizzeSheetImportService::ESTENSIONI,
        ]);
    }

    // ─── quello che questo pannello fa a modo suo ────────────────────────────

    /**
     * Sempre l'agenzia dell'operatore in sessione, sia al passo 1 sia dopo.
     * Non guarda la richiesta: e' il punto di tutta la pagina.
     */
    protected function agenziaDestinazione(Request $request, bool $nuovo): int
    {
        return (int) $this->operatore()->agid;
    }

    protected function rottaBase(): string
    {
        return 'polizze';
    }

    protected function layoutPagina(): string
    {
        return 'layouts.agencies';
    }

    protected function chiaveSessione(): string
    {
        return 'import_polizze_in_corso';
    }

    private function operatore(): Operatore
    {
        /** @var Operatore $operatore */
        $operatore = Auth::guard('operatore')->user();

        return $operatore;
    }
}
