<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ImportaLePolizze;
use App\Http\Controllers\Controller;
use App\Models\AgenziaNew;
use App\Models\Polizza;
use App\Services\PolizzeSheetImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * La stessa pagina di import del pannello agenzie, montata sul dominio
 * principale a `/importa-polizze`, dentro la sessione di amministratore.
 *
 * Unica differenza: qui **l'agenzia si sceglie da una tendina**, perche'
 * l'amministratore carica per conto di chiunque. E' esattamente la cosa che
 * nel pannello agenzie non puo' esistere — li' l'id viene dalla sessione
 * dell'operatore, altrimenti un'agenzia potrebbe scrivere nelle polizze di
 * un'altra. Qui il gate e' la sessione admin, che protegge la rotta.
 *
 * L'agenzia scelta al passo 1 viene **salvata insieme al file** e riusata al
 * passo 3: non si rilegge dalla richiesta, cosi' non puo' cambiare per strada
 * fra la mappatura e la scrittura.
 *
 * ⚠️ A questo URL stava il vecchio wizard
 * ({@see ImportaPolizzeController}), che scriveva nella tabella
 * `polizze_importate` — che **non legge nessuno**: ne' l'app, ne' l'area
 * agenzie, ne' il sito. Era lavoro a vuoto, e questa pagina lo sostituisce
 * scrivendo in `polizze`, cioe' la tabella che l'endpoint dell'app serve
 * davvero. Il vecchio controller resta nel codice ma non e' piu' raggiungibile
 * da qui.
 */
class AdminImportPolizzeController extends Controller
{
    use ImportaLePolizze;

    public function page()
    {
        return view('polizze.carica', [
            'layout' => $this->layoutPagina(),
            'base' => $this->rottaBase(),
            'operatore' => null,
            'agenzie' => $this->elencoAgenzie(),
            'inArchivio' => Polizza::count(),
            'ultimoAggiornamento' => Polizza::max('updated_at'),
            'estensioni' => PolizzeSheetImportService::ESTENSIONI,
        ]);
    }

    // ─── quello che questo pannello fa a modo suo ────────────────────────────

    /**
     * Al passo 1 l'agenzia arriva dalla tendina e si controlla che esista
     * davvero; dopo si usa quella gia' salvata col file.
     */
    protected function agenziaDestinazione(Request $request, bool $nuovo): int
    {
        if (! $nuovo) {
            $attesa = $request->session()->get($this->chiaveSessione());

            return is_array($attesa) ? (int) ($attesa['agid'] ?? 0) : 0;
        }

        $id = (int) $request->input('agenzia_id', 0);

        return $id > 0 && AgenziaNew::whereKey($id)->exists() ? $id : 0;
    }

    protected function nomeAgenzia(int $id): ?string
    {
        return AgenziaNew::whereKey($id)->value('nome_agenzia');
    }

    protected function rottaBase(): string
    {
        return 'importa-polizze';
    }

    protected function layoutPagina(): string
    {
        return 'layouts.admin';
    }

    /** Chiave diversa da quella del pannello agenzie: i due non si rubano il file. */
    protected function chiaveSessione(): string
    {
        return 'admin_import_polizze_in_corso';
    }

    /**
     * Le agenzie per la tendina, ognuna col numero di polizze che ha gia'.
     *
     * Il conteggio serve: chi carica per conto di altri ha davanti una lista
     * di una quarantina di nomi, e vedere "0 polizze" accanto a quella che sta
     * per sostituire e' l'unico modo per accorgersi di aver sbagliato riga
     * prima di premere, non dopo.
     *
     * @return Collection<int, object>
     */
    private function elencoAgenzie()
    {
        $conteggi = Polizza::selectRaw('id_agenzia, count(*) as n')
            ->groupBy('id_agenzia')
            ->pluck('n', 'id_agenzia');

        return AgenziaNew::orderBy('nome_agenzia')
            ->get(['id', 'nome_agenzia'])
            ->map(fn (AgenziaNew $a) => (object) [
                'id' => $a->id,
                'nome' => $a->nome_agenzia,
                'polizze' => (int) ($conteggi[$a->id] ?? 0),
            ]);
    }
}
