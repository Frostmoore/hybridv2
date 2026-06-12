<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agencies;

use App\Http\Controllers\Controller;
use App\Models\AgenziaNew;
use App\Models\Cliente;
use App\Models\Operatore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pagine del pannello agencies: home/user_panel, utenti, export CSV,
 * operators (solo super-admin).
 */
class AgenciesPanelController extends Controller
{
    public function index()
    {
        return Auth::guard('operatore')->check()
            ? redirect('home.php')
            : redirect('login.php');
    }

    public function home()
    {
        return view('agencies.home', [
            'operatore' => Auth::guard('operatore')->user(),
        ]);
    }

    // ─── utenti.php ──────────────────────────────────────────────────────

    public function utenti()
    {
        /** @var Operatore $operatore */
        $operatore = Auth::guard('operatore')->user();

        $clienti = Cliente::where('agenziaid', $operatore->agid)
            ->orderBy('id')
            ->get(['id', 'username', 'email', 'telefono', 'nome', 'cognome', 'cf',
                'datadinascita', 'firstlogin', 'lastlogin', 'active', 'playerid']);

        return view('agencies.utenti', ['clienti' => $clienti, 'operatore' => $operatore]);
    }

    // ─── export_utenti.php ───────────────────────────────────────────────

    public function exportUtenti(): StreamedResponse
    {
        /** @var Operatore $operatore */
        $operatore = Auth::guard('operatore')->user();
        $agid = (int) $operatore->agid;

        return response()->streamDownload(function () use ($agid) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 per Excel, come il legacy
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID', 'Username', 'Email', 'Telefono',
                'Nome', 'Cognome', 'CF', 'Data di Nascita',
                'Primo Accesso', 'Ultimo Accesso', 'Stato',
                'Liberatoria', 'Informazione', 'Comun. a Terzi', 'Profilazione',
            ], ';');

            Cliente::where('agenziaid', $agid)->orderBy('id')
                ->chunk(500, function ($clienti) use ($out) {
                    foreach ($clienti as $c) {
                        fputcsv($out, [
                            $c->id, $c->username, $c->email, $c->telefono,
                            $c->nome, $c->cognome, $c->cf, $c->datadinascita,
                            $c->firstlogin, $c->lastlogin,
                            (string) $c->active === '1' ? 'Attivo' : 'Disattivato',
                            self::formatPrivacy($c->privacyuno),
                            self::formatPrivacy($c->privacydue),
                            self::formatPrivacy($c->privacytre),
                            self::formatPrivacy($c->privacyquattro),
                        ], ';');
                    }
                });

            fclose($out);
        }, 'utenti_agenzia_'.$agid.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** formatPrivacy legacy: "Accettato - <ts>" / "Rifiutato - <ts>" / ''. */
    public static function formatPrivacy(?string $val): string
    {
        if (! $val || ! str_contains($val, '|')) {
            return '';
        }
        [$flag, $datetime] = explode('|', $val, 2);
        $esito = $flag === '1' ? 'Accettato' : 'Rifiutato';

        return $datetime ? "$esito - $datetime" : $esito;
    }

    // ─── operators.php (solo super-admin) ────────────────────────────────

    public function operators()
    {
        /** @var Operatore $operatore */
        $operatore = Auth::guard('operatore')->user();
        if (! in_array($operatore->username, config('hybrid.agencies_superadmins'), true)) {
            return redirect('home.php');   // come il legacy
        }

        return view('agencies.operators', [
            'operatori' => Operatore::orderBy('id')->get(),
            'agenzie' => AgenziaNew::orderBy('nome_agenzia')->get(['id', 'nome_agenzia']),
        ]);
    }

    public function addOperator()
    {
        return view('agencies.addoperator');
    }

    public function register()
    {
        return view('agencies.register');
    }
}
