<?php

declare(strict_types=1);

namespace App\Http\Controllers\ApiV2\Concerns;

use App\Mail\LegacyHtmlMail;
use App\Support\LegacyText;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Idraulica condivisa dagli endpoint "invio modulo con allegati": payload JSON
 * nel campo POST `data`, ZIP degli allegati, mail all'agenzia.
 *
 * Estratto da ClaimsController (metodi identici, solo private → protected) per
 * poterlo riusare dagli endpoint custom senza duplicare la logica: due copie di
 * zip+mail sarebbero divergute alla prima modifica.
 */
trait HandlesClaimSubmissions
{
    /**
     * Email destinataria per un'agenzia e un tipo ('denuncia'|'preventivo'|…):
     * usa l'override di config se presente, altrimenti il valore dal DB.
     * Sostituisce gli `if ($agencyId === 17)` hardcoded del legacy (critics.md).
     */
    protected function agencyMail(int $agencyId, string $kind, mixed $fallback): string
    {
        $override = config("hybrid.agency_mail_overrides.$agencyId.$kind");

        return LegacyText::normalizeEmail($override ?? $fallback);
    }

    /** @return array<string, mixed>|null */
    protected function dataPayload(Request $request): ?array
    {
        $raw = (string) $request->input('data', '{}');
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Crea lo ZIP con gli allegati richiesti (come zip_name/add_file_to_zip).
     * Ritorna il path RELATIVO allo storage locale, o false se lo ZIP non si apre.
     *
     * @param  array<string, string>  $entries  campo file → nome entry (senza estensione)
     */
    protected function buildZip(Request $request, string $subdir, string $label, array $entries): string|false
    {
        $relative = 'uploads/'.$subdir.'/'.date('Ymd').'-'.$label.'-'.bin2hex(random_bytes(4)).'.zip';
        $absolute = Storage::path($relative);

        $dir = dirname($absolute);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($absolute, ZipArchive::CREATE) !== true) {
            return false;
        }

        foreach ($entries as $field => $entryName) {
            $file = $request->file($field);
            if ($file instanceof UploadedFile && $file->isValid()) {
                $ext = $file->getClientOriginalExtension();
                $zip->addFile($file->getRealPath(), $entryName.($ext !== '' ? '.'.$ext : ''));
            }
        }
        $zip->close();

        return $relative;
    }

    /** Invio non bloccante, come il legacy. */
    protected function sendClaimMail(string $to, string $fromName, string $subject, string $body, string $zipPath): void
    {
        if ($to === '') {
            return;
        }

        try {
            Mail::to($to)->send(new LegacyHtmlMail(
                subjectLine: $subject,
                htmlBody: $body,
                fromName: $fromName,
                attachmentPath: $zipPath !== '' ? Storage::path($zipPath) : null,
            ));
        } catch (\Throwable) {
            // non bloccante
        }
    }
}
