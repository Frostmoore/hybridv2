<?php

namespace Tests\Feature\Agencies;

use App\Models\Operatore;
use App\Models\Polizza;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\ApiV2\V2TestCase;
use ZipArchive;

/**
 * Import delle polizze dal pannello agenzie (passo 1 upload, passo 2 mappatura).
 *
 * Quello che questi test difendono, oltre al funzionamento:
 *
 * - **l'agenzia non e' un parametro.** Nessuna richiesta puo' spostare le
 *   polizze in un'altra agenzia: l'id viene dall'operatore in sessione.
 * - **la mappatura sbagliata non distrugge l'archivio.** La cancellazione in
 *   modalita' "sostituisci" avviene dopo la validazione, non prima.
 * - **le date finiscono in ISO.** L'app spezza la data sul `-` e ordina per
 *   confronto fra stringhe: un `31/12/2026` in tabella romperebbe entrambe le
 *   cose in silenzio.
 */
class PolizzeImportTest extends V2TestCase
{
    private function host(string $path): string
    {
        return 'http://'.config('hybrid.domain_agencies').'/'.ltrim($path, '/');
    }

    /** Operatore loggato di un'agenzia nuova; ritorna [operatore, idAgenzia]. */
    private function operatoreLoggato(string $username = 'op.uno'): array
    {
        $agenzia = $this->makeAgency([
            'nome_agenzia' => 'Agenzia '.$username,
            'token' => 'tok-'.$username,
            'token_interno' => 'int-'.$username,
        ]);
        $operatore = Operatore::create([
            'username' => $username,
            'email' => $username.'@test.it',
            'password' => Hash::make('OpPass!1'),
            'active' => '1',
            'agid' => $agenzia->id,
        ]);
        $this->actingAs($operatore, 'operatore');

        return [$operatore, (int) $agenzia->id];
    }

    private function csv(string $contenuto, string $nome = 'polizze.csv'): UploadedFile
    {
        $percorso = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($percorso, $contenuto);

        return new UploadedFile($percorso, $nome, 'text/csv', null, true);
    }

    /** CSV con intestazioni che gli alias NON riconoscono, per forzare la mappatura a mano. */
    private function csvDiEsempio(): UploadedFile
    {
        return $this->csv(
            "Tessera sanitaria;Contratto nr.;Chi firma;Decorre il;Prossimo rinnovo\n"
            ."RSSMRA80A01H501U;POL-001;Mario Rossi;01/03/2026;01/03/2027\n"
            ."VRDLGI75B02H501X;POL-002;Luigi Verdi;15/06/2026;15/06/2027\n"
        );
    }

    /** ZIP minimo nella forma dell'export SSF (v7, nomi minuscoli). */
    private function zipSsf(): UploadedFile
    {
        $percorso = tempnam(sys_get_temp_dir(), 'ssf').'.zip';
        $csv = function (array $righe): string {
            $out = implode(';', array_keys($righe[0]))."\n";
            foreach ($righe as $r) {
                $out .= implode(';', array_values($r))."\n";
            }

            return $out;
        };

        $zip = new ZipArchive;
        $zip->open($percorso, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('polizze.csv', $csv([[
            'ID_ANAGRAFICA_EXP' => 'RSSMRA80A01H501U',
            'NUMERO_POLIZZA_CMP' => '100000001',
            'COMPAGNIA_ANIA' => '0292',
            'RAMO_CMP' => '050',
            'PRODOTTO_CMP' => 'Prodotto di prova',
            'COD_STATO_SHARE' => 'AT',
            'EFFETTO' => '10/06/2013',
            'SCADENZA_EFFETTIVA' => '01/02/2028',
            'SCADENZA_INCASSATO' => '01/02/2027',
            'FRAZIONAMENTO_SHARE' => '1',
        ]]));
        $zip->addFromString('clienti.csv', $csv([[
            'ID_ANAGRAFICA_EXP' => 'RSSMRA80A01H501U',
            'RAGIONE_SOCIALE' => 'Mario Rossi',
            'CODICE_FISCALE' => 'RSSMRA80A01H501U',
        ]]));
        $zip->addFromString('polizzealtro.csv', $csv([[
            'NUMERO_POLIZZA_CMP' => '100000001',
            'TARGA' => 'AB123CD',
        ]]));
        $zip->close();

        return new UploadedFile($percorso, 'SSF_01112026_v1.zip', 'application/zip', null, true);
    }

    // ─── ZIP SSF: nessuna mappatura, si importa subito ──────────────────

    public function test_lo_zip_ssf_si_importa_senza_passare_dalla_mappatura(): void
    {
        [, $agid] = $this->operatoreLoggato();

        $this->post($this->host('polizze/carica'), ['file' => $this->zipSsf()])
            ->assertRedirect($this->host('polizze'));

        // L'agenzia e' quella dell'operatore, non un campo del form.
        $p = Polizza::sole();
        $this->assertSame($agid, (int) $p->id_agenzia);
        $this->assertSame('100000001', $p->n_polizza);
        $this->assertSame('AB123CD', $p->targa);
        $this->assertSame('2027-02-01', $p->data_scadenza_titolo);

        $this->assertSame(1, session('esito')['conteggi']['importate']);
        $this->assertSame('ssf', session('esito')['tipo']);
    }

    public function test_uno_zip_qualunque_dice_cosa_fare_invece_di_schiantarsi(): void
    {
        $this->operatoreLoggato();

        $percorso = tempnam(sys_get_temp_dir(), 'zip').'.zip';
        $zip = new ZipArchive;
        $zip->open($percorso, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('qualcosa.txt', 'niente a che vedere');
        $zip->close();

        $this->post($this->host('polizze/carica'), [
            'file' => new UploadedFile($percorso, 'archivio.zip', 'application/zip', null, true),
        ])->assertSessionHas('errore', fn (string $m): bool => str_contains($m, 'non sembra un export SSF'));

        $this->assertSame(0, Polizza::count());
    }

    // ─── accesso ─────────────────────────────────────────────────────────

    public function test_ospite_non_entra(): void
    {
        $this->get($this->host('polizze'))->assertRedirect($this->host('login'));
        $this->post($this->host('polizze/carica'))->assertRedirect();
    }

    public function test_la_pagina_mostra_quante_polizze_ci_sono(): void
    {
        [, $agid] = $this->operatoreLoggato();
        Polizza::create(['id_agenzia' => $agid, 'cf' => 'RSSMRA80A01H501U', 'n_polizza' => 'A1']);

        $this->get($this->host('polizze'))
            ->assertOk()
            ->assertSee('Carica un file')
            ->assertSee('polizza in archivio');
    }

    // ─── passo 1 → passo 2 ───────────────────────────────────────────────

    public function test_il_csv_porta_alla_mappatura_con_anteprima_dei_valori(): void
    {
        $this->operatoreLoggato();

        $this->post($this->host('polizze/carica'), ['file' => $this->csvDiEsempio(), 'modalita' => 'aggiorna'])
            ->assertRedirect($this->host('polizze/mappatura'));

        $this->get($this->host('polizze/mappatura'))
            ->assertOk()
            ->assertSee('Tessera sanitaria')          // il nome vero della colonna
            ->assertSee('RSSMRA80A01H501U')           // il primo valore vero
            ->assertSee('Codice fiscale del cliente') // la tendina dei campi
            ->assertSee('Numero di polizza');
    }

    public function test_le_colonne_riconoscibili_sono_preselezionate(): void
    {
        $this->operatoreLoggato();

        $this->post($this->host('polizze/carica'), [
            'file' => $this->csv("CF;N. Polizza;Contraente\nRSSMRA80A01H501U;A1;Mario Rossi\n"),
        ]);

        $this->get($this->host('polizze/mappatura'))
            ->assertOk()
            ->assertViewHas('suggerita', [0 => 'cf', 1 => 'n_polizza', 2 => 'contraente']);
    }

    public function test_formato_non_gestito_viene_rifiutato(): void
    {
        $this->operatoreLoggato();

        $this->post($this->host('polizze/carica'), [
            'file' => UploadedFile::fake()->create('polizze.pdf', 4, 'application/pdf'),
        ])->assertSessionHas('errore', fn (string $m): bool => str_contains($m, 'Formato non gestito'));
    }

    // ─── passo 2: import ─────────────────────────────────────────────────

    public function test_import_con_mappatura_manuale(): void
    {
        [, $agid] = $this->operatoreLoggato();

        $this->post($this->host('polizze/carica'), ['file' => $this->csvDiEsempio()]);

        $this->post($this->host('polizze/importa'), [
            'colonna' => [0 => 'cf', 1 => 'n_polizza', 2 => 'contraente', 3 => 'data_decorrenza', 4 => 'data_scadenza_titolo'],
            'intestazione' => '1',
        ])->assertRedirect($this->host('polizze'));

        $this->assertSame(2, Polizza::where('id_agenzia', $agid)->count());

        $p = Polizza::where('n_polizza', 'POL-001')->firstOrFail();
        $this->assertSame($agid, (int) $p->id_agenzia);
        $this->assertSame('RSSMRA80A01H501U', $p->cf);
        $this->assertSame('Mario Rossi', $p->contraente);
        // Date in ISO: e' il formato che l'app sa leggere e ordinare.
        $this->assertSame('2026-03-01', $p->data_decorrenza);
        $this->assertSame('2027-03-01', $p->data_scadenza_titolo);
        // I campi non mappati restano vuoti, non nulli (le colonne sono NOT NULL).
        $this->assertSame('', $p->compagnia);
    }

    public function test_il_resoconto_dice_quante_e_perche_scartate(): void
    {
        $this->operatoreLoggato();

        $this->post($this->host('polizze/carica'), ['file' => $this->csv(
            "Tessera;Contratto\n"
            ."RSSMRA80A01H501U;POL-001\n"
            .";POL-002\n"              // senza codice fiscale
            ."VRDLGI75B02H501X;\n"     // senza numero
        )]);

        $this->post($this->host('polizze/importa'), [
            'colonna' => [0 => 'cf', 1 => 'n_polizza'],
            'intestazione' => '1',
        ]);

        $esito = session('esito');
        $this->assertSame(1, $esito['conteggi']['importate']);
        $this->assertSame(1, $esito['conteggi']['scartate_senza_cf']);
        $this->assertSame(1, $esito['conteggi']['scartate_senza_numero']);
    }

    public function test_senza_codice_fiscale_mappato_non_importa_e_non_cancella(): void
    {
        [, $agid] = $this->operatoreLoggato();
        Polizza::create(['id_agenzia' => $agid, 'cf' => 'RSSMRA80A01H501U', 'n_polizza' => 'VECCHIA']);

        // Modalita' "sostituisci": se la cancellazione avvenisse prima della
        // validazione, una mappatura incompleta svuoterebbe l'archivio.
        $this->post($this->host('polizze/carica'), ['file' => $this->csvDiEsempio(), 'modalita' => 'sostituisci']);

        $this->post($this->host('polizze/importa'), ['colonna' => [1 => 'n_polizza']])
            ->assertSessionHas('errore', fn (string $m): bool => str_contains($m, 'Codice fiscale'));

        $this->assertSame(1, Polizza::where('id_agenzia', $agid)->count());
        $this->assertDatabaseHas('polizze', ['n_polizza' => 'VECCHIA']);
    }

    public function test_lo_stesso_campo_su_due_colonne_viene_rifiutato(): void
    {
        $this->operatoreLoggato();
        $this->post($this->host('polizze/carica'), ['file' => $this->csvDiEsempio()]);

        $this->post($this->host('polizze/importa'), [
            'colonna' => [0 => 'cf', 1 => 'n_polizza', 2 => 'n_polizza'],
        ])->assertSessionHas('errore', fn (string $m): bool => str_contains($m, 'piu\' di una colonna'));

        $this->assertSame(0, Polizza::count());
    }

    public function test_sostituisci_azzera_solo_la_propria_agenzia(): void
    {
        [, $agidA] = $this->operatoreLoggato('op.uno');
        $altra = $this->makeAgency(['nome_agenzia' => 'Altra', 'token' => 't2', 'token_interno' => 'i2']);
        Polizza::create(['id_agenzia' => $agidA, 'cf' => 'RSSMRA80A01H501U', 'n_polizza' => 'VECCHIA-A']);
        Polizza::create(['id_agenzia' => $altra->id, 'cf' => 'RSSMRA80A01H501U', 'n_polizza' => 'VECCHIA-B']);

        $this->post($this->host('polizze/carica'), ['file' => $this->csvDiEsempio(), 'modalita' => 'sostituisci']);
        $this->post($this->host('polizze/importa'), [
            'colonna' => [0 => 'cf', 1 => 'n_polizza'],
            'intestazione' => '1',
        ]);

        $this->assertDatabaseMissing('polizze', ['n_polizza' => 'VECCHIA-A']);
        $this->assertDatabaseHas('polizze', ['n_polizza' => 'VECCHIA-B']);
        $this->assertSame(2, Polizza::where('id_agenzia', $agidA)->count());
    }

    public function test_ricaricare_lo_stesso_file_aggiorna_e_non_duplica(): void
    {
        [, $agid] = $this->operatoreLoggato();

        foreach (['Mario Rossi', 'Mario Rossi Jr'] as $nome) {
            $this->post($this->host('polizze/carica'), ['file' => $this->csv(
                "Tessera;Contratto;Chi firma\nRSSMRA80A01H501U;POL-001;{$nome}\n"
            )]);
            $this->post($this->host('polizze/importa'), [
                'colonna' => [0 => 'cf', 1 => 'n_polizza', 2 => 'contraente'],
                'intestazione' => '1',
            ]);
        }

        $this->assertSame(1, Polizza::where('id_agenzia', $agid)->count());
        $this->assertSame('Mario Rossi Jr', Polizza::where('n_polizza', 'POL-001')->value('contraente'));
    }

    public function test_senza_intestazione_la_prima_riga_e_un_dato(): void
    {
        [, $agid] = $this->operatoreLoggato();

        $this->post($this->host('polizze/carica'), ['file' => $this->csv(
            "RSSMRA80A01H501U;POL-001\nVRDLGI75B02H501X;POL-002\n"
        )]);
        $this->post($this->host('polizze/importa'), ['colonna' => [0 => 'cf', 1 => 'n_polizza']]);

        $this->assertSame(2, Polizza::where('id_agenzia', $agid)->count());
    }

    // ─── isolamento fra agenzie ──────────────────────────────────────────

    public function test_un_altra_agenzia_non_eredita_il_file_caricato(): void
    {
        $this->operatoreLoggato('op.uno');
        $this->post($this->host('polizze/carica'), ['file' => $this->csvDiEsempio()]);

        // Stessa sessione, operatore di un'altra agenzia: il file in attesa non
        // e' suo e non lo deve nemmeno vedere.
        $this->operatoreLoggato('op.due');

        $this->get($this->host('polizze/mappatura'))->assertRedirect($this->host('polizze'));
        $this->post($this->host('polizze/importa'), ['colonna' => [0 => 'cf', 1 => 'n_polizza']])
            ->assertRedirect($this->host('polizze'));

        $this->assertSame(0, Polizza::count());
    }

    public function test_annulla_butta_il_file(): void
    {
        $this->operatoreLoggato();
        $this->post($this->host('polizze/carica'), ['file' => $this->csvDiEsempio()]);

        $this->post($this->host('polizze/annulla'))->assertRedirect($this->host('polizze'));
        $this->get($this->host('polizze/mappatura'))->assertRedirect($this->host('polizze'));
    }
}
