<?php

namespace Tests\Feature\Import;

use App\Models\Polizza;
use App\Services\SsfImportService;
use Tests\Feature\ApiV2\V2TestCase;
use ZipArchive;

/**
 * Ogni caso qui dentro riproduce qualcosa trovato nell'export reale del
 * 01/11/2026 (69.504 polizze). Non sono casi di fantasia: sono le trappole che
 * hanno motivato le regole scritte in memory/decisioni.md.
 */
class SsfImportServiceTest extends V2TestCase
{
    private string $zip = '';

    protected function tearDown(): void
    {
        if ($this->zip !== '' && is_file($this->zip)) {
            unlink($this->zip);
        }
        parent::tearDown();
    }

    /**
     * @param  list<array<string, string>>  $polizze
     * @param  list<array<string, string>>  $clienti
     * @param  list<array<string, string>>  $altro
     */
    private function creaZip(array $polizze, array $clienti, array $altro = [], bool $v10 = false): string
    {
        $this->zip = tempnam(sys_get_temp_dir(), 'ssf').'.zip';
        $zip = new ZipArchive;
        $zip->open($this->zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $csv = function (array $righe): string {
            if ($righe === []) {
                return '';
            }
            $out = "\xEF\xBB\xBF".implode(';', array_keys($righe[0]))."\n";
            foreach ($righe as $r) {
                $out .= implode(';', array_values($r))."\n";
            }

            return $out;
        };

        // v7 minuscolo, v10 capitalizzato: l'importer deve reggere entrambi
        $nomi = $v10
            ? ['Polizze.csv', 'Clienti.csv', 'PolizzeAltro.csv']
            : ['polizze.csv', 'clienti.csv', 'polizzealtro.csv'];
        $zip->addFromString($nomi[0], $csv($polizze));
        $zip->addFromString($nomi[1], $csv($clienti));
        $zip->addFromString($nomi[2], $csv($altro));
        $zip->close();

        return $this->zip;
    }

    private function polizza(array $override = []): array
    {
        return array_merge([
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
        ], $override);
    }

    private function cliente(array $override = []): array
    {
        return array_merge([
            'ID_ANAGRAFICA_EXP' => 'RSSMRA80A01H501U',
            'RAGIONE_SOCIALE' => 'Mario Rossi',
            'CODICE_FISCALE' => 'RSSMRA80A01H501U',
        ], $override);
    }

    public function test_importa_una_polizza_attiva_con_i_campi_tradotti(): void
    {
        $zip = $this->creaZip([$this->polizza()], [$this->cliente()],
            [['NUMERO_POLIZZA_CMP' => '100000001', 'TARGA' => 'AB123CD']]);

        $esito = (new SsfImportService)->importa($zip, 7);

        $this->assertSame(1, $esito['importate']);
        $p = Polizza::sole();
        $this->assertSame('RSSMRA80A01H501U', $p->cf);
        $this->assertSame('Mario Rossi', $p->contraente);
        $this->assertSame('AB123CD', $p->targa);
        $this->assertSame('Attiva', $p->stato_polizza);
        $this->assertSame('Annuale', $p->frazionamento, 'il codice 1 vale "Annuale"');
        // le date devono diventare ISO: l'app ci fa DateTime.parse()
        $this->assertSame('2013-06-10', $p->data_decorrenza);
        $this->assertSame('2027-02-01', $p->data_scadenza_titolo);
        $this->assertSame('2028-02-01', $p->data_scadenza_contratto);
    }

    public function test_scarta_le_polizze_non_attive(): void
    {
        $zip = $this->creaZip([
            $this->polizza(['NUMERO_POLIZZA_CMP' => 'A1', 'COD_STATO_SHARE' => 'ST']),
            $this->polizza(['NUMERO_POLIZZA_CMP' => 'A2', 'COD_STATO_SHARE' => 'SO']),
            $this->polizza(['NUMERO_POLIZZA_CMP' => 'A3']),
        ], [$this->cliente()]);

        $esito = (new SsfImportService)->importa($zip, 7);

        $this->assertSame(1, $esito['importate']);
        $this->assertSame(2, $esito['scartate_non_attive']);
    }

    /**
     * Il caso `Prima.it`: lo stesso "numero" su clienti diversi. Senza questa
     * regola un utente vedrebbe le polizze di un altro.
     */
    public function test_scarta_i_numeri_di_polizza_usati_da_piu_clienti(): void
    {
        $zip = $this->creaZip([
            $this->polizza(['NUMERO_POLIZZA_CMP' => 'Prima.it', 'ID_ANAGRAFICA_EXP' => 'RSSMRA80A01H501U']),
            $this->polizza(['NUMERO_POLIZZA_CMP' => 'Prima.it', 'ID_ANAGRAFICA_EXP' => 'VRDLGU75B02F205X']),
            $this->polizza(['NUMERO_POLIZZA_CMP' => '900000001']),
        ], [
            $this->cliente(),
            $this->cliente(['ID_ANAGRAFICA_EXP' => 'VRDLGU75B02F205X', 'CODICE_FISCALE' => 'VRDLGU75B02F205X']),
        ]);

        $esito = (new SsfImportService)->importa($zip, 7);

        $this->assertSame(1, $esito['importate']);
        $this->assertSame(2, $esito['scartate_segnaposto']);
        $this->assertSame('900000001', Polizza::sole()->n_polizza);
    }

    /** Il codice fiscale si recupera dall'ID quando l'anagrafica non c'e'. */
    public function test_ricava_il_codice_fiscale_dall_id_anagrafica(): void
    {
        $zip = $this->creaZip(
            [$this->polizza(['ID_ANAGRAFICA_EXP' => 'PF_BNCLRA90C45H501K'])],
            [] // nessun cliente: il join fallisce di proposito
        );

        $esito = (new SsfImportService)->importa($zip, 7);

        $this->assertSame(1, $esito['importate']);
        $this->assertSame('BNCLRA90C45H501K', Polizza::sole()->cf, 'il prefisso PF_ va tolto');
        $this->assertSame('', Polizza::sole()->contraente, 'il nominativo invece non si inventa');
    }

    /** Decisione del cliente: per ora solo persone fisiche. */
    public function test_scarta_partite_iva_e_identificativi_interni(): void
    {
        $zip = $this->creaZip([
            $this->polizza(['NUMERO_POLIZZA_CMP' => 'B1', 'ID_ANAGRAFICA_EXP' => '12345678901']),
            $this->polizza(['NUMERO_POLIZZA_CMP' => 'B2', 'ID_ANAGRAFICA_EXP' => 'AA0504']),
            $this->polizza(['NUMERO_POLIZZA_CMP' => 'B3']),
        ], [$this->cliente()]);

        $esito = (new SsfImportService)->importa($zip, 7);

        $this->assertSame(1, $esito['importate']);
        $this->assertSame(2, $esito['scartate_senza_cf']);
    }

    /** Mancando solo il conto alla rovescia, la polizza si importa lo stesso. */
    public function test_importa_anche_senza_scadenza_del_titolo(): void
    {
        $zip = $this->creaZip([$this->polizza(['SCADENZA_INCASSATO' => ''])], [$this->cliente()]);

        $esito = (new SsfImportService)->importa($zip, 7);

        $this->assertSame(1, $esito['importate']);
        $this->assertSame(1, $esito['senza_scadenza_titolo']);
        $this->assertSame('', Polizza::sole()->data_scadenza_titolo);
        $this->assertSame('2028-02-01', Polizza::sole()->data_scadenza_contratto);
    }

    public function test_legge_anche_il_formato_v10_coi_nomi_capitalizzati(): void
    {
        $zip = $this->creaZip([$this->polizza()], [$this->cliente()], [], v10: true);

        $this->assertSame(1, (new SsfImportService)->importa($zip, 7)['importate']);
    }

    /**
     * Fra v7 e v10 le colonne cambiano posizione: leggendo per indice invece
     * che per nome i campi finirebbero mescolati.
     */
    public function test_legge_le_colonne_per_nome_non_per_posizione(): void
    {
        $girata = array_reverse($this->polizza(), preserve_keys: true);
        $zip = $this->creaZip([$girata], [$this->cliente()]);

        (new SsfImportService)->importa($zip, 7);

        $this->assertSame('2013-06-10', Polizza::sole()->data_decorrenza);
        $this->assertSame('Prodotto di prova', Polizza::sole()->prodotto);
    }

    public function test_sostituisce_le_polizze_precedenti_della_stessa_agenzia(): void
    {
        Polizza::create(['id_agenzia' => 7, 'cf' => 'RSSMRA80A01H501U', 'n_polizza' => 'vecchia']);
        Polizza::create(['id_agenzia' => 9, 'cf' => 'RSSMRA80A01H501U', 'n_polizza' => 'altra-agenzia']);

        $zip = $this->creaZip([$this->polizza()], [$this->cliente()]);
        (new SsfImportService)->importa($zip, 7);

        $this->assertNull(Polizza::where('n_polizza', 'vecchia')->first(),
            'le vecchie dell agenzia 7 vanno via');
        $this->assertNotNull(Polizza::where('n_polizza', 'altra-agenzia')->first(),
            'quelle delle altre agenzie non si toccano');
    }

    public function test_piu_targhe_finiscono_nello_stesso_campo(): void
    {
        $zip = $this->creaZip([$this->polizza()], [$this->cliente()], [
            ['NUMERO_POLIZZA_CMP' => '100000001', 'TARGA' => 'AB123CD'],
            ['NUMERO_POLIZZA_CMP' => '100000001', 'TARGA' => 'EF456GH'],
        ]);

        (new SsfImportService)->importa($zip, 7);

        $this->assertSame('AB123CD, EF456GH', Polizza::sole()->targa);
    }
}
