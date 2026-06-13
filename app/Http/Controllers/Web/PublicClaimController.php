<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\LegacyHtmlMail;
use App\Models\AgenziaNew;
use App\Models\Documento;
use App\Models\Preventivo;
use App\Models\Sinistro;
use App\Support\LegacyRowSanitizer;
use App\Support\LegacyText;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Form web pubblici per-agenzia e relativi handler:
 * /denuncia_sinistro.php?id, /preventivo.php?id, /documento.php?id
 * POST res/denunciasinistro.php, res/richiestapreventivo.php, res/caricadocumenti.php
 *
 * Differenze deliberate dal legacy (vedi critics.md):
 * - l'email destinataria viene letta dal DB, NON dal form;
 * - id AUTO_INCREMENT al posto del MAX(id)+1 senza lock;
 * - query parametrizzate (il legacy interpolava $_GET['id']).
 */
class PublicClaimController extends Controller
{
    // ─── Pagine (GET ?id=) ───────────────────────────────────────────────

    public function showSinistro(Request $request)
    {
        return view('public.denuncia_sinistro', ['agenzia' => $this->agencyOrAbort($request)]);
    }

    public function showPreventivo(Request $request)
    {
        return view('public.preventivo', ['agenzia' => $this->agencyOrAbort($request)]);
    }

    public function showDocumento(Request $request)
    {
        return view('public.documento', ['agenzia' => $this->agencyOrAbort($request)]);
    }

    // ─── POST res/denunciasinistro.php ───────────────────────────────────

    public function handleSinistro(Request $request)
    {
        $isAuto = $request->has('agenzia_id_auto');
        if (! $isAuto && ! $request->has('agenzia_id_nonauto')) {
            abort(403, 'Accesso Negato');
        }
        $suffix = $isAuto ? 'auto' : 'nonauto';

        $agency = AgenziaNew::find((int) $request->input("agenzia_id_$suffix"));
        if ($agency === null) {
            abort(404);
        }

        $nome = $request->input("cognome_denuncia_$suffix", '').' '.$request->input("primo_nome_denuncia_$suffix", '');
        $email = (string) $request->input("email_denuncia_$suffix", '');
        $descrizione = (string) $request->input("descrizione_denuncia_$suffix", '');
        $privacy = LegacyRowSanitizer::normalizeBool($request->input("checkbox_privacy_$suffix"));
        $now = now();
        $dataDenuncia = $now->format('Y-m-d H:i:s');   // storage canonico
        $dataDisplay = $now->format('d/m/Y');          // presentazione (corpo email)

        $sinistro = Sinistro::create([
            'id_agenzia' => $agency->id,
            'nome_denuncia' => $nome,
            'tipo_sinistro' => $suffix,                  // 'auto' / 'nonauto' come il legacy
            'email_denuncia' => $email,
            'documenti_denuncia' => '',
            'data_denuncia' => $dataDenuncia,
            'descrizione_denuncia' => $descrizione,
            'privacy_denuncia' => $privacy,
        ]);

        $groups = $isAuto
            ? ['CAI' => "cai_denuncia_$suffix", 'DOCUMENTI' => "documenti_denuncia_$suffix", 'IMMAGINI' => "immagini_denuncia_$suffix"]
            : ['DOCUMENTI' => "documenti_denuncia_$suffix", 'IMMAGINI' => "immagini_denuncia_$suffix"];

        $zipPath = $this->buildZip($request, 'sinistri', (int) $sinistro->id, $groups);
        $sinistro->documenti_denuncia = $zipPath ?: '';
        $sinistro->save();

        $body = '
        <h2><strong>NUOVA DENUNCIA DI SINISTRO</strong></h2>
        <p><strong>Denunciante</strong>: '.htmlspecialchars($nome).'</p>
        <p><strong>e-mail Denunciante</strong>: <a href="mailto:'.htmlspecialchars($email).'">'.htmlspecialchars($email).'</a></p>
        <p><strong>Tipo di Sinistro</strong>: '.$suffix.'</p>
        <p><strong>Data Denuncia</strong>: '.$dataDisplay.'</p>
        <p><strong>Descrizione del Sinistro</strong>: </p>
        <p>'.nl2br(htmlspecialchars($descrizione)).'</p>
        <p><strong>In allegato, la documentazione presentata dal denunciante.</strong></p>';

        $this->notifyAgency($agency, 'Nuova denuncia di Sinistro da '.$nome, $body, $zipPath);

        return view('public.esito', [
            'title' => 'Denuncia inoltrata!',
            'text' => 'La tua denuncia è stata inoltrata con successo.<br />Verrai ricontattato al più presto dalla tua agenzia.',
            'gif' => 'success.gif',
        ]);
    }

    // ─── POST res/richiestapreventivo.php ────────────────────────────────

    public function handlePreventivo(Request $request)
    {
        if (! $request->has('agenzia_id_preventivo')) {
            abort(403, 'Accesso Negato');
        }

        $agency = AgenziaNew::find((int) $request->input('agenzia_id_preventivo'));
        if ($agency === null) {
            abort(404);
        }

        $nome = $request->input('cognome_preventivo', '').' '.$request->input('primo_nome_preventivo', '');
        $email = (string) $request->input('email_preventivo', '');
        $descrizione = (string) $request->input('descrizione_preventivo', '');
        $privacy = LegacyRowSanitizer::normalizeBool($request->input('checkbox_privacy_preventivo'));
        $now = now();
        $dataDenuncia = $now->format('Y-m-d H:i:s');   // storage canonico
        $dataDisplay = $now->format('d/m/Y');          // presentazione (corpo email)

        $preventivo = Preventivo::create([
            'id_agenzia' => $agency->id,
            'nome_denuncia' => $nome,
            'email_denuncia' => $email,
            'documenti_denuncia' => '',
            'data_denuncia' => $dataDenuncia,
            'descrizione_denuncia' => $descrizione,
            'privacy_denuncia' => $privacy,
        ]);

        $zipPath = $this->buildZip($request, 'preventivi', (int) $preventivo->id, ['DOCUMENTI' => 'documenti_preventivo']);
        $preventivo->documenti_denuncia = $zipPath ?: '';
        $preventivo->save();

        $body = '
        <h2><strong>NUOVA RICHIESTA DI PREVENTIVO</strong></h2>
        <p><strong>Richiedente</strong>: '.htmlspecialchars($nome).'</p>
        <p><strong>e-mail Richiedente</strong>: <a href="mailto:'.htmlspecialchars($email).'">'.htmlspecialchars($email).'</a></p>
        <p><strong>Data Richiesta</strong>: '.$dataDisplay.'</p>
        <p><strong>Descrizione</strong>: </p>
        <p>'.nl2br(htmlspecialchars($descrizione)).'</p>
        <p><strong>In allegato, la documentazione presentata dal richiedente.</strong></p>';

        $this->notifyAgency($agency, 'Nuova Richiesta di Preventivo da '.$nome, $body, $zipPath);

        return view('public.esito', [
            'title' => 'Complimenti!',
            'text' => 'Hai inoltrato la tua richiesta di preventivo.<br />Verrai contattato al più presto da un nostro consulente!',
            'gif' => 'success.gif',
        ]);
    }

    // ─── POST res/caricadocumenti.php ────────────────────────────────────

    public function handleDocumento(Request $request)
    {
        if (! $request->has('agenzia_id_documenti')) {
            abort(403, 'Accesso Negato');
        }

        $agency = AgenziaNew::find((int) $request->input('agenzia_id_documenti'));
        if ($agency === null) {
            abort(404);
        }

        $nome = $request->input('cognome_documenti', '').' '.$request->input('primo_nome_documenti', '');
        $email = (string) $request->input('email_documenti', '');
        $descrizione = (string) $request->input('descrizione_documenti', '');
        $privacy = LegacyRowSanitizer::normalizeBool($request->input('checkbox_privacy_documenti'));
        $now = now();
        $dataDenuncia = $now->format('Y-m-d H:i:s');   // storage canonico
        $dataDisplay = $now->format('d/m/Y');          // presentazione (corpo email)

        $documento = Documento::create([
            'id_agenzia' => $agency->id,
            'nome_denuncia' => $nome,
            'email_denuncia' => $email,
            'documenti_denuncia' => '',
            'data_denuncia' => $dataDenuncia,
            'descrizione_denuncia' => $descrizione,
            'privacy_denuncia' => $privacy,
        ]);

        $zipPath = $this->buildZip($request, 'documenti', (int) $documento->id, ['DOCUMENTI' => 'documenti_documenti']);
        $documento->documenti_denuncia = $zipPath ?: '';
        $documento->save();

        $body = '
        <h2><strong>NUOVO DOCUMENTO CARICATO</strong></h2>
        <p><strong>Mittente</strong>: '.htmlspecialchars($nome).'</p>
        <p><strong>e-mail Mittente</strong>: <a href="mailto:'.htmlspecialchars($email).'">'.htmlspecialchars($email).'</a></p>
        <p><strong>Data Richiesta</strong>: '.$dataDisplay.'</p>
        <p><strong>Descrizione</strong>: </p>
        <p>'.nl2br(htmlspecialchars($descrizione)).'</p>
        <p><strong>In allegato, la documentazione presentata dal mittente.</strong></p>';

        $this->notifyAgency($agency, 'Nuovo Documento caricato da '.$nome, $body, $zipPath);

        return view('public.esito', [
            'title' => 'Complimenti!',
            'text' => 'Hai caricato con successo i tuoi documenti.<br />Verranno esaminati al più presto dalla tua agenzia.',
            'gif' => 'success.gif',
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function agencyOrAbort(Request $request): AgenziaNew
    {
        $id = $request->query('id');
        if ($id === null || $id === '') {
            abort(400, 'Inserisci un id valido');
        }

        return AgenziaNew::findOrFail((int) $id);
    }

    /**
     * ZIP in stile legacy: entry "PREFISSO-N.ext" per ogni gruppo di file.
     * Nome file: uploads/{subdir}/Ymd-{id}-documentazione.zip
     *
     * @param  array<string, string>  $groups  prefisso entry → nome campo file[]
     */
    private function buildZip(Request $request, string $subdir, int $id, array $groups): string|false
    {
        $relative = 'uploads/'.$subdir.'/'.date('Ymd').'-'.$id.'-documentazione.zip';
        $absolute = Storage::path($relative);

        $dir = dirname($absolute);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($absolute, ZipArchive::CREATE) !== true) {
            return false;
        }

        foreach ($groups as $prefix => $field) {
            $files = $request->file($field) ?? [];
            $i = 0;
            foreach ((array) $files as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $ext = $file->getClientOriginalExtension();
                    $zip->addFile($file->getRealPath(), $prefix.'-'.$i.($ext !== '' ? '.'.$ext : ''));
                    $i++;
                }
            }
        }
        $zip->close();

        return $relative;
    }

    /** Email all'agenzia: destinatario dal DB (fix vs legacy), non bloccante. */
    private function notifyAgency(AgenziaNew $agency, string $subject, string $body, string|false $zipPath): void
    {
        $to = LegacyText::normalizeEmail($agency->denuncia_mail ?: $agency->quick_email);
        if ($to === '') {
            return;
        }

        try {
            Mail::to($to)->send(new LegacyHtmlMail(
                subjectLine: $subject,
                htmlBody: $body,
                fromName: (string) $agency->nome_agenzia,
                attachmentPath: $zipPath ? Storage::path($zipPath) : null,
            ));
        } catch (\Throwable) {
            // non bloccante
        }
    }
}
