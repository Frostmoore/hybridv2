<?php

namespace Tests\Feature\Import;

use App\Models\Polizza;
use App\Services\PolizzeSheetImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Il servizio che importa un foglio con la mappatura scelta a mano.
 *
 * Qui si prova quello che la pagina non puo' provare: il riconoscimento del
 * tipo di file dal contenuto, le conversioni delle date e i limiti di lunghezza.
 */
class PolizzeSheetImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $cartella;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartella = sys_get_temp_dir().'/polizze-sheet-'.uniqid();
        mkdir($this->cartella);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cartella.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->cartella);
        parent::tearDown();
    }

    private function file(string $nome, string $contenuto): string
    {
        $percorso = $this->cartella.'/'.$nome;
        file_put_contents($percorso, $contenuto);

        return $percorso;
    }

    // ─── riconoscimento del formato ──────────────────────────────────────

    public function test_legge_un_csv_con_punto_e_virgola_e_bom(): void
    {
        $percorso = $this->file('a.csv', "\xEF\xBB\xBFCF;Polizza\nRSSMRA80A01H501U;A1\n");

        $righe = PolizzeSheetImportService::leggi($percorso);

        // Il BOM non deve restare attaccato al nome della prima colonna,
        // altrimenti nessun alias la riconosce piu'.
        $this->assertSame(['CF', 'Polizza'], $righe[0]);
        $this->assertSame(['RSSMRA80A01H501U', 'A1'], $righe[1]);
    }

    public function test_legge_un_csv_con_la_virgola(): void
    {
        $percorso = $this->file('a.csv', "CF,Polizza\nRSSMRA80A01H501U,A1\n");

        $this->assertSame(['CF', 'Polizza'], PolizzeSheetImportService::leggi($percorso)[0]);
    }

    public function test_legge_un_xlsx(): void
    {
        $percorso = $this->cartella.'/a.xlsx';
        $zip = new ZipArchive;
        $zip->open($percorso, ZipArchive::CREATE);
        $zip->addFromString('xl/sharedStrings.xml',
            '<sst><si><t>CF</t></si><si><t>Polizza</t></si><si><t>RSSMRA80A01H501U</t></si><si><t>A1</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml',
            '<worksheet><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>'
            .'<row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2" t="s"><v>3</v></c></row>'
            .'</sheetData></worksheet>');
        $zip->close();

        $righe = PolizzeSheetImportService::leggi($percorso);

        $this->assertSame(['CF', 'Polizza'], $righe[0]);
        $this->assertSame(['RSSMRA80A01H501U', 'A1'], $righe[1]);
    }

    public function test_legge_un_finto_xls_che_e_una_tabella_html(): void
    {
        // Diversi gestionali esportano questo e lo chiamano .xls. Fidarsi
        // dell'estensione vorrebbe dire rifiutare un file leggibile.
        $percorso = $this->file('a.xls',
            '<html><body><table>'
            .'<tr><th>CF</th><th>Polizza</th></tr>'
            .'<tr><td>RSSMRA80A01H501U</td><td>A1</td></tr>'
            .'</table></body></html>');

        $righe = PolizzeSheetImportService::leggi($percorso);

        $this->assertSame(['CF', 'Polizza'], $righe[0]);
        $this->assertSame(['RSSMRA80A01H501U', 'A1'], $righe[1]);
    }

    public function test_xls_97_2003_da_un_errore_che_spiega_cosa_fare(): void
    {
        $percorso = $this->file('a.xls', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 64));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/\.xlsx/');
        PolizzeSheetImportService::leggi($percorso);
    }

    public function test_file_vuoto_da_errore(): void
    {
        $this->expectException(RuntimeException::class);
        PolizzeSheetImportService::leggi($this->file('a.csv', "\n;;\n"));
    }

    // ─── mappatura ───────────────────────────────────────────────────────

    public function test_gli_alias_preselezionano_ma_un_campo_una_volta_sola(): void
    {
        $suggerita = PolizzeSheetImportService::suggerisciMappatura(
            ['Cod. Fiscale', 'N. Polizza', 'Scadenza', 'Scadenza', 'Pippo']
        );

        $this->assertSame('cf', $suggerita[0]);
        $this->assertSame('n_polizza', $suggerita[1]);
        $this->assertSame('data_scadenza_contratto', $suggerita[2]);
        // La seconda "Scadenza" non ruba il campo alla prima, e "Pippo" non
        // corrisponde a niente: restano da scegliere a mano.
        $this->assertArrayNotHasKey(3, $suggerita);
        $this->assertArrayNotHasKey(4, $suggerita);
    }

    public function test_errori_di_mappatura(): void
    {
        $this->assertSame([], PolizzeSheetImportService::erroriMappatura([0 => 'cf', 1 => 'n_polizza']));
        $this->assertNotEmpty(PolizzeSheetImportService::erroriMappatura([0 => 'cf']));
        $this->assertNotEmpty(PolizzeSheetImportService::erroriMappatura([0 => 'cf', 1 => 'n_polizza', 2 => 'cf']));
        $this->assertNotEmpty(PolizzeSheetImportService::erroriMappatura([0 => 'cf', 1 => 'n_polizza', 2 => 'inventato']));
    }

    public function test_nomi_di_comodo_delle_colonne(): void
    {
        $this->assertSame('Colonna A', PolizzeSheetImportService::nomeColonna(0));
        $this->assertSame('Colonna Z', PolizzeSheetImportService::nomeColonna(25));
        $this->assertSame('Colonna AA', PolizzeSheetImportService::nomeColonna(26));
    }

    // ─── conversioni ─────────────────────────────────────────────────────

    public function test_tutte_le_date_finiscono_in_iso(): void
    {
        $righe = [
            ['CF', 'POL', 'D1', 'D2', 'D3'],
            ['RSSMRA80A01H501U', 'A1', '01/03/2026', '2026-12-31', '46022'],
            ['VRDLGI75B02H501X', 'A2', '1-3-26', '2026-12-31 00:00:00', 'quando capita'],
        ];
        $mappatura = [0 => 'cf', 1 => 'n_polizza', 2 => 'data_decorrenza',
            3 => 'data_scadenza_contratto', 4 => 'data_scadenza_titolo'];

        $resoconto = (new PolizzeSheetImportService)->importa($righe, $mappatura, 7);

        $a1 = Polizza::where('n_polizza', 'A1')->firstOrFail();
        $this->assertSame('2026-03-01', $a1->data_decorrenza);
        $this->assertSame('2026-12-31', $a1->data_scadenza_contratto);
        $this->assertSame('2025-12-31', $a1->data_scadenza_titolo); // seriale Excel 46022

        $a2 = Polizza::where('n_polizza', 'A2')->firstOrFail();
        $this->assertSame('2026-03-01', $a2->data_decorrenza);      // anno a due cifre
        $this->assertSame('2026-12-31', $a2->data_scadenza_contratto); // via l'orario
        $this->assertSame('quando capita', $a2->data_scadenza_titolo); // intatto, ma contato

        $this->assertSame(1, $resoconto['date_non_riconosciute']);
        $this->assertSame(2, $resoconto['importate']);
    }

    public function test_una_data_impossibile_resta_com_e(): void
    {
        $righe = [['RSSMRA80A01H501U', 'A1', '31/02/2026']];
        $resoconto = (new PolizzeSheetImportService)->importa(
            $righe, [0 => 'cf', 1 => 'n_polizza', 2 => 'data_decorrenza'], 7, false, false
        );

        $this->assertSame('31/02/2026', Polizza::where('n_polizza', 'A1')->value('data_decorrenza'));
        $this->assertSame(1, $resoconto['date_non_riconosciute']);
    }

    public function test_il_codice_fiscale_si_normalizza_e_i_non_standard_si_contano(): void
    {
        $righe = [
            ['  rssmra80a01h501u ', 'A1'],
            ['01234567890', 'A2'],   // partita IVA: si importa, ma si conta
        ];
        $resoconto = (new PolizzeSheetImportService)->importa(
            $righe, [0 => 'cf', 1 => 'n_polizza'], 7, false, false
        );

        $this->assertSame('RSSMRA80A01H501U', Polizza::where('n_polizza', 'A1')->value('cf'));
        $this->assertSame(2, $resoconto['importate']);
        $this->assertSame(1, $resoconto['cf_non_standard']);
    }

    public function test_i_valori_troppo_lunghi_si_troncano_e_si_contano(): void
    {
        $righe = [['RSSMRA80A01H501U', 'A1', str_repeat('X', 400)]];
        $resoconto = (new PolizzeSheetImportService)->importa(
            $righe, [0 => 'cf', 1 => 'n_polizza', 2 => 'contraente'], 7, false, false
        );

        $this->assertSame(255, mb_strlen((string) Polizza::where('n_polizza', 'A1')->value('contraente')));
        $this->assertSame(1, $resoconto['valori_troncati']);
    }

    public function test_mappatura_non_valida_alza_eccezione_prima_di_toccare_il_database(): void
    {
        Polizza::create(['id_agenzia' => 7, 'cf' => 'RSSMRA80A01H501U', 'n_polizza' => 'VECCHIA']);

        try {
            (new PolizzeSheetImportService)->importa(
                [['RSSMRA80A01H501U', 'A1']], [0 => 'cf'], 7, sostituisci: true, primaRigaIntestazione: false
            );
            $this->fail('Doveva alzare RuntimeException.');
        } catch (RuntimeException) {
            // La "sostituzione" non deve essere avvenuta.
            $this->assertDatabaseHas('polizze', ['n_polizza' => 'VECCHIA']);
        }
    }

    public function test_agenzia_mancante_alza_eccezione(): void
    {
        $this->expectException(RuntimeException::class);
        (new PolizzeSheetImportService)->importa(
            [['RSSMRA80A01H501U', 'A1']], [0 => 'cf', 1 => 'n_polizza'], 0, false, false
        );
    }
}
