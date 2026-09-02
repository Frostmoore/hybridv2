<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Toglie il `www.` dall'host con un redirect, PRIMA del routing.
 *
 * Perché serve: in bootstrap/app.php le pagine web stanno dentro
 * `Route::domain(config('hybrid.domain_main'))` e Laravel confronta l'host alla
 * lettera. Una richiesta a `www.<dominio>` non matcha nessun gruppo e finisce in
 * 404 — non "pagina mancante", ma "dominio non riconosciuto". Sul legacy Apache
 * serviva entrambi gli host senza vincoli, quindi il `www.` ha sempre
 * funzionato: le app white-label generate dal builder lo hanno cablato dentro
 * (constants.template.dart) e sulla v2 mandavano gli utenti su un 404 aprendo
 * delete_account.php — URL dichiarato agli store, quindi un problema di
 * compliance. Stesso buco su cambiapassword.php, attivacliente.php e i form
 * pubblici linkati nelle email.
 *
 * Perché le route API sono escluse: `routes/api_v2.php` è registrato SENZA
 * vincolo di dominio, quindi risponde già correttamente su `www.` e le app in
 * circolazione la chiamano lì. Redirigerle sarebbe una regressione: i client
 * HTTP non seguono i redirect sulle POST in modo affidabile (molti degradano a
 * GET e perdono il body), e romperemmo login e refresh su app che oggi
 * funzionano. Si tocca solo ciò che oggi è già rotto.
 *
 * Il redirect vale solo se togliendo il `www.` si ottiene uno dei domini
 * configurati: su un host sconosciuto la richiesta passa intatta e il router
 * decide come sempre.
 *
 * Nota: lo schema viene letto dalla richiesta. Se un giorno si mette un reverse
 * proxy davanti ad Apache (oggi termina lui il TLS) va configurato
 * `$middleware->trustProxies()`, altrimenti il redirect punta a http.
 */
class RedirectWwwToApex
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if (! str_starts_with($host, 'www.')) {
            return $next($request);
        }

        $apex = substr($host, 4);

        $domains = array_map(
            static fn ($d) => strtolower((string) $d),
            array_filter([config('hybrid.domain_main'), config('hybrid.domain_agencies')]),
        );

        if (! in_array($apex, $domains, true) || $request->is('res/api/*')) {
            return $next($request);
        }

        $port = $request->getPort();
        $suffix = in_array((int) $port, [80, 443], true) ? '' : ':'.$port;

        $target = $request->getScheme().'://'.$apex.$suffix.$request->getRequestUri();

        // 308 sulle scritture: il 301 farebbe degradare la POST a GET.
        $status = in_array($request->getMethod(), ['GET', 'HEAD'], true) ? 301 : 308;

        return redirect()->away($target, $status);
    }
}
