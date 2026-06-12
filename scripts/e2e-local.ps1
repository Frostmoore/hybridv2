# e2e-local.ps1 — Test end-to-end del server Laravel in locale (fase 10).
#
# Esercita via HTTP reale tutti i flussi: app white-label (API v2), pannello
# admin, pannello agencies, form pubblici e cron. Richiede:
#  - DB locale popolato (idealmente coi dati di produzione via import)
#  - MAIL_MAILER=log nel .env
#  - PHP in PATH
#
# Uso:  pwsh scripts/e2e-local.ps1
# Le fixtures (agenzia/admin/operatore E2E con chiavi OneSignal FINTE)
# vengono create da database/seeders/E2eSeeder.php.

$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

$port = 8123
$base = "http://127.0.0.1:$port"
$hostMain = 'hybridandgogsv2.test'
$hostAgencies = 'agencies.hybridandgogsv2.test'
$script:passed = 0
$script:failed = 0

function Check([string] $name, [bool] $condition, [string] $detail = '') {
    if ($condition) {
        $script:passed++
        Write-Host "  PASS  $name" -ForegroundColor Green
    } else {
        $script:failed++
        Write-Host "  FAIL  $name  $detail" -ForegroundColor Red
    }
}

function Tinker([string] $code) {
    (php artisan tinker --execute=$code) -join "`n"
}

function Post-NoRedirect($url, $headers, $session, $body) {
    # PS7 lancia un'eccezione quando il redirect supera il limite: il 302
    # post-form punta al dominio .test non risolvibile, quindi NON va seguito.
    try {
        Invoke-WebRequest $url -Method Post -Headers $headers -WebSession $session -Body $body -MaximumRedirection 0 -SkipHttpErrorCheck | Out-Null
    } catch {
        # il cookie di sessione è comunque stato impostato dalla risposta 302
    }
}

function Get-Csrf([string] $html) {
    if ($html -match 'name="_token"\s+value="([^"]+)"') { return $Matches[1] }
    if ($html -match "'X-CSRF-TOKEN':\s*`"([^`"]+)`"") { return $Matches[1] }
    return $null
}

# ─── Setup ───────────────────────────────────────────────────────────────────
Write-Host "== Setup ==" -ForegroundColor Cyan
php artisan db:seed --class=E2eSeeder --force | Out-Null
$agId = [int](Tinker "echo App\Models\AgenziaNew::where('nome_agenzia','E2E Test Agency')->value('id');")
Write-Host "  Agenzia E2E: id $agId"

# Pulizia residui di run precedenti
Tinker "App\Models\Cliente::where('username','like','e2e.user%')->delete(); App\Models\Notifica::where('titolo','like','E2E %')->delete(); echo 'ok';" | Out-Null
Clear-Content storage\logs\laravel.log -ErrorAction SilentlyContinue

$serve = Start-Process php -ArgumentList 'artisan', 'serve', "--port=$port" -PassThru -WindowStyle Hidden
Start-Sleep 3

try {

# ─── A. Flusso app white-label (API v2) ─────────────────────────────────────
Write-Host "== A. Flusso app (API v2) ==" -ForegroundColor Cyan

# 1. Config agenzia
$cfg = Invoke-RestMethod "$base/res/api/v2/agency.php?id=$agId&token=E2ETOKEN123"
Check 'config agenzia' ($cfg.success -and $cfg.data.nome_app -eq 'E2E Test App' -and $cfg.data.PSObject.Properties.Name.Count -eq 56)

# 2. Registrazione
$stamp = Get-Date -Format 'HHmmss'
$username = "e2e.user$stamp"
$cf = "E2E$stamp".PadRight(16, 'X')
$reg = Invoke-RestMethod "$base/res/api/v2/auth/register.php" -Method Post -ContentType 'application/json' -Body (@{
    agency_id = "$agId"; username = $username; password = 'E2eUserPass!1'
    email = "$username@example.test"; nome = 'E2e'; cognome = 'User'; cf = $cf
    telefono = '333'; datadinascita = '1990-01-01'; playerid = "player-$stamp"
    privacy1 = '1'; privacy2 = '1'; privacy3 = '0'; privacy4 = '0'
} | ConvertTo-Json)
Check 'registrazione 201' $reg.success

# 3. Email di attivazione finita nel log
$mailLog = Get-Content storage\logs\laravel.log -Raw
Check 'email attivazione inviata (log)' ($mailLog -match 'Attiva il tuo account' -and $mailLog -match 'attivacliente')
Check 'email avviso agenzia inviata (log)' ($mailLog -match 'Nuova registrazione su E2E Test Agency')

# 4. Attivazione via link (token dal DB, l'URL nel log è quoted-printable)
$userInfo = (Tinker "`$u = App\Models\Cliente::where('username','$username')->first(); echo `$u->id.'|'.`$u->activation_token.'|'.`$u->active;").Trim() -split '\|'
Check 'utente registrato inattivo' ($userInfo[2] -eq '0')
$act = Invoke-WebRequest "$base/attivacliente.php?a=$($userInfo[0])&b=$($userInfo[1])" -Headers @{Host = $hostMain }
Check 'attivazione account' ($act.Content -match 'Hai attivato con successo')

# 5. Login
$login = Invoke-RestMethod "$base/res/api/v2/auth/login.php" -Method Post -ContentType 'application/json' -Body (@{
    agency_id = "$agId"; username = $username; password = 'E2eUserPass!1'
} | ConvertTo-Json)
$tok = $login.data.token
Check 'login + token' ($login.success -and $tok.Length -gt 50)
$auth = @{ Authorization = "Bearer $tok" }

# 6. me + privacy
$me = Invoke-RestMethod "$base/res/api/v2/user/me.php" -Headers $auth
Check 'me' ($me.data.cf -eq $cf.ToUpper())
$priv = Invoke-RestMethod "$base/res/api/v2/user/privacy.php" -Method Patch -ContentType 'application/json' -Headers $auth -Body '{"privacy_id":"3","privacy_value":true}'
Check 'privacy update' ($priv.data.value -like '1|*')

# 7. Notifiche: general + index/read/single
$gene = Invoke-RestMethod "$base/res/api/v2/notifications/general.php?agency_id=$agId"
Check 'notifiche generali' ($gene.success -and ($gene.data | Where-Object titolo -eq 'E2E Notifica Generale'))
Tinker "App\Models\Notifica::create(['titolo'=>'E2E Privata','contenuto'=>'x','destinatari'=>'$username,','letta_da'=>'','agenziaid'=>$agId,'dataora'=>now()->format('Y-m-d H:i:s')]); echo 'ok';" | Out-Null
$noti = Invoke-RestMethod "$base/res/api/v2/notifications/index.php" -Headers $auth
Check 'notifiche index' ($noti.data.Count -eq 1 -and $noti.data[0].letta -eq $false)
$read = Invoke-RestMethod "$base/res/api/v2/notifications/read.php" -Method Post -ContentType 'application/json' -Headers $auth -Body (@{id = [int]$noti.data[0].id } | ConvertTo-Json)
$single = Invoke-RestMethod "$base/res/api/v2/notifications/single.php?id=$($noti.data[0].id)" -Headers $auth
Check 'notifica read+single' ($read.data.letta -and $single.data.letta)

# 8. Claim sinistro multipart (curl per il multipart)
$tmpImg = New-TemporaryFile
[IO.File]::WriteAllBytes($tmpImg, [byte[]](137, 80, 78, 71, 13, 10, 26, 10) + (1..64))
$dataJson = '{"option":1,"nome":"E2e","cognome":"User","email":"' + $username + '@example.test","dataSinistro":"2026-06-12","descrizione":"Sinistro e2e","privacy":"1"}'
$sin = curl.exe -s -X POST "$base/res/api/v2/claims/sinistro.php" -H "Authorization: Bearer $tok" -F "data=$dataJson" -F "fotoCAI=@$tmpImg;filename=cai.png" -F "fronteDoc=@$tmpImg;filename=fronte.png" | ConvertFrom-Json
Check 'claim sinistro 201' ($sin.success -and $sin.data.id -gt 0)
$zipCheck = (Tinker "`$s = App\Models\Sinistro::find($($sin.data.id)); echo (str_starts_with(`$s->documenti_denuncia,'uploads/sinistri/') ? 'okpath' : 'badpath').'|'.(file_exists(\Illuminate\Support\Facades\Storage::path(`$s->documenti_denuncia)) ? 'okfile' : 'nofile');").Trim()
Check 'claim: riga + zip su disco' ($zipCheck -eq 'okpath|okfile')
Check 'claim: email denuncia (log)' ((Get-Content storage\logs\laravel.log -Raw) -match 'Nuova denuncia sinistro da User E2e')

# 9. Polizze (seed polizza per il CF appena creato)
Tinker "App\Models\Polizza::updateOrCreate(['n_polizza'=>'E2EPOL1','id_agenzia'=>$agId],['cf'=>'$($cf.ToUpper())','compagnia'=>'E2E Assicurazioni','data_scadenza_titolo'=>'2026-12-31']); echo 'ok';" | Out-Null
$pol = Invoke-RestMethod "$base/res/api/v2/polizze/index.php" -Headers $auth
Check 'polizze index' ($pol.data.count -eq 1 -and $pol.data.polizze[0].compagnia -eq 'E2E Assicurazioni')

# 10. Forgot password + cambiapassword + nuovo login
$fp = Invoke-RestMethod "$base/res/api/v2/auth/forgot-password.php" -Method Post -ContentType 'application/json' -Body (@{agency_id = "$agId"; username = $username } | ConvertTo-Json)
Check 'forgot-password' $fp.success
Check 'email reset (log)' ((Get-Content storage\logs\laravel.log -Raw) -match 'Reimposta la tua password')

$session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
$cpPage = Invoke-WebRequest "$base/cambiapassword.php?a=$($userInfo[0])&b=$($userInfo[1])&c=$agId" -Headers @{Host = $hostMain } -WebSession $session
$csrf = Get-Csrf $cpPage.Content
Check 'pagina cambiapassword + csrf' ($cpPage.Content -match 'Reimpostazione Password' -and $csrf)
$jsonPayload = (@{id = $userInfo[0]; nuova_password = 'NuovaE2e!Pass1'; id_agenzia = "$agId"; token = $userInfo[1] } | ConvertTo-Json -Compress)
$uph = Invoke-WebRequest "$base/res/userpasswordhandler.php" -Method Post -Headers @{Host = $hostMain; 'X-CSRF-TOKEN' = $csrf } -WebSession $session -Body @{json = $jsonPayload }
Check 'userpasswordhandler success' ($uph.Content -eq 'success')
$login2 = Invoke-RestMethod "$base/res/api/v2/auth/login.php" -Method Post -ContentType 'application/json' -Body (@{agency_id = "$agId"; username = $username; password = 'NuovaE2e!Pass1' } | ConvertTo-Json)
Check 'login con nuova password' $login2.success

# ─── B. Pannello admin (dominio principale) ──────────────────────────────────
Write-Host "== B. Pannello admin ==" -ForegroundColor Cyan

$adm = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
$loginPage = Invoke-WebRequest "$base/index.html" -Headers @{Host = $hostMain } -WebSession $adm
$csrf = Get-Csrf $loginPage.Content
# I redirect post-login puntano al dominio .test (non risolvibile): non seguirli
Post-NoRedirect "$base/authenticate.php" @{Host = $hostMain } $adm @{_token = $csrf; nomeutente = 'e2e.admin'; password = 'E2ePass!1' }
$homePage = Invoke-WebRequest "$base/home.php" -Headers @{Host = $hostMain } -WebSession $adm
Check 'login admin → home' ($homePage.Content -match 'Agenzie' -and $homePage.Content -match 'E2E Test Agency')

$editPage = Invoke-WebRequest "$base/agenzia.php?id=$agId" -Headers @{Host = $hostMain } -WebSession $adm
$csrf = Get-Csrf $editPage.Content
Check 'pagina modifica agenzia' ($editPage.Content -match 'E2ETOKEN123')
Post-NoRedirect "$base/res/updateagenzia.php" @{Host = $hostMain } $adm @{_token = $csrf; id = $agId; quick_telefono = "06E2E$stamp" }
$tel = (Tinker "echo App\Models\AgenziaNew::find($agId)->quick_telefono;").Trim()
Check 'update agenzia persiste' ($tel -eq "06E2E$stamp")

$gatePage = Invoke-WebRequest "$base/import_polizze.php" -Headers @{Host = $hostMain } -WebSession $adm
$csrf = Get-Csrf $gatePage.Content
$importPw = (Tinker "echo config('hybrid.import_password') ?: 'NONCONFIG';").Trim()
if ($importPw -ne 'NONCONFIG' -and $importPw) {
    Post-NoRedirect "$base/import_polizze.php" @{Host = $hostMain } $adm @{_token = $csrf; pw = $importPw }
    $importPage = Invoke-WebRequest "$base/import_polizze.php" -Headers @{Host = $hostMain } -WebSession $adm
    Check 'import polizze: gate + token interni' ($importPage.Content -match 'Token interni' -and $importPage.Content -match 'E2EINTERNO456')
} else {
    Check 'import polizze: gate password' ($gatePage.Content -match 'Password')
}

$notifPage = Invoke-WebRequest "$base/notifiche.php" -Headers @{Host = $hostMain } -WebSession $adm
Check 'pagina notifiche admin' ($notifPage.Content -match 'Invia una Notifica')

# ─── C. Pannello agencies (sottodominio) ─────────────────────────────────────
Write-Host "== C. Pannello agencies ==" -ForegroundColor Cyan

$ops = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
$agLogin = Invoke-WebRequest "$base/login.php" -Headers @{Host = $hostAgencies } -WebSession $ops
$csrf = Get-Csrf $agLogin.Content
$logResp = Invoke-RestMethod "$base/api/v1/log.php" -Method Post -Headers @{Host = $hostAgencies; 'X-CSRF-TOKEN' = $csrf } -WebSession $ops -Body @{username = 'e2e.operatore'; password = 'E2ePass!1' }
Check 'login operatore' ($logResp.success -and $logResp.message -eq 'Login riuscito')

$utentiPage = Invoke-WebRequest "$base/utenti.php" -Headers @{Host = $hostAgencies } -WebSession $ops
Check 'utenti agenzia (vede il nuovo utente)' ($utentiPage.Content -match $username)

$csv = Invoke-WebRequest "$base/export_utenti.php" -Headers @{Host = $hostAgencies } -WebSession $ops
Check 'export CSV' ($csv.Headers.'Content-Type' -like 'text/csv*' -and $csv.Content -match 'Liberatoria' -and $csv.Content -match $username)

# Invio notifica a tutti: chiavi OneSignal FINTE → la chiamata vera fallisce,
# ma il flusso (validazioni + insert su notifiche/notifiche_generali) è esercitato
$prima = [int](Tinker "echo App\Models\NotificaGenerale::where('notifica_agid',$agId)->count();").Trim()
# Il login rigenera la sessione: serve il token CSRF fresco dalla pagina
$notifAllPage = Invoke-WebRequest "$base/new_notification_all.php" -Headers @{Host = $hostAgencies } -WebSession $ops
$csrf = Get-Csrf $notifAllPage.Content
$sendAll = Invoke-RestMethod "$base/api/v1/send_notification_all.php" -Method Post -Headers @{Host = $hostAgencies; 'X-CSRF-TOKEN' = $csrf } -WebSession $ops -Body @{agenziaid = $agId; titolo = "E2E Push $stamp"; testo = 'Test e2e'; notifica_scadenza = '2027-01-01 12:00:00' }
$dopo = [int](Tinker "echo App\Models\NotificaGenerale::where('notifica_agid',$agId)->count();").Trim()
Check 'send all: insert ok, push rifiutato da OneSignal (chiavi finte)' ($sendAll.success -eq $false -and $sendAll.message -match 'gestore notifiche' -and $dopo -eq $prima + 1)

# ─── D. Form pubblici e cron ─────────────────────────────────────────────────
Write-Host "== D. Form pubblici e cron ==" -ForegroundColor Cyan

$formPage = Invoke-WebRequest "$base/denuncia_sinistro.php?id=$agId" -Headers @{Host = $hostMain }
Check 'form pubblico sinistro' ($formPage.Content -match 'Denuncia il tuo Sinistro su E2E Test Agency')

php artisan hybrid:backup-db | Out-Null
Check 'backup-db' (Test-Path "storage\app\backups\backup_$(Get-Date -Format 'yyyy-MM-dd').json")

# Cron notifiche con scadenza al giorno sbagliato → 0 push, nessun contatto esterno
$wrongDay = (Get-Date).AddDays(3).ToString('yyyy-MM-dd')
New-Item -ItemType Directory -Force storage\app\scadenze | Out-Null
Set-Content "storage\app\scadenze\scadenze_$(Get-Date -Format 'yyyy-MM-dd').json" ('[{"cliente_id":' + $userInfo[0] + ',"id_polizza":"E2E1","data_effetto_titolo":"' + $wrongDay + '","os_app_id":"e2e-fake","os_api_key":"e2e-fake","playerid":"p"}]')
$cron = php artisan hybrid:scadenze-notifiche 2>&1 | Out-String
Check 'cron notifiche (skip giorno sbagliato)' ($cron -match 'Push inviate: 0')

} finally {
    Stop-Process $serve -Force -ErrorAction SilentlyContinue
    Remove-Item "storage\app\scadenze\scadenze_$(Get-Date -Format 'yyyy-MM-dd').json" -ErrorAction SilentlyContinue
}

# ─── Esito ───────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host ("E2E: {0} PASS, {1} FAIL" -f $script:passed, $script:failed) -ForegroundColor ($(if ($script:failed -eq 0) { 'Green' } else { 'Red' }))
exit $(if ($script:failed -eq 0) { 0 } else { 1 })
