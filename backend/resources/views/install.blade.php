<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Installation — LG-IT</title>
    <style>
        :root {
            --text: #16212e; --muted: #4b5a6b; --bg: #f4f6f8; --surface: #fff; --border: #dee3e8;
            --accent: #14568f; --accent-hover: #0b3550; --accent-fg: #fff;
            --ok: #0e8f79; --ok-bg: #e7f5f2; --warn: #9a6200; --warn-bg: #fff4dc;
            --danger: #c0392b; --danger-bg: #fcebe9; --deep: #0b3550;
            --radius: 10px;
            font: 15px/1.55 Manrope, system-ui, 'Segoe UI', Roboto, sans-serif;
            color: var(--text); background: var(--bg);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --text: #e7ebef; --muted: #a9b4bf; --bg: #0f1620; --surface: #16212e; --border: #263241;
                --accent: #5da0d6; --accent-hover: #82b8e0; --accent-fg: #0f1620;
                --ok: #35c2a4; --ok-bg: #123028; --warn: #f0b64a; --warn-bg: #33270e;
                --danger: #ef7b6e; --danger-bg: #3a1b18;
            }
        }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        body { margin: 0; background: var(--bg); min-height: 100vh; }
        header { background: var(--deep); color: #fff; padding: 20px 16px; }
        header .inner, main { max-width: 980px; margin: 0 auto; }
        header h1 { margin: 0; font-size: 20px; }
        header p { margin: 4px 0 0; opacity: .8; font-size: 14px; }
        main { display: grid; grid-template-columns: 220px 1fr; gap: 24px; padding: 24px 16px 64px; }
        @media (max-width: 760px) { main { grid-template-columns: 1fr; } nav ol { display: flex; overflow-x: auto; gap: 4px; } nav li span.label { display: none; } }
        nav ol { list-style: none; margin: 0; padding: 0; position: sticky; top: 16px; }
        nav li { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; color: var(--muted); font-weight: 600; font-size: 14px; }
        nav li .num { flex: none; width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; border: 2px solid var(--border); font-size: 13px; }
        nav li.current { background: var(--surface); color: var(--text); box-shadow: 0 1px 2px rgba(11,53,80,.08); }
        nav li.current .num { border-color: var(--accent); color: var(--accent); }
        nav li.done .num { background: var(--ok); border-color: var(--ok); color: #fff; }
        section.step { display: none; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; }
        section.step.active { display: block; }
        h2 { margin: 0 0 4px; font-size: 19px; }
        .lead { margin: 0 0 20px; color: var(--muted); }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; }
        .grid .full { grid-column: 1 / -1; }
        @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }
        label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 4px; }
        .hint { display: block; font-weight: 400; color: var(--muted); font-size: 12.5px; margin-top: 4px; }
        input, select { width: 100%; padding: 9px 11px; font: inherit; color: var(--text); background: var(--bg); border: 1px solid var(--border); border-radius: 8px; }
        input:focus, select:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
        input.invalid { border-color: var(--danger); }
        .field-error { color: var(--danger); font-size: 12.5px; margin-top: 4px; }
        .actions { display: flex; gap: 10px; justify-content: space-between; align-items: center; margin-top: 24px; flex-wrap: wrap; }
        .actions .right { display: flex; gap: 10px; margin-left: auto; }
        button { font: inherit; font-weight: 600; padding: 9px 18px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface); color: var(--text); cursor: pointer; }
        button.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-fg); }
        button.primary:hover:not(:disabled) { background: var(--accent-hover); }
        button:disabled { opacity: .55; cursor: not-allowed; }
        .notice { padding: 10px 14px; border-radius: 8px; margin-top: 14px; font-size: 14px; border: 1px solid transparent; }
        .notice.ok { background: var(--ok-bg); color: var(--ok); border-color: color-mix(in srgb, var(--ok) 30%, transparent); }
        .notice.warn { background: var(--warn-bg); color: var(--warn); border-color: color-mix(in srgb, var(--warn) 30%, transparent); }
        .notice.error { background: var(--danger-bg); color: var(--danger); border-color: color-mix(in srgb, var(--danger) 30%, transparent); }
        .checks { list-style: none; margin: 0; padding: 0; border: 1px solid var(--border); border-radius: 8px; }
        .checks li { display: flex; gap: 12px; padding: 10px 14px; border-top: 1px solid var(--border); }
        .checks li:first-child { border-top: 0; }
        .checks .icon, .progress .icon { flex: none; width: 22px; height: 22px; border-radius: 50%; display: grid; place-items: center; font-size: 13px; font-weight: 700; color: #fff; }
        .icon.ok { background: var(--ok); } .icon.warn { background: #d99a1b; } .icon.error { background: var(--danger); }
        .checks .detail { color: var(--muted); font-size: 13px; overflow-wrap: anywhere; }
        .radio-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .radio-row label { flex: 1; min-width: 180px; display: flex; gap: 10px; align-items: flex-start; border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; cursor: pointer; font-weight: 400; }
        .radio-row input { width: auto; margin-top: 3px; }
        .radio-row label:has(input:checked) { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 7%, transparent); }
        .recap { width: 100%; border-collapse: collapse; font-size: 14px; }
        .recap th, .recap td { text-align: left; padding: 7px 0; border-bottom: 1px solid var(--border); vertical-align: top; }
        .recap th { color: var(--muted); font-weight: 600; width: 38%; }
        .progress { list-style: none; padding: 0; margin: 18px 0 0; }
        .progress li { display: flex; gap: 12px; align-items: flex-start; padding: 8px 0; color: var(--muted); }
        .progress li.running, .progress li.done, .progress li.failed { color: var(--text); }
        .progress li:not(.done, .failed, .running) .icon { background: var(--border); color: var(--muted); }
        .progress li.running .icon { background: var(--accent); color: #fff; animation: pulse 1s infinite alternate; }
        .progress .msg { font-size: 13px; color: var(--muted); }
        .progress pre { margin: 6px 0 0; font-size: 12px; background: var(--bg); padding: 8px; border-radius: 6px; max-height: 160px; overflow: auto; white-space: pre-wrap; }
        @keyframes pulse { to { opacity: .5; } }
        .muted { color: var(--muted); }
        a { color: var(--accent); }
    </style>
</head>
<body>
<header>
    <div class="inner">
        <h1>Installation de LG-IT</h1>
        <p id="mode-label">Configuration de l'application après déploiement sur OVH</p>
    </div>
</header>

<main>
    <nav aria-label="Étapes">
        <ol id="stepper">
            <li data-step="0"><span class="num">1</span><span class="label">Prérequis</span></li>
            <li data-step="1"><span class="num">2</span><span class="label">Application</span></li>
            <li data-step="2"><span class="num">3</span><span class="label">Base de données</span></li>
            <li data-step="3"><span class="num">4</span><span class="label">E-mail</span></li>
            <li data-step="4"><span class="num">5</span><span class="label">Administrateur</span></li>
            <li data-step="5"><span class="num">6</span><span class="label">Installation</span></li>
        </ol>
    </nav>

    <form id="wizard" novalidate autocomplete="off">
        {{-- 1. Prérequis --}}
        <section class="step" data-step="0">
            <h2>Vérification du serveur</h2>
            <p class="lead">L'hébergement doit remplir ces conditions avant de continuer.</p>
            <ul class="checks" id="checks"><li class="muted">Analyse en cours…</li></ul>
            <div id="checks-notice"></div>
            <div class="actions">
                <button type="button" id="recheck">Relancer la vérification</button>
                <div class="right"><button type="button" class="primary" data-next>Continuer</button></div>
            </div>
        </section>

        {{-- 2. Application --}}
        <section class="step" data-step="1">
            <h2>Application</h2>
            <p class="lead">Informations générales du site.</p>
            <div class="grid">
                <div><label for="app_name">Nom du site</label><input id="app_name" name="app_name" required></div>
                <div>
                    <label for="app_env">Environnement</label>
                    <select id="app_env" name="app_env">
                        <option value="production">Production</option>
                        <option value="staging">Staging (pré-production)</option>
                    </select>
                </div>
                <div class="full">
                    <label for="app_url">URL publique</label>
                    <input id="app_url" name="app_url" type="url" required placeholder="https://lgit.be">
                    <span class="hint">Sans barre oblique finale. Utilisée pour les liens dans les e-mails.</span>
                </div>
                <div>
                    <label for="app_timezone">Fuseau horaire</label>
                    <select id="app_timezone" name="app_timezone">
                        <option>Europe/Brussels</option>
                        <option>Europe/Paris</option>
                        <option>Europe/Luxembourg</option>
                        <option>UTC</option>
                    </select>
                </div>
            </div>
            <div class="actions">
                <button type="button" data-prev>Retour</button>
                <div class="right"><button type="button" class="primary" data-next>Continuer</button></div>
            </div>
        </section>

        {{-- 3. Base de données --}}
        <section class="step" data-step="2">
            <h2>Base de données MySQL</h2>
            <p class="lead">Identifiants visibles dans le Manager OVH › Hébergement › <em>Bases de données</em>.</p>
            <div class="grid">
                <div class="full">
                    <label for="db_host">Serveur</label>
                    <input id="db_host" name="db_host" required placeholder="xxxxxx.mysql.db">
                    <span class="hint">Sur OVH mutualisé : <code>nomdelabase.mysql.db</code>.</span>
                </div>
                <div><label for="db_database">Nom de la base</label><input id="db_database" name="db_database" required></div>
                <div><label for="db_port">Port</label><input id="db_port" name="db_port" type="number" required value="3306"></div>
                <div><label for="db_username">Utilisateur</label><input id="db_username" name="db_username" required></div>
                <div>
                    <label for="db_password">Mot de passe</label>
                    <input id="db_password" name="db_password" type="password" autocomplete="new-password">
                    <span class="hint" id="db_password_hint" hidden>Laisser vide pour conserver le mot de passe actuel.</span>
                </div>
            </div>
            <div id="db-notice"></div>
            <div class="actions">
                <button type="button" data-prev>Retour</button>
                <div class="right">
                    <button type="button" id="test-db">Tester la connexion</button>
                    <button type="button" class="primary" data-next>Continuer</button>
                </div>
            </div>
        </section>

        {{-- 4. E-mail --}}
        <section class="step" data-step="3">
            <h2>Envoi d'e-mails</h2>
            <p class="lead">Utilisé pour les notifications et la réinitialisation des mots de passe.</p>
            <div class="radio-row">
                <label><input type="radio" name="mail_mailer" value="smtp" checked> <span><strong>SMTP</strong><br><span class="muted">Adresse e-mail OVH (ssl0.ovh.net) ou autre fournisseur</span></span></label>
                <label><input type="radio" name="mail_mailer" value="log"> <span><strong>Désactivé</strong><br><span class="muted">Les e-mails sont écrits dans les logs, pas envoyés</span></span></label>
            </div>
            <div class="grid" id="smtp-fields" style="margin-top:16px">
                <div class="full"><label for="mail_host">Serveur SMTP</label><input id="mail_host" name="mail_host" placeholder="ssl0.ovh.net"></div>
                <div>
                    <label for="mail_scheme">Chiffrement</label>
                    <select id="mail_scheme" name="mail_scheme">
                        <option value="smtps">SSL/TLS (port 465)</option>
                        <option value="smtp">STARTTLS / aucun (port 587)</option>
                    </select>
                </div>
                <div><label for="mail_port">Port</label><input id="mail_port" name="mail_port" type="number"></div>
                <div><label for="mail_username">Identifiant</label><input id="mail_username" name="mail_username" placeholder="contact@lgit.be"></div>
                <div>
                    <label for="mail_password">Mot de passe</label>
                    <input id="mail_password" name="mail_password" type="password" autocomplete="new-password">
                    <span class="hint" id="mail_password_hint" hidden>Laisser vide pour conserver le mot de passe actuel.</span>
                </div>
            </div>
            <div class="grid" style="margin-top:14px">
                <div><label for="mail_from_address">Adresse d'expédition</label><input id="mail_from_address" name="mail_from_address" type="email" required placeholder="contact@lgit.be"></div>
                <div><label for="mail_from_name">Nom d'expéditeur</label><input id="mail_from_name" name="mail_from_name" placeholder="LG-IT"></div>
            </div>
            <div id="mail-notice"></div>
            <div class="actions">
                <button type="button" data-prev>Retour</button>
                <div class="right">
                    <button type="button" id="test-mail">Tester le SMTP</button>
                    <button type="button" class="primary" data-next>Continuer</button>
                </div>
            </div>
        </section>

        {{-- 5. Administrateur --}}
        <section class="step" data-step="4">
            <h2>Compte administrateur</h2>
            <p class="lead">Ce compte a accès à tout le back-office (cours, stages, professeurs, timesheets).</p>
            <div class="radio-row" id="admin-choice" hidden>
                <label><input type="radio" name="admin_mode" value="skip" checked> <span><strong>Conserver les comptes existants</strong><br><span class="muted">Un administrateur existe déjà en base</span></span></label>
                <label><input type="radio" name="admin_mode" value="create"> <span><strong>Créer / réinitialiser un admin</strong><br><span class="muted">Si l'e-mail existe, son mot de passe est remplacé</span></span></label>
            </div>
            <div class="grid" id="admin-fields" style="margin-top:16px">
                <div><label for="admin_name">Nom</label><input id="admin_name" name="admin_name" autocomplete="name"></div>
                <div><label for="admin_email">E-mail de connexion</label><input id="admin_email" name="admin_email" type="email" autocomplete="username"></div>
                <div>
                    <label for="admin_password">Mot de passe</label>
                    <input id="admin_password" name="admin_password" type="password" autocomplete="new-password">
                    <span class="hint">12 caractères minimum, majuscules, minuscules et chiffres.</span>
                </div>
                <div><label for="admin_password_confirmation">Confirmation</label><input id="admin_password_confirmation" name="admin_password_confirmation" type="password" autocomplete="new-password"></div>
            </div>
            <div class="actions">
                <button type="button" data-prev>Retour</button>
                <div class="right"><button type="button" class="primary" data-next>Continuer</button></div>
            </div>
        </section>

        {{-- 6. Installation --}}
        <section class="step" data-step="5">
            <h2>Récapitulatif</h2>
            <p class="lead">Vérifiez puis lancez l'installation. Les mots de passe ne sont pas affichés.</p>
            <table class="recap" id="recap"></table>
            <ul class="progress" id="progress">
                <li data-run="env"><span class="icon">1</span><div>Écriture du fichier .env<div class="msg"></div></div></li>
                <li data-run="migrate"><span class="icon">2</span><div>Création / mise à jour des tables<div class="msg"></div></div></li>
                <li data-run="admin"><span class="icon">3</span><div>Compte administrateur<div class="msg"></div></div></li>
                <li data-run="optimize"><span class="icon">4</span><div>Optimisation (caches, lien storage)<div class="msg"></div></div></li>
                <li data-run="finish"><span class="icon">5</span><div>Verrouillage de l'assistant<div class="msg"></div></div></li>
            </ul>
            <div id="install-notice"></div>
            <div class="actions" id="install-actions">
                <button type="button" data-prev>Retour</button>
                <div class="right"><button type="button" class="primary" id="install">Lancer l'installation</button></div>
            </div>
        </section>
    </form>
</main>

<script>
(() => {
    const TOKEN = @json($token);
    const form = document.getElementById('wizard');
    const steps = [...document.querySelectorAll('section.step')];
    const navItems = [...document.querySelectorAll('#stepper li')];
    let current = 0;
    let state = { admin_exists: false, requirementsOk: false };

    // Champs validés à chaque étape avant de passer à la suivante.
    const STEP_FIELDS = {
        1: ['app_name', 'app_url', 'app_env', 'app_timezone'],
        2: ['db_host', 'db_port', 'db_database', 'db_username', 'db_password'],
        3: ['mail_mailer', 'mail_host', 'mail_port', 'mail_scheme', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name'],
        4: ['admin_name', 'admin_email', 'admin_password', 'admin_password_confirmation'],
    };

    async function api(method, path, body) {
        const res = await fetch('/install' + path, {
            method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Install-Token': TOKEN },
            body: body ? JSON.stringify(body) : undefined,
        });
        let data = {};
        try { data = await res.json(); } catch { data = { message: 'Réponse inattendue du serveur (HTTP ' + res.status + ').' }; }
        if (res.status === 404) data.message = "Lien d'installation invalide ou expiré. Générez-en un nouveau : php artisan app:installer";
        return { ok: res.ok && data.ok !== false, status: res.status, data };
    }

    const values = () => {
        const fd = new FormData(form), out = {};
        for (const [k, v] of fd.entries()) out[k] = typeof v === 'string' ? v.trim() : v;
        out.admin_password = form.admin_password.value;
        out.admin_password_confirmation = form.admin_password_confirmation.value;
        out.db_password = form.db_password.value;
        out.mail_password = form.mail_password.value;
        return out;
    };

    function notice(el, type, text) {
        el.innerHTML = '';
        if (!text) return;
        const div = document.createElement('div');
        div.className = 'notice ' + type;
        div.textContent = text;
        el.appendChild(div);
    }

    function clearErrors(section) {
        section.querySelectorAll('.field-error').forEach(e => e.remove());
        section.querySelectorAll('.invalid').forEach(e => e.classList.remove('invalid'));
    }

    function showErrors(errors) {
        let first = null;
        for (const [name, msgs] of Object.entries(errors || {})) {
            const input = form.elements[name];
            if (!input || !input.parentElement) continue;
            const el = input instanceof RadioNodeList ? input[0] : input;
            el.classList.add('invalid');
            const div = document.createElement('div');
            div.className = 'field-error';
            div.textContent = msgs[0];
            el.closest('div').appendChild(div);
            const stepIdx = Number(el.closest('section').dataset.step);
            if (first === null || stepIdx < first) first = stepIdx;
        }
        return first;
    }

    function go(i) {
        current = i;
        steps.forEach((s, idx) => s.classList.toggle('active', idx === i));
        navItems.forEach((li, idx) => {
            li.classList.toggle('current', idx === i);
            li.classList.toggle('done', idx < i);
        });
        if (i === 5) renderRecap();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Validation côté navigateur (l'API revalide tout au moment de l'installation).
    function validateStep(i) {
        const section = steps[i];
        clearErrors(section);
        const errors = {};
        const v = values();
        const need = (name, msg) => { if (!v[name]) errors[name] = [msg || 'Champ obligatoire.']; };

        if (i === 1) {
            need('app_name'); need('app_url');
            if (v.app_url && !/^https?:\/\/[^\s/]+/.test(v.app_url)) errors.app_url = ['URL invalide (ex. https://lgit.be).'];
        }
        if (i === 2) {
            need('db_host'); need('db_port'); need('db_database'); need('db_username');
            if (!v.db_password && !state.has_db_password) errors.db_password = ['Mot de passe requis.'];
        }
        if (i === 3) {
            need('mail_from_address');
            if (v.mail_mailer === 'smtp') { need('mail_host'); need('mail_port'); }
        }
        if (i === 4 && adminMode() === 'create') {
            need('admin_name'); need('admin_email');
            const p = v.admin_password;
            if (p.length < 12 || !/[a-z]/.test(p) || !/[A-Z]/.test(p) || !/\d/.test(p)) {
                errors.admin_password = ['12 caractères minimum, avec majuscules, minuscules et chiffres.'];
            } else if (p !== v.admin_password_confirmation) {
                errors.admin_password_confirmation = ['La confirmation ne correspond pas.'];
            }
        }
        showErrors(errors);
        return Object.keys(errors).length === 0;
    }

    const adminMode = () => state.admin_exists ? form.querySelector('input[name="admin_mode"]:checked').value : 'create';

    form.addEventListener('click', e => {
        if (e.target.matches('[data-next]')) {
            if (current === 0 && !state.requirementsOk) return;
            if (validateStep(current)) go(current + 1);
        }
        if (e.target.matches('[data-prev]')) go(current - 1);
    });
    form.addEventListener('submit', e => e.preventDefault());

    // --- Prérequis + valeurs actuelles -------------------------------------
    async function loadStatus() {
        const list = document.getElementById('checks');
        const { ok, data } = await api('GET', '/status');
        if (!ok) {
            list.innerHTML = '';
            notice(document.getElementById('checks-notice'), 'error', data.message);
            return;
        }
        list.innerHTML = '';
        let blocking = 0;
        for (const c of data.requirements) {
            const level = c.ok ? 'ok' : c.level === 'warning' ? 'warn' : 'error';
            if (!c.ok && c.level !== 'warning') blocking++;
            const li = document.createElement('li');
            li.innerHTML = `<span class="icon ${level}">${c.ok ? '✓' : level === 'warn' ? '!' : '✕'}</span><div><strong></strong><div class="detail"></div></div>`;
            li.querySelector('strong').textContent = c.label;
            li.querySelector('.detail').textContent = c.detail;
            list.appendChild(li);
        }
        state.requirementsOk = blocking === 0;
        steps[0].querySelector('[data-next]').disabled = !state.requirementsOk;
        notice(document.getElementById('checks-notice'), blocking ? 'error' : 'ok',
            blocking ? `${blocking} prérequis bloquant(s) à corriger sur le serveur.` : 'Le serveur est prêt.');

        if (!state.loaded) {
            state.loaded = true;
            state.admin_exists = data.admin_exists;
            state.has_db_password = data.values.has_db_password;
            state.has_mail_password = data.values.has_mail_password;
            for (const [k, v] of Object.entries(data.values)) {
                const el = form.elements[k];
                if (!el || v === null || v === undefined) continue;
                el.value = v;
            }
            document.getElementById('db_password_hint').hidden = !state.has_db_password;
            document.getElementById('mail_password_hint').hidden = !state.has_mail_password;
            document.getElementById('admin-choice').hidden = !state.admin_exists;
            if (data.mode === 'reconfigure') {
                document.querySelector('header h1').textContent = 'Configuration de LG-IT';
                document.getElementById('mode-label').textContent = "L'application est déjà installée : modifiez les réglages puis relancez la finalisation.";
            }
            syncMail(); syncAdmin();
        }
    }
    document.getElementById('recheck').addEventListener('click', loadStatus);

    // --- Tests ------------------------------------------------------------
    async function runTest(button, path, fields, noticeEl) {
        button.disabled = true;
        const original = button.textContent;
        button.textContent = 'Test en cours…';
        clearErrors(steps[current]);
        const v = values(), body = {};
        fields.forEach(f => body[f] = v[f]);
        const { ok, status, data } = await api('POST', path, body);
        if (status === 422 && data.errors) { showErrors(data.errors); notice(noticeEl, 'error', 'Vérifiez les champs signalés.'); }
        else notice(noticeEl, ok ? (data.warning ? 'warn' : 'ok') : 'error', data.message);
        button.disabled = false;
        button.textContent = original;
    }
    document.getElementById('test-db').addEventListener('click', e =>
        runTest(e.target, '/test/database', STEP_FIELDS[2], document.getElementById('db-notice')));
    document.getElementById('test-mail').addEventListener('click', e =>
        runTest(e.target, '/test/mail', ['mail_host', 'mail_port', 'mail_scheme', 'mail_username', 'mail_password'], document.getElementById('mail-notice')));

    // --- Bascules --------------------------------------------------------
    function syncMail() {
        const smtp = form.querySelector('input[name="mail_mailer"]:checked').value === 'smtp';
        document.getElementById('smtp-fields').hidden = !smtp;
        document.getElementById('test-mail').hidden = !smtp;
    }
    function syncAdmin() {
        document.getElementById('admin-fields').hidden = adminMode() === 'skip';
    }
    form.querySelectorAll('input[name="mail_mailer"]').forEach(r => r.addEventListener('change', syncMail));
    form.querySelectorAll('input[name="admin_mode"]').forEach(r => r.addEventListener('change', syncAdmin));
    form.mail_scheme.addEventListener('change', () => { form.mail_port.value = form.mail_scheme.value === 'smtps' ? 465 : 587; });

    // --- Récapitulatif + exécution ---------------------------------------
    function renderRecap() {
        const v = values();
        const rows = [
            ['Site', `${v.app_name} — ${v.app_url}`],
            ['Environnement', v.app_env === 'production' ? 'Production (APP_DEBUG=false)' : 'Staging (APP_DEBUG=false)'],
            ['Fuseau horaire', v.app_timezone],
            ['Base de données', `${v.db_username}@${v.db_host}:${v.db_port} / ${v.db_database}`],
            ['E-mails', v.mail_mailer === 'smtp' ? `${v.mail_username || '(sans auth)'} via ${v.mail_host}:${v.mail_port}` : 'Désactivés (logs)'],
            ['Expéditeur', `${v.mail_from_name || v.app_name} <${v.mail_from_address}>`],
            ['Administrateur', adminMode() === 'skip' ? 'Comptes existants conservés' : `${v.admin_name} <${v.admin_email}>`],
        ];
        const table = document.getElementById('recap');
        table.innerHTML = '';
        for (const [k, val] of rows) {
            const tr = table.insertRow();
            const th = document.createElement('th'); th.textContent = k; tr.appendChild(th);
            tr.insertCell().textContent = val;
        }
    }

    document.getElementById('install').addEventListener('click', async e => {
        const button = e.target;
        const noticeEl = document.getElementById('install-notice');
        button.disabled = true;
        steps[5].querySelector('[data-prev]').disabled = true;
        notice(noticeEl);
        const v = values();
        const payloads = {
            env: v,
            migrate: {},
            admin: adminMode() === 'skip' ? { skip: true } : v,
            optimize: {},
            finish: {},
        };

        for (const li of document.querySelectorAll('#progress li')) li.className = '';

        for (const li of document.querySelectorAll('#progress li')) {
            const step = li.dataset.run;
            const msg = li.querySelector('.msg');
            li.className = 'running';
            msg.textContent = 'En cours…';
            const { ok, status, data } = await api('POST', '/run/' + step, payloads[step]);

            if (!ok) {
                li.className = 'failed';
                li.querySelector('.icon').className = 'icon error';
                li.querySelector('.icon').textContent = '✕';
                msg.textContent = data.message || 'Erreur.';
                if (status === 422 && data.errors) {
                    const target = showErrors(data.errors);
                    notice(noticeEl, 'error', 'Certaines valeurs ont été refusées : corrigez-les puis relancez.');
                    if (target !== null) setTimeout(() => go(target), 1200);
                } else {
                    notice(noticeEl, 'error', "L'installation s'est arrêtée. Corrigez le problème puis relancez : les étapes déjà faites peuvent être rejouées sans risque.");
                }
                button.disabled = false;
                button.textContent = "Relancer l'installation";
                steps[5].querySelector('[data-prev]').disabled = false;
                return;
            }

            li.className = 'done';
            li.querySelector('.icon').className = 'icon ok';
            li.querySelector('.icon').textContent = '✓';
            msg.textContent = data.message;
            if (data.output) {
                const pre = document.createElement('pre');
                pre.textContent = data.output;
                msg.appendChild(pre);
            }
            (data.warnings || []).forEach(w => notice(noticeEl, 'warn', w));

            if (step === 'finish') {
                navItems[5].classList.add('done');
                document.getElementById('install-actions').innerHTML = '';
                const done = document.createElement('div');
                done.className = 'notice ok';
                done.innerHTML = '<strong>Installation terminée.</strong> Ce lien est maintenant désactivé. ';
                const a = document.createElement('a');
                a.href = data.login_url;
                a.textContent = 'Se connecter au back-office →';
                done.appendChild(a);
                noticeEl.appendChild(done);
            }
        }
    });

    go(0);
    loadStatus();
})();
</script>
</body>
</html>
