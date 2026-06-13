<?php

namespace Tests\Unit;

use App\Support\LegacyRowSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LegacyRowSanitizerTest extends TestCase
{
    // ─── normalizeDate ───────────────────────────────────────────────────

    #[DataProvider('dateProvider')]
    public function test_normalize_date(string $input, string $expected): void
    {
        $this->assertSame($expected, LegacyRowSanitizer::normalizeDate($input));
    }

    public static function dateProvider(): array
    {
        return [
            'già canonico'        => ['2024-12-20 14:30:00', '2024-12-20 14:30:00'],
            'ISO senza secondi'   => ['2024-12-20 14:30', '2024-12-20 14:30:00'],
            'solo data ISO'       => ['2024-12-20', '2024-12-20 00:00:00'],
            'd-m-Y (legacy)'      => ['20-12-2024', '2024-12-20 00:00:00'],
            'd/m/Y (form web)'    => ['20/12/2024', '2024-12-20 00:00:00'],
            'd/m/Y con ora'       => ['20/12/2024 09:05', '2024-12-20 09:05:00'],
            'vuoto resta vuoto'   => ['', ''],
            'spazi attorno'       => ['  20-12-2024  ', '2024-12-20 00:00:00'],
            'illeggibile invariato' => ['non una data', 'non una data'],
            'data impossibile invariata' => ['32-13-2024', '32-13-2024'],
        ];
    }

    // ─── normalizeBool ───────────────────────────────────────────────────

    #[DataProvider('boolProvider')]
    public function test_normalize_bool(mixed $input, string $expected): void
    {
        $this->assertSame($expected, LegacyRowSanitizer::normalizeBool($input));
    }

    public static function boolProvider(): array
    {
        return [
            "'on' (form web)" => ['on', '1'],
            "'1' (v2)"        => ['1', '1'],
            "'true'"          => ['true', '1'],
            'bool true'       => [true, '1'],
            "'ON' maiuscolo"  => ['ON', '1'],
            "'' vuoto"        => ['', '0'],
            "'0'"             => ['0', '0'],
            'null'            => [null, '0'],
            "'off'"           => ['off', '0'],
            'bool false'      => [false, '0'],
        ];
    }

    public function test_sanitize_normalizes_privacy_denuncia(): void
    {
        $this->assertSame('1', LegacyRowSanitizer::sanitize('sinistri', ['privacy_denuncia' => 'on'])['privacy_denuncia']);
        $this->assertSame('0', LegacyRowSanitizer::sanitize('preventivi', ['privacy_denuncia' => ''])['privacy_denuncia']);
        $this->assertSame('1', LegacyRowSanitizer::sanitize('documenti', ['privacy_denuncia' => '1'])['privacy_denuncia']);
    }

    // ─── normalizeCsv ────────────────────────────────────────────────────

    #[DataProvider('csvProvider')]
    public function test_normalize_csv(string $input, string $expected): void
    {
        $this->assertSame($expected, LegacyRowSanitizer::normalizeCsv($input));
    }

    public static function csvProvider(): array
    {
        return [
            'virgola finale'   => ['gsdf,gfds,', 'gsdf,gfds'],
            'già pulito'       => ['a,b,c', 'a,b,c'],
            'spazi e vuoti'    => [' a , , b ,', 'a,b'],
            'vuoto'            => ['', ''],
            'solo virgole'     => [',,,', ''],
            'singolo + virgola' => ['mario.rossi,', 'mario.rossi'],
        ];
    }

    public function test_sanitize_cleans_notifiche_csv_columns(): void
    {
        $row = LegacyRowSanitizer::sanitize('notifiche', [
            'destinatari' => 'a,b,', 'letta_da' => 'x,,',
        ]);
        $this->assertSame('a,b', $row['destinatari']);
        $this->assertSame('x', $row['letta_da']);
    }

    // ─── sanitize applica la normalizzazione alle colonne giuste ──────────

    public function test_sanitize_normalizes_dataora_on_notifiche(): void
    {
        $row = LegacyRowSanitizer::sanitize('notifiche', ['dataora' => '20-12-2024', 'titolo' => 'x']);
        $this->assertSame('2024-12-20 00:00:00', $row['dataora']);
    }

    public function test_sanitize_normalizes_data_denuncia_on_sinistri(): void
    {
        $row = LegacyRowSanitizer::sanitize('sinistri', ['data_denuncia' => '20/12/2024']);
        $this->assertSame('2024-12-20 00:00:00', $row['data_denuncia']);
    }

    public function test_sanitize_leaves_unrelated_table_dates_untouched(): void
    {
        // 'clienti' non è tra le DATE_COLUMNS: nessuna normalizzazione di stringhe
        $row = LegacyRowSanitizer::sanitize('clienti', ['nome' => '20/12/2024']);
        $this->assertSame('20/12/2024', $row['nome']);
    }
}
