# API v2 — endpoint SPECIALI (fuori contratto)

> Questo documento **non** è il contratto della API v2. Quello è
> `legacy/public_html/API_V2.md` e descrive i path che TUTTE le app parlano.
>
> Qui stanno le implementazioni concordate con **singole agenzie**: esistono solo
> se la relativa feature è accesa in `agenzie_speciale` (tab **Speciale** del
> pannello admin) e sono servite da `routes/api_v2_custom.php`. Nessuna app
> standard le chiama; nessun endpoint standard cambia comportamento per colpa
> loro.

---

## Come funziona il gate

1. Ogni feature speciale ha una colonna boolean in `agenzie_speciale`
   (una riga per agenzia, PK `id_agenzia`, creata al primo salvataggio).
2. L'admin la accende dalla tab **Speciale** del form agenzia.
3. L'app scopre cosa è attivo con `custom/features.php`.
4. Gli endpoint della feature rispondono **404 «Risorsa non trovata.»** a chi non
   è abilitato — corpo identico a quello di una route inesistente, così non è
   possibile dedurne l'esistenza.

`agenzie_speciale` e `consulenze` sono in `LegacyRowSanitizer::PROTECTED_TABLES`:
`hybrid:import-legacy` fa TRUNCATE delle tabelle che importa e le azzererebbe.

---

## `GET /res/api/v2/custom/features.php`

Feature attive per un'agenzia. Autenticazione come `agency.php` (token pubblico,
**no JWT**): l'app la chiama prima del login per sapere cosa disegnare.

**Query**: `id` (int, obbligatorio), `token` (string, obbligatorio).

**200**

```json
{
  "success": true,
  "data": {
    "consulenza": {
      "attiva": true,
      "titolo": "Richiedi una Consulenza",
      "testo": "Prenota un appuntamento in agenzia"
    }
  }
}
```

`attiva` è un **booleano JSON vero**, non la stringa `'1'` del contratto legacy:
questo endpoint è nuovo e non ha vincoli di retrocompatibilità.
L'email destinataria configurata in pannello **non viene esposta**.

**Errori**: `422` `VALIDATION_ERROR` (id/token mancanti) · `401` `UNAUTHORIZED`
(agenzia inesistente o token errato — indistinguibili, come in `agency.php`).

Agenzia senza riga in `agenzie_speciale` → tutti i flag `false`, **mai** un errore.

---

## `POST /res/api/v2/claims/consulenza.php`

Richiesta di consulenza con appuntamento. **Richiede `consulenza_attiva`.**
Stessi campi della richiesta di preventivo più data e ora dell'appuntamento.

**Auth**: `Authorization: Bearer <JWT>`. L'`agency_id` viene dai claims del
token, **non** dal payload.

**Body**: `multipart/form-data`

| parte | tipo | note |
|---|---|---|
| `data` | string (JSON) | payload, vedi sotto |
| `documentazione` | file | opzionale |
| `fronteDoc` | file | opzionale |
| `retroDoc` | file | opzionale |

Payload dentro `data`:

| campo | obbl. | formato / note |
|---|---|---|
| `nome` | – | |
| `cognome` | – | |
| `email` | **sì** | normalizzata (trim, lowercase, taglio dopo `\|`) |
| `telefono` | – | **persistito** (nei preventivi si perde) |
| `indirizzo` | – | **persistito** |
| `descrizione` | – | testo libero |
| `privacy` | – | truthy legacy (`1`, `true`, `si`, …) |
| `data_appuntamento` | **sì** | `YYYY-MM-DD` |
| `ora_appuntamento` | **sì** | `HH:MM` (24h) |

I due campi arrivano separati perché il date/time picker nativo di Android/iOS li
produce separati; il server li unisce in `consulenze.appuntamento_il`.

**201**

```json
{ "success": true, "data": { "message": "Richiesta di consulenza inviata con successo.", "id": 12 } }
```

**Errori**

| status | code | quando |
|---|---|---|
| `401` | – | JWT assente, scaduto o non valido (middleware `auth.jwt`) |
| `404` | `NOT_FOUND` | `Agenzia non trovata.` — agency_id del token inesistente |
| `404` | `NOT_FOUND` | `Risorsa non trovata.` — **feature non attiva per l'agenzia** |
| `422` | `VALIDATION_ERROR` | `data` mancante o JSON non valido |
| `422` | `VALIDATION_ERROR` | email vuota |
| `422` | `VALIDATION_ERROR` | data/ora mancanti, malformate o inesistenti (`2027-02-31`) |
| `422` | `VALIDATION_ERROR` | appuntamento nel passato |

**Effetti**

- riga in `consulenze` (tipi veri: boolean, datetime, timestamps);
- ZIP degli allegati in `storage/app/private/uploads/consulenze/`, entry
  `DOC-`/`FDOC-`/`RDOC-` + «Cognome Nome» (come i preventivi), **mai** servito via URL;
- email HTML all'agenzia con lo ZIP allegato e l'appuntamento in evidenza.
  Destinatario, in ordine: `agenzie_speciale.consulenza_mail` → override
  `hybrid.agency_mail_overrides.<id>.consulenza` → `denuncia_mail` → `quick_email`.
  L'invio è **non bloccante**: se l'SMTP fallisce la richiesta resta salvata e
  l'endpoint risponde comunque 201 (identico al comportamento legacy).

---

## Lato app

La build standard non conosce nulla di tutto questo. Per la consulenza serve una
build dedicata che:

1. chiami `custom/features.php` insieme ad `agency.php`;
2. se `consulenza.attiva`, disegni la card sopra i Servizi con
   `titolo`/`testo` (icona e colore hardcoded come le altre `SectionCard`:
   nella v2 le `*_immagine` del DB **non** vengono più usate);
3. apra un form uguale a quello del preventivo più un date/time picker nativo
   che riempie `data_appuntamento` e `ora_appuntamento`;
4. posti su `claims/consulenza.php`.

---

## Aggiungere una nuova feature speciale

1. Colonne in `agenzie_speciale` (flag boolean + eventuali testi) con una migration.
2. Voce in `AgencyAdminController::SPECIAL_FEATURES` — la tab Speciale si disegna
   da lì, il salvataggio segue le stesse chiavi.
3. Flag in `FeaturesController::show()` se l'app deve saperlo.
4. Endpoint in `routes/api_v2_custom.php` + controller in `ApiV2\Custom\`, con il
   gate a 404 come `ConsulenzaController`.
5. Tabelle nuove → `LegacyRowSanitizer::PROTECTED_TABLES` **e** cascata in
   `AgencyAdminController::destroy()`.
6. Documentala qui, **non** in `API_V2.md`.
