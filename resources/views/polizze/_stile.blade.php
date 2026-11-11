{{-- Stile delle pagine di import polizze (passo 1 e passo 2).
     Sta qui e non in admin.css perche' e' tutto roba di queste due schermate:
     metterlo nel foglio comune vorrebbe dire rischiare regressioni sulle altre
     pagine del pannello per niente. I nomi cominciano tutti per `pol-`. --}}
<style>
    .pol-steps {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        margin: 0 0 28px;
        padding: 16px 22px;
        background: var(--adm-surface);
        border: 1px solid var(--adm-border);
        border-radius: 14px;
        box-shadow: var(--adm-shadow);
    }

    .pol-step {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--adm-muted);
        font-weight: 600;
        font-size: .92rem;
    }

    .pol-step__num {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #eef1f8;
        color: var(--adm-muted);
        display: grid;
        place-items: center;
        font-size: .86rem;
        font-weight: 700;
    }

    .pol-step.is-active { color: var(--adm-ink); }
    .pol-step.is-active .pol-step__num { background: var(--adm-primary); color: #fff; }
    .pol-step.is-done .pol-step__num { background: var(--adm-success); color: #fff; }

    .pol-step__bar {
        flex: 1 1 40px;
        height: 2px;
        background: var(--adm-border);
        min-width: 24px;
    }

    /* Due colonne: a sinistra si fa, a destra si capisce. Sotto i 1000px
       diventano una colonna sola e l'aiuto scivola in fondo. */
    .pol-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.55fr) minmax(290px, 1fr);
        gap: 24px;
        align-items: start;
    }

    @media (max-width: 1000px) {
        .pol-layout { grid-template-columns: 1fr; }
    }

    .pol-card {
        background: var(--adm-surface);
        border: 1px solid var(--adm-border);
        border-radius: 16px;
        box-shadow: var(--adm-shadow);
        padding: 30px 32px 32px;
    }

    .pol-card + .pol-card { margin-top: 24px; }

    .pol-card h2 {
        margin: 0 0 6px;
        font-size: 1.2rem;
        font-weight: 700;
        letter-spacing: -.01em;
    }

    .pol-card__intro {
        margin: 0 0 26px;
        color: var(--adm-muted);
        font-size: .93rem;
        line-height: 1.6;
        max-width: 62ch;
    }

    .pol-label {
        display: block;
        font-size: .76rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--adm-muted);
        margin: 0 0 10px;
    }

    /* Area di caricamento: l'input vero e' nascosto dentro la label, cosi' si
       puo' cliccare ovunque nel riquadro e il nome del file scelto compare
       grande invece che nel testo minuscolo del controllo nativo. */
    .pol-drop {
        display: block;
        border: 2px dashed #ccd3e6;
        border-radius: 14px;
        background: #fbfcff;
        padding: 34px 24px;
        text-align: center;
        cursor: pointer;
        position: relative;
        transition: border-color .15s, background .15s;
    }

    .pol-drop:hover,
    .pol-drop.is-hover { border-color: var(--adm-primary); background: #f4f7ff; }
    .pol-drop.is-filled { border-style: solid; border-color: var(--adm-success); background: #f5fbf8; }
    /* Invisibile ma RESO: con display:none Chrome non sa dove ancorare il
       messaggio di campo obbligatorio e blocca l'invio senza dire niente
       ("An invalid form control is not focusable"). */
    .pol-drop input {
        position: absolute;
        left: 50%;
        bottom: 12px;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .pol-drop__icon {
        width: 54px;
        height: 54px;
        margin: 0 auto 14px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: #e9eeff;
        color: var(--adm-primary);
        font-size: 1.3rem;
    }

    .pol-drop.is-filled .pol-drop__icon { background: #dff2e9; color: var(--adm-success); }

    .pol-drop__main { font-weight: 700; font-size: 1rem; color: var(--adm-ink); }
    .pol-drop__main u { text-decoration-color: var(--adm-primary); text-underline-offset: 3px; }

    .pol-drop__sub {
        margin-top: 7px;
        color: var(--adm-muted);
        font-size: .85rem;
    }

    .pol-modes {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 14px;
    }

    .pol-mode {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        border: 1.5px solid var(--adm-border);
        border-radius: 13px;
        padding: 16px 18px;
        cursor: pointer;
        background: var(--adm-surface);
        transition: border-color .15s, background .15s, box-shadow .15s;
    }

    .pol-mode:hover { border-color: #b9c3e0; }

    .pol-mode.is-on {
        border-color: var(--adm-primary);
        background: #f6f8ff;
        box-shadow: 0 0 0 3px rgba(59, 91, 219, .1);
    }

    .pol-mode input { margin-top: 3px; accent-color: var(--adm-primary); }
    .pol-mode strong { display: block; font-size: .95rem; margin-bottom: 3px; }
    .pol-mode small { color: var(--adm-muted); font-size: .82rem; line-height: 1.5; }
    .pol-mode.is-danger.is-on { border-color: var(--adm-danger); background: #fff6f7; box-shadow: 0 0 0 3px rgba(216, 69, 95, .09); }

    .pol-actions {
        margin-top: 28px;
        padding-top: 22px;
        border-top: 1px solid var(--adm-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .pol-actions__note { color: var(--adm-muted); font-size: .84rem; margin: 0; }

    .pol-btn {
        border: 0;
        border-radius: 11px;
        padding: 13px 26px;
        font-size: .95rem;
        font-weight: 700;
        cursor: pointer;
        background: var(--adm-primary);
        color: #fff;
        display: inline-flex;
        align-items: center;
        gap: 9px;
        white-space: nowrap;
        box-shadow: 0 6px 16px rgba(59, 91, 219, .25);
        transition: background .15s, transform .08s;
    }

    .pol-btn:hover { background: var(--adm-primary-d); }
    .pol-btn:active { transform: translateY(1px); }

    /* Colonna di destra */
    .pol-aside { position: sticky; top: 84px; }

    .pol-aside h3 {
        margin: 0 0 14px;
        font-size: 1rem;
        font-weight: 700;
    }

    .pol-need {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        padding: 13px 0;
        border-bottom: 1px solid var(--adm-border);
    }

    .pol-need:last-of-type { border-bottom: 0; }

    .pol-need__dot {
        flex: 0 0 auto;
        width: 24px;
        height: 24px;
        border-radius: 7px;
        display: grid;
        place-items: center;
        font-size: .72rem;
        background: #e9eeff;
        color: var(--adm-primary);
    }

    .pol-need strong { display: block; font-size: .9rem; margin-bottom: 2px; }
    .pol-need span { color: var(--adm-muted); font-size: .83rem; line-height: 1.55; }

    .pol-chips { display: flex; flex-wrap: wrap; gap: 7px; margin: 12px 0 0; }

    .pol-chip {
        background: #f1f3f9;
        color: #4b5468;
        border-radius: 7px;
        padding: 5px 10px;
        font-size: .79rem;
        font-weight: 600;
    }

    .pol-tip {
        margin: 18px 0 0;
        padding: 14px 16px;
        border-radius: 11px;
        background: #fbfaf2;
        border: 1px solid #ece6cf;
        color: #6a5f33;
        font-size: .84rem;
        line-height: 1.6;
    }

    .pol-stat {
        display: flex;
        align-items: baseline;
        gap: 10px;
        background: var(--adm-surface);
        border: 1px solid var(--adm-border);
        border-radius: 12px;
        padding: 12px 20px;
        box-shadow: var(--adm-shadow);
    }

    .pol-stat b { font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; }
    .pol-stat span { color: var(--adm-muted); font-size: .87rem; }

    /* ── Passo 2: abbinamento delle colonne ──────────────────────────────── */

    /* Una riga per colonna del file, non una tabella: con la tendina dentro una
       cella la riga si stringe e diventa illeggibile. Qui le tre parti hanno
       larghezze dichiarate e sotto gli 880px si impilano. */
    .pol-maphead,
    .pol-maprow {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr) minmax(230px, .95fr);
        gap: 22px;
        align-items: center;
    }

    .pol-maphead {
        padding: 0 0 12px;
        border-bottom: 2px solid var(--adm-border);
        font-size: .74rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--adm-muted);
    }

    .pol-maprow {
        padding: 18px 0;
        border-bottom: 1px solid var(--adm-border);
    }

    .pol-maprow:last-child { border-bottom: 0; }
    .pol-maprow.is-dup { background: #fff6f7; box-shadow: 0 0 0 10px #fff6f7; border-radius: 4px; }

    .pol-colname { font-weight: 700; font-size: .95rem; word-break: break-word; }
    .pol-colname em { display: block; font-weight: 500; font-size: .78rem; color: var(--adm-muted); font-style: normal; margin-top: 3px; }

    .pol-sample { display: flex; flex-direction: column; gap: 5px; min-width: 0; }

    .pol-sample span {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: .8rem;
        color: #4b5468;
        background: #f5f7fb;
        border-radius: 6px;
        padding: 4px 9px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pol-sample span.is-empty { color: #9aa1b1; font-family: inherit; font-style: italic; }

    .pol-select {
        width: 100%;
        border: 1.5px solid var(--adm-border);
        border-radius: 10px;
        padding: 11px 13px;
        font-size: .9rem;
        background: var(--adm-surface);
        color: var(--adm-ink);
        cursor: pointer;
        transition: border-color .15s, box-shadow .15s;
    }

    .pol-select:focus { outline: 0; border-color: var(--adm-primary); box-shadow: 0 0 0 3px rgba(59, 91, 219, .12); }
    .pol-select.is-set { border-color: var(--adm-primary); background: #f8f9ff; font-weight: 600; }
    .pol-select.is-dup { border-color: var(--adm-danger); box-shadow: 0 0 0 3px rgba(216, 69, 95, .12); }

    @media (max-width: 880px) {
        .pol-maphead { display: none; }

        .pol-maprow {
            grid-template-columns: 1fr;
            gap: 10px;
            padding: 20px 0;
        }
    }

    /* Barra delle azioni appiccicata in fondo: con molte colonne il bottone
       "Importa" finirebbe fuori schermo e si dovrebbe scorrere per trovarlo. */
    .pol-bar {
        position: sticky;
        bottom: 0;
        z-index: 5;
        margin-top: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
        background: var(--adm-surface);
        border: 1px solid var(--adm-border);
        border-radius: 14px;
        box-shadow: 0 -2px 18px rgba(16, 24, 40, .07);
        padding: 16px 22px;
    }

    .pol-check {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        cursor: pointer;
        max-width: 60ch;
    }

    .pol-check input { margin-top: 3px; accent-color: var(--adm-primary); }
    .pol-check strong { display: block; font-size: .9rem; }
    .pol-check small { color: var(--adm-muted); font-size: .81rem; line-height: 1.5; }

    .pol-btn--ghost {
        background: transparent;
        color: var(--adm-muted);
        box-shadow: none;
        border: 1.5px solid var(--adm-border);
        font-weight: 600;
    }

    .pol-btn--ghost:hover { background: #f4f6fb; color: var(--adm-ink); }

    .pol-filebar {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        background: var(--adm-surface);
        border: 1px solid var(--adm-border);
        border-radius: 13px;
        box-shadow: var(--adm-shadow);
        padding: 15px 20px;
        margin-bottom: 24px;
    }

    .pol-filebar__name { font-weight: 700; font-size: .98rem; }
    .pol-filebar__meta { color: var(--adm-muted); font-size: .86rem; }

    .pol-tag {
        border-radius: 999px;
        padding: 5px 13px;
        font-size: .78rem;
        font-weight: 700;
    }

    .pol-tag--ok { background: #e7f5ee; color: #1f6b4c; }
    .pol-tag--warn { background: #fdeef0; color: #a32f44; }

    /* Avvisi dal vivo sulla mappatura */
    .pol-avviso {
        border-radius: 12px;
        padding: 14px 18px;
        margin-bottom: 22px;
        font-size: .89rem;
        line-height: 1.6;
        border: 1px solid transparent;
    }

    .pol-avviso--ok { background: rgba(47, 158, 111, .09); border-color: rgba(47, 158, 111, .25); color: #1f6b4c; }
    .pol-avviso--err { background: rgba(216, 69, 95, .07); border-color: rgba(216, 69, 95, .25); color: #a32f44; }

    /* ── Resoconto dell'import ───────────────────────────────────────────── */
    .pol-esito {
        border-radius: 14px;
        padding: 20px 24px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        font-size: .95rem;
        line-height: 1.6;
    }

    .pol-esito--ok { background: rgba(47, 158, 111, .09); border-color: rgba(47, 158, 111, .25); color: #1f6b4c; }
    .pol-esito--err { background: rgba(216, 69, 95, .07); border-color: rgba(216, 69, 95, .25); color: #a32f44; }
    .pol-esito strong { font-size: 1.05rem; }

    .pol-tiles {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(215px, 1fr));
        gap: 14px;
    }

    .pol-tile {
        border: 1px solid var(--adm-border);
        border-radius: 12px;
        padding: 16px 18px;
        background: #fbfcff;
    }

    .pol-tile b { display: block; font-size: 1.6rem; font-weight: 800; letter-spacing: -.02em; line-height: 1.1; }
    .pol-tile span { display: block; margin-top: 5px; color: var(--adm-muted); font-size: .83rem; line-height: 1.45; }
    .pol-tile--ok b { color: var(--adm-success); }
    .pol-tile--warn b { color: #b4802a; }

    .pol-usate { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }

    .pol-usata {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: #f5f7fb;
        border-radius: 9px;
        padding: 7px 12px;
        font-size: .83rem;
    }

    .pol-usata i { color: #9aa1b1; font-size: .7rem; }
    .pol-usata b { font-weight: 700; }

    .pol-hint {
        margin: 9px 0 0;
        color: var(--adm-muted);
        font-size: .83rem;
        line-height: 1.55;
    }
</style>
