<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AgenziaNew;
use App\Support\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * Gestione agenzie del pannello admin:
 * home.php (griglia), agenzia.php?id (modifica) + POST res/updateagenzia.php,
 * creagenzia.php (creazione) + POST res/nuovagenzia.php.
 *
 * Le immagini vanno in storage/app/agency-assets/img/<id>/<campo>.png e nel DB
 * resta il path relativo legacy "img/<id>/<campo>.png" (servito da /res/...).
 */
class AgencyAdminController extends Controller
{
    /** Campi testuali editabili, raggruppati per sezione (per le viste). */
    public const FIELD_GROUPS = [
        'Identità app' => ['nome_app', 'nome_agenzia', 'colori', 'attiva', 'codiceagenzia', 'privacy_agenzia', 'denuncia_mail'],
        'Social' => ['facebook_agenzia', 'instagram_agenzia', 'linkedin_agenzia', 'google_agenzia', 'sito_agenzia'],
        'Info e sedi (campi multipli separati da |)' => ['info_titolo', 'info_nomi_sedi', 'info_indirizzi_sedi', 'info_testo_orari', 'info_orari_sedi', 'info_recensioni_sedi', 'info_telefono_sedi', 'info_email_sedi', 'info_mappa_sedi', 'info_sito_sedi'],
        'Notifica vetrina' => ['notifica_titolo', 'notifica_testo', 'notifica_link'],
        'Contatti e numeri utili (voci "Label.Numero" separate da |)' => ['contatti_titolo', 'numeri_utili_labels', 'numeri_utili_colori', 'numeri_utili_salute', 'numeri_utili_assistenza', 'numeri_utili_noleggio'],
        'Sezione Sinistro' => ['denuncia_titolo', 'denuncia_testo_grassetto'],
        'Sezione Preventivo' => ['preventivo_titolo', 'preventivo_testo_grassetto'],
        'Sezione Documenti' => ['documento_titolo', 'documento_testo_grassetto'],
        'Contatti rapidi' => ['quick_telefono', 'quick_whatsapp', 'quick_email'],
        'Servizi esterni' => ['assisecret', 'assiurl', 'sintesi_token', 'sintesi_lic', 'sintesi_azi', 'sintesi_age', 'os_app_id', 'os_api_key', 'token_interno'],
    ];

    /** Campi immagine PNG (nome campo = nome file come il legacy). */
    public const IMAGE_FIELDS = [
        'logo_agenzia', 'header_agenzia', 'info_immagine', 'contatti_immagine',
        'denuncia_immagine', 'preventivo_immagine', 'documento_immagine',
    ];

    // ─── GET home.php ────────────────────────────────────────────────────

    public function home()
    {
        return view('admin.home', ['agenzie' => AgenziaNew::orderBy('id')->get()]);
    }

    // ─── GET /agenzia/{id} (edit) ────────────────────────────────────────

    public function edit(int $id)
    {
        $agenzia = AgenziaNew::findOrFail($id);

        return view('admin.agenzia_form', [
            'agenzia' => $agenzia,
            'action' => 'agenzia/'.$agenzia->id,
            'titolo' => 'Modifica '.$agenzia->nome_agenzia,
        ]);
    }

    // ─── POST /agenzia/{id} (update; alias res/updateagenzia.php) ─────────

    public function update(Request $request, ?int $id = null)
    {
        // id dal route URL pulito, oppure dal body (alias legacy res/updateagenzia.php)
        $agenzia = AgenziaNew::findOrFail($id ?? (int) $request->input('id', 0));

        $agenzia->fill($this->textFields($request));
        $this->saveImages($request, $agenzia);
        $agenzia->save();

        return redirect('agenzia/'.$agenzia->id)->with('status', 'Agenzia aggiornata con successo.');
    }

    // ─── GET /agenzia/nuova (create) ─────────────────────────────────────

    public function create()
    {
        return view('admin.agenzia_form', [
            'agenzia' => new AgenziaNew,
            'action' => 'agenzia',
            'titolo' => 'Nuova Agenzia',
        ]);
    }

    // ─── POST /agenzia (store; alias res/nuovagenzia.php) ─────────────────

    public function store(Request $request)
    {
        // Il logo è obbligatorio, come nel legacy
        if (! $request->hasFile('logo_agenzia')) {
            return back()->withErrors('Non è stato caricato alcun file nel campo Logo. Il Logo è un elemento obbligatorio.')->withInput();
        }

        $agenzia = new AgenziaNew($this->textFields($request));
        $agenzia->token = $this->generateToken();     // 10 char alfanumerici (legacy)
        if ((string) $agenzia->attiva === '') {
            $agenzia->attiva = '1';
        }
        $agenzia->save();                              // serve l'id per la cartella img/<id>/

        $this->saveImages($request, $agenzia);
        $agenzia->save();

        return redirect('home')->with('status', 'Agenzia "'.$agenzia->nome_agenzia.'" creata con ID '.$agenzia->id.'.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function textFields(Request $request): array
    {
        $data = [];
        foreach (self::FIELD_GROUPS as $fields) {
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $data[$field] = (string) $request->input($field, '');
                }
            }
        }

        return $data;
    }

    /** Salva i PNG caricati e aggiorna i path relativi sul model. */
    private function saveImages(Request $request, AgenziaNew $agenzia): void
    {
        $dir = config('hybrid.agency_assets_path').'/img/'.$agenzia->id;

        foreach (self::IMAGE_FIELDS as $field) {
            $file = $request->file($field);
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            // Solo PNG, come il legacy
            if (! in_array($file->getMimeType(), ['image/png', 'image/x-png'], true)) {
                continue;
            }
            File::ensureDirectoryExists($dir);
            $dest = $dir.'/'.$field.'.png';

            if ($field === 'header_agenzia') {
                // La testata si auto-comprime in upload (resize a max 1200 + JPEG):
                // i background mobili da più MB sono inutili e pesanti.
                ImageOptimizer::optimize($file->getRealPath(), $dest);
            } else {
                $file->move($dir, $field.'.png');
            }

            $agenzia->{$field} = 'img/'.$agenzia->id.'/'.$field.'.png';
        }
    }

    /** generateRandomString() del legacy: 10 char [0-9a-zA-Z]. */
    private function generateToken(int $length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $token;
    }
}
