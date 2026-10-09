/* Rechnungsprogramm – App (ohne Build-Schritt, ohne Abhängigkeiten) */
(() => {
	'use strict';

	/* ================================================================ Hilfsfunktionen */

	const $ = (sel, root = document) => root.querySelector(sel);
	const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
	const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const nfMoney = new Intl.NumberFormat('de-AT', { style: 'currency', currency: 'EUR' });
	const nfNum = new Intl.NumberFormat('de-AT', { maximumFractionDigits: 3 });
	const nf2 = new Intl.NumberFormat('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	const money = (v) => nfMoney.format(+v || 0);
	const moneyShort = (v) => (Math.abs(v) >= 10000 ? new Intl.NumberFormat('de-AT', { maximumFractionDigits: 0 }).format(v) + ' €' : money(v));
	const qty = (v) => nfNum.format(+v || 0);
	const dec = (v) => nf2.format(+v || 0);
	const date = (iso) => (iso ? iso.slice(8, 10) + '.' + iso.slice(5, 7) + '.' + iso.slice(0, 4) : '');
	const num = (s) => {
		if (typeof s === 'number') return s;
		s = String(s ?? '').trim().replace(/\s/g, '');
		if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
		const n = parseFloat(s);
		return isNaN(n) ? 0 : n;
	};
	const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
	const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
	const addDays = (iso, d) => { const t = new Date(iso + 'T12:00:00'); t.setDate(t.getDate() + d); return t.toISOString().slice(0, 10); };
	const addMonths = (iso, m) => {
		let [y, mo, d] = iso.split('-').map(Number);
		mo += m; y += Math.floor((mo - 1) / 12); mo = ((mo - 1) % 12 + 12) % 12 + 1;
		d = Math.min(d, new Date(y, mo, 0).getDate());
		return `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
	};
	const MONTHS = ['Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
	const MONTHS_LONG = ['Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
	const norm = (s) => String(s ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
	const store = {
		get(k, d) { try { const v = localStorage.getItem('nw.' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
		set(k, v) { try { localStorage.setItem('nw.' + k, JSON.stringify(v)); } catch (e) { /* privat/gesperrt */ } },
	};

	/* Icons (Linienstil, 24er Raster) */
	const ICONS = {
		undo: '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
		home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
		file: '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>',
		users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-5.5 6.5-5.5s5.9 1.9 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.8c2 .7 3.2 2.4 3.5 5.2"/>',
		box: '<path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/>',
		repeat: '<path d="M17 2.5 20.5 6 17 9.5"/><path d="M3.5 11V9a3 3 0 0 1 3-3h14"/><path d="M7 21.5 3.5 18 7 14.5"/><path d="M20.5 13v2a3 3 0 0 1-3 3h-14"/>',
		wallet: '<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18M16 15h2"/><path d="M6 6V5a2 2 0 0 1 2-2h9"/>',
		cog: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
		plus: '<path d="M12 5v14M5 12h14"/>',
		search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
		download: '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 17v3h16v-3"/>',
		share: '<path d="M12 3v12M8 7l4-4 4 4"/><path d="M5 12v8h14v-8"/>',
		copy: '<rect x="8" y="8" width="13" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
		x: '<path d="M6 6l12 12M18 6 6 18"/>',
		trash: '<path d="M4 7h16M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13M9 7V4h6v3"/>',
		edit: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
		left: '<path d="M15 5 8 12l7 7"/>',
		right: '<path d="m9 5 7 7-7 7"/>',
		up: '<path d="m6 15 6-6 6 6"/>',
		down: '<path d="m6 9 6 6 6-6"/>',
		logout: '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 17l5-5-5-5M15 12H4"/>',
		eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		alert: '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17.5v.01"/>',
		clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		moon: '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
		sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		auto: '<circle cx="12" cy="12" r="9"/><path d="M12 3v18" /><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>',
		ban: '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>',
		bell: '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
		play: '<path d="M7 4.5v15l12-7.5z"/>',
		archive: '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10h14V9M10 13h4"/>',
		more: '<circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/>',
		paperclip: '<path d="m20 11-8.5 8.5a5 5 0 0 1-7-7L13 4a3.5 3.5 0 0 1 5 5l-8.5 8.5a2 2 0 0 1-3-3L14 7"/>',
		chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
		key: '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M14 9l2 2"/>',
		send: '<path d="M21 3 10 14"/><path d="m21 3-7 18-4-7-7-4z"/>',
		offer: '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/><path d="m9 14 2 2 4-4"/>',
		menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
		grip: '<circle cx="9" cy="6" r="1.2" fill="currentColor"/><circle cx="15" cy="6" r="1.2" fill="currentColor"/><circle cx="9" cy="12" r="1.2" fill="currentColor"/><circle cx="15" cy="12" r="1.2" fill="currentColor"/><circle cx="9" cy="18" r="1.2" fill="currentColor"/><circle cx="15" cy="18" r="1.2" fill="currentColor"/>',
		sidebar: '<rect x="3" y="4" width="18" height="16" rx="1.5"/><path d="M9 4v16"/>',
	};
	const icon = (n, cls = '') => `<svg class="i ${cls}" viewBox="0 0 24 24" aria-hidden="true">${ICONS[n] || ''}</svg>`;
	/** Logo (bereinigtes SVG vom Server) oder der Firmenname als Schriftzug. */
	const LOGO = () => ($('#logo-svg')?.textContent || '').trim() || `<span class="logo-text">${esc(S.settings.company || 'Rechnungen')}</span>`;
	const initials = () => (S.settings.company || 'Rechnungen').split(/\s+/).filter((w) => !/^(gmbh|og|kg|e\.?u\.?|ag)$/i.test(w)).map((w) => w[0]).join('').slice(0, 2).toLowerCase();

	/** Rundes Symbol für wiederkehrende Rechnungen bzw. Kunden mit Dauerrechnung. */
	const recDot = (title = 'Wiederkehrende Rechnung') => `<span class="rec-dot" title="${title}" aria-label="${title}">${icon('repeat')}</span>`;
	const withRec = (name, on, title) => `<span class="name-rec"><span class="nm">${name}</span>${on ? recDot(title) : ''}</span>`;
	const STATE_LABEL = { draft: 'Entwurf', open: 'Offen', overdue: 'Überfällig', partial: 'Teilweise bezahlt', paid: 'Bezahlt', cancelled: 'Storniert', storno: 'Storno', sent: 'Offen', accepted: 'Angenommen', declined: 'Abgelehnt', expired: 'Abgelaufen' };
	const badge = (state, extra = '') => `<span class="badge b-${state}">${STATE_LABEL[state] || state}${extra}</span>`;
	const MODE_LABEL = { send: 'Automatisch senden', issue: 'Ausstellen (ohne Mail)', draft: 'Entwurf zur Prüfung' };
	const INTERVALS = { 1: 'monatlich', 2: 'alle 2 Monate', 3: 'vierteljährlich', 6: 'halbjährlich', 12: 'jährlich', 24: 'alle 2 Jahre', 36: 'alle 3 Jahre' };

	/* ================================================================ API */

	let CSRF = '';
	async function api(action, data, opts = {}) {
		const isForm = data instanceof FormData;
		const init = data === undefined
			? { method: 'GET', credentials: 'same-origin' }
			: { method: 'POST', credentials: 'same-origin', headers: isForm ? { 'X-CSRF': CSRF } : { 'Content-Type': 'application/json', 'X-CSRF': CSRF }, body: isForm ? data : JSON.stringify(data) };
		const q = opts.query ? '&' + new URLSearchParams(opts.query) : '';
		const res = await fetch('api.php?a=' + action + q, init);
		if (opts.blob) {
			if (!res.ok) throw new Error((await res.json().catch(() => ({}))).error || 'Fehler ' + res.status);
			if (opts.headers) return { blob: await res.blob(), headers: res.headers };
			return res.blob();
		}
		const json = await res.json().catch(() => ({ error: 'Ungültige Antwort vom Server (' + res.status + ')' }));
		if (res.status === 401 && json.auth === false) { boot(); throw new Error('Bitte anmelden.'); }
		if (!res.ok || json.error) throw new Error(json.error || 'Fehler ' + res.status);
		return json;
	}

	/* ================================================================ Zustand */

	const S = { settings: {}, customers: [], products: [], invoices: [], recurring: [], mail: {}, today: '' };
	async function refresh() {
		Object.assign(S, await api('bootstrap'));
		updateNavCounts();
	}
	const customerById = (id) => S.customers.find((c) => +c.id === +id);
	const productBySku = (sku) => S.products.find((p) => p.sku === sku);

	/* ================================================================ UI-Bausteine */

	function toast(msg, opts = {}) {
		const el = document.createElement('div');
		el.className = 'toast' + (opts.error ? ' err' : '');
		el.innerHTML = `<span>${esc(msg)}</span>` + (opts.action ? `<button type="button">${esc(opts.action)}</button>` : '');
		if (opts.action) el.querySelector('button').onclick = () => { opts.onAction(); el.remove(); };
		$('#toasts').appendChild(el);
		setTimeout(() => el.remove(), opts.error ? 6500 : opts.action ? 6000 : 3200);
	}
	const fail = (e) => toast(e.message || String(e), { error: true });

	let layers = [];
	function closeTop() {
		const l = layers.pop();
		if (!l) return false;
		if (l.onClose && l.onClose() === false) { layers.push(l); return true; }
		l.els.forEach((e) => e.remove());
		if (l.restore) l.restore.focus?.();
		return true;
	}
	function openLayer(html, cls, opts = {}) {
		const ov = document.createElement('div');
		ov.className = 'overlay';
		const el = document.createElement('div');
		el.className = cls;
		el.setAttribute('role', 'dialog');
		el.setAttribute('aria-modal', 'true');
		el.innerHTML = html;
		document.body.append(ov, el);
		const layer = { els: [ov, el], onClose: opts.onClose, restore: document.activeElement };
		layers.push(layer);
		ov.onclick = () => closeTop();
		$$('[data-close]', el).forEach((b) => (b.onclick = () => closeTop()));
		setTimeout(() => (el.querySelector('[autofocus]') || el.querySelector('input,select,textarea,button'))?.focus(), 30);
		return el;
	}
	function drawer(title, body, foot, opts = {}) {
		return openLayer(
			`<div class="drawer-head"><h2>${title}</h2><button class="btn ghost icon" data-close aria-label="Schließen">${icon('x')}</button></div>
			<div class="drawer-body">${body}</div>${foot ? `<div class="drawer-foot">${foot}</div>` : ''}`,
			'drawer' + (opts.wide ? ' wide' : ''), opts);
	}
	function confirmDialog(title, text, okLabel = 'OK', opts = {}) {
		return new Promise((resolve) => {
			const el = openLayer(
				`<div class="dialog-body"><h2>${esc(title)}</h2><p>${text}</p>${opts.extra || ''}</div>
				<div class="drawer-foot"><button class="btn" data-close>Abbrechen</button><button class="btn ${opts.danger ? 'dark' : 'primary'}" data-ok autofocus>${esc(okLabel)}</button></div>`,
				'dialog' + (opts.wide ? ' wide' : ''), { onClose: () => resolve(null) });
			$('[data-ok]', el).onclick = () => {
				const vals = {};
				$$('input,select,textarea', el).forEach((i) => (vals[i.name] = i.type === 'checkbox' ? i.checked : i.value));
				layers.pop(); el.previousSibling.remove(); el.remove();
				resolve(vals);
			};
		});
	}

	/** Autovervollständigung an einem Eingabefeld. */
	function autocomplete(input, source, onPick, opts = {}) {
		let box = null, items = [], idx = 0;
		const wrap = input.parentElement;
		const close = () => { box?.remove(); box = null; };
		const render = () => {
			items = source(input.value);
			if (!items.length || (!opts.showEmpty && !input.value.trim() && !opts.always)) return close();
			if (!box) { box = document.createElement('div'); box.className = 'ac'; wrap.appendChild(box); }
			idx = Math.min(idx, items.length - 1);
			box.innerHTML = items.map((it, i) => `<div class="ac-item ${i === idx ? 'on' : ''}" data-i="${i}">${it.html}</div>`).join('');
			$$('.ac-item', box).forEach((d) => d.onmousedown = (e) => { e.preventDefault(); pick(+d.dataset.i); });
		};
		const pick = (i) => { const it = items[i]; close(); if (it) onPick(it.value); };
		input.addEventListener('input', () => { idx = 0; render(); });
		input.addEventListener('focus', () => { if (opts.always) render(); });
		input.addEventListener('blur', () => setTimeout(close, 120));
		input.addEventListener('keydown', (e) => {
			if (!box) { if (e.key === 'ArrowDown') { render(); e.preventDefault(); } return; }
			if (e.key === 'ArrowDown') { idx = (idx + 1) % items.length; render(); e.preventDefault(); }
			else if (e.key === 'ArrowUp') { idx = (idx - 1 + items.length) % items.length; render(); e.preventDefault(); }
			else if (e.key === 'Enter') { pick(idx); e.preventDefault(); }
			else if (e.key === 'Escape') { close(); e.stopPropagation(); }
		});
	}

	/* ---------------------------------------------------------------- PLZ ↔ Ort (Österreich, Daten: GeoNames CC BY 4.0) */

	let PLZ = null;
	const loadPlz = () => (PLZ ||= fetch('assets/plz-at.json').then((r) => r.json()).then((d) => {
		const list = [];
		Object.entries(d).forEach(([plz, names]) => names.forEach((n, i) => list.push({ plz, name: n, main: i === 0, key: norm(n) })));
		return { byPlz: d, list };
	}).catch(() => ({ byPlz: {}, list: [] })));
	const isAustria = (v) => !v || /^(österreich|oesterreich|austria|at)$/i.test(String(v).trim());

	/** Vorschläge: PLZ eintippen → Ort, Ort eintippen → PLZ. */
	function plzAssist(zipEl, cityEl, countryEl) {
		if (!zipEl || !cityEl) return;
		let data = null;
		loadPlz().then((d) => (data = d));
		let autoCity = false;
		const opt = (plz, name, main) => ({ value: { plz, name }, html: `<span class="mono">${esc(plz)}</span><div class="grow"><div class="t">${esc(name)}</div>${main ? '' : '<div class="s">Ortschaft</div>'}</div>` });
		const austria = () => isAustria(countryEl?.value);
		const pick = (v) => {
			zipEl.value = v.plz; cityEl.value = v.name; autoCity = true; // aus Vorschlag – darf bei PLZ-Änderung mitwandern
		};
		autocomplete(zipEl, (q) => {
			if (!data || !austria() || !/^\d{1,4}$/.test(q.trim())) return [];
			const t = q.trim();
			return Object.keys(data.byPlz).filter((p) => p.startsWith(t)).slice(0, 4 === t.length ? 1 : 8)
				.flatMap((p) => data.byPlz[p].slice(0, 4 === t.length ? 12 : 1).map((n, i) => opt(p, n, i === 0)));
		}, pick);
		autocomplete(cityEl, (q) => {
			const n = norm(q.trim());
			if (!data || !austria() || n.length < 2) return [];
			const starts = data.list.filter((e) => e.key.startsWith(n));
			const more = starts.length < 8 ? data.list.filter((e) => !e.key.startsWith(n) && e.key.includes(n)) : [];
			return [...starts.sort((a, b) => b.main - a.main), ...more].slice(0, 8).map((e) => opt(e.plz, e.name, e.main));
		}, pick);
		// Vollständige PLZ: Postort automatisch eintragen, solange der Ort leer ist oder automatisch kam
		zipEl.addEventListener('input', () => {
			const t = zipEl.value.trim();
			if (data && austria() && /^\d{4}$/.test(t) && data.byPlz[t] && (!cityEl.value.trim() || autoCity)) {
				cityEl.value = data.byPlz[t][0]; autoCity = true;
			}
		});
		cityEl.addEventListener('input', (e) => { if (e.isTrusted) autoCity = false; });
	}

	const customerSource = (q) => {
		const n = norm(q);
		const list = S.customers.filter((c) => !+c.archived && (!n || norm(c.name + ' ' + c.person + ' ' + c.number + ' ' + c.city).includes(n))).slice(0, 8)
			.map((c) => ({ value: c, html: `<span class="mono">${esc(c.number)}</span><div class="grow"><div class="t">${esc(c.name)}</div><div class="s">${esc([c.person, c.city].filter(Boolean).join(' · '))}</div></div>` }));
		list.push({ value: { _new: true, name: q }, html: `${icon('plus')}<div class="grow"><div class="t">Neuer Kunde${q ? ' „' + esc(q) + '“' : ''}</div></div>` });
		return list;
	};
	const productSource = (q) => {
		const n = norm(q);
		if (!n) return [];
		return S.products.filter((p) => !+p.archived && norm(p.sku + ' ' + p.name + ' ' + p.category).includes(n)).slice(0, 8)
			.map((p) => ({ value: p, html: `<span class="mono">${esc(p.sku)}</span><div class="grow"><div class="t">${esc(p.name)}</div><div class="s">${esc(p.category || '')}${p.unit ? ' · je ' + esc(p.unit) : ''}</div></div><b class="num">${money(p.price)}</b>` }));
	};

	/* ================================================================ Shell, Navigation */

	const NAV = [
		['#/', 'home', 'Übersicht'],
		['#/rechnungen', 'file', 'Rechnungen'],
		['#/angebote', 'offer', 'Angebote'],
		['#/stunden', 'clock', 'Stunden'],
		['#/kunden', 'users', 'Kunden'],
		['#/dauerrechnungen', 'repeat', 'Dauerrechnungen'],
		['#/artikel', 'box', 'Artikel'],
		['#/ausgaben', 'wallet', 'Ausgaben'],
	];

	function themeIcon() { const t = store.get('theme', 'auto'); return t === 'dark' ? 'moon' : t === 'light' ? 'sun' : 'auto'; }
	/** Häufige Anbieter – Werte laut deren Anleitungen; bei Unsicherheit beim Anbieter nachsehen. */
	const SMTP_PRESETS = [
		{ name: 'Eigene Domain (Webhoster)', host: null, port: 465, secure: 'ssl', hint: 'Server, Benutzer und Passwort stehen in der Postfach-Verwaltung deines Webhosters (meist mail.deine-domain.at oder smtp.anbieter.at, Port 465 mit SSL oder 587 mit STARTTLS).' },
		{ name: 'Gmail', host: 'smtp.gmail.com', port: 465, secure: 'ssl', hint: 'Gmail braucht die Zwei-Faktor-Anmeldung und ein „App-Passwort“ (Google-Konto → Sicherheit → App-Passwörter). Das normale Passwort funktioniert nicht.' },
		{ name: 'Microsoft 365 / Outlook', host: 'smtp.office365.com', port: 587, secure: 'tls', hint: 'Benutzer = deine E-Mail-Adresse. SMTP-Authentifizierung muss im Postfach erlaubt sein; bei Zwei-Faktor-Anmeldung ein App-Kennwort verwenden.' },
		{ name: 'GMX', host: 'mail.gmx.net', port: 465, secure: 'ssl', hint: 'In den GMX-Einstellungen „POP3/IMAP Abruf“ bzw. den SMTP-Versand über externe Programme erlauben.' },
		{ name: 'WEB.DE', host: 'smtp.web.de', port: 587, secure: 'tls', hint: 'In den WEB.DE-Einstellungen den Zugriff über externe Programme (POP3/IMAP) erlauben.' },
		{ name: 'iCloud', host: 'smtp.mail.me.com', port: 587, secure: 'tls', hint: 'Benutzer = iCloud-Adresse, Passwort = app-spezifisches Passwort (appleid.apple.com → Anmeldung und Sicherheit).' },
	];

	const UI_THEMES = [
		{ id: 'schlicht', name: 'Schlicht', color: '#16171a', desc: 'Schwarz-weiß, ruhig und zeitlos' },
		{ id: 'modern', name: 'Modern', color: '#4f46e5', desc: 'Weiche Ecken, sanfte Schatten, Indigo', modern: true },
		{ id: 'blau', name: 'Blau', color: '#1f4fd1', desc: 'Klar und freundlich' },
		{ id: 'tanne', name: 'Tannengrün', color: '#1d6b4f', desc: 'Ruhig, naturnah' },
		{ id: 'bordeaux', name: 'Bordeaux', color: '#8e1f2a', desc: 'Warm, klassisch' },
		{ id: 'kupfer', name: 'Kupfer', color: '#b4531f', desc: 'Kräftig, handwerklich' },
		{ id: 'petrol', name: 'Petrol', color: '#0f6b78', desc: 'Kühl, technisch' },
	];
	/** Design der Oberfläche anwenden (Attribut + Grundfarbe; alles Weitere macht das CSS). */
	function applyUi(id) {
		const t = UI_THEMES.find((x) => x.id === id) || UI_THEMES[0];
		document.documentElement.dataset.ui = t.id;
		document.documentElement.style.setProperty('--accent-base', t.color);
	}
	const miniPreview = (t) => {
		const soft = t.id === 'schlicht' ? '#eaecef' : `color-mix(in srgb, ${t.color} 12%, #fff)`;
		const r = t.modern ? '9px' : '3px';
		const bg = t.modern ? '#f3f3f9' : '#eef0f3';
		return `<div class="tmini" style="background:${bg};border-radius:${t.modern ? '10px' : '6px'}">
			<div class="tmini-side"><b></b><span class="on" style="background:${soft};border-left:2px solid ${t.color}"></span><span></span><span></span><span></span></div>
			<div class="tmini-main"><div class="mh"><b></b><em style="background:${t.modern ? `linear-gradient(135deg, ${t.color}, color-mix(in srgb, ${t.color} 60%, #c026d3))` : t.color};border-radius:${r}"></em></div>
				<div class="mc" style="border-radius:${r};${t.modern ? 'box-shadow:0 3px 10px rgba(30,27,75,.08)' : 'border:1px solid #d9dce1'}"><i style="height:40%;background:#b5bac3"></i><i style="height:75%;background:${t.color}"></i><i style="height:55%;background:#b5bac3"></i><i style="height:95%;background:${t.color}"></i><i style="height:30%;background:#b5bac3"></i><i style="height:65%;background:${t.color}"></i></div>
				<div class="mb"><s style="background:${soft}"></s><s style="background:#e6f4ec"></s><s style="background:${soft}"></s></div></div>
		</div>`;
	};

	function applyTheme() {
		const t = store.get('theme', 'auto');
		if (t === 'auto') document.documentElement.removeAttribute('data-theme');
		else document.documentElement.setAttribute('data-theme', t);
	}
	function cycleTheme() {
		const order = ['auto', 'light', 'dark'];
		const t = order[(order.indexOf(store.get('theme', 'auto')) + 1) % 3];
		store.set('theme', t); applyTheme();
		$$('[data-theme-btn]').forEach((b) => (b.innerHTML = icon(themeIcon()) + (b.dataset.themeBtn === 'label' ? `<span class="lbl">Design: ${{ auto: 'Automatisch', light: 'Hell', dark: 'Dunkel' }[t]}</span>` : '')));
	}

	function renderShell() {
		const mac = /Mac|iPhone|iPad/.test(navigator.platform);
		$('#app').innerHTML = `
		<div class="shell ${store.get('collapsed', false) ? 'collapsed' : ''}">
			<aside class="side" aria-label="Hauptmenü">
				<div class="side-head">
					<a class="brand" href="#/" aria-label="Übersicht">
						<span class="brand-logo">${LOGO()}</span>
						<span class="brand-mark" aria-hidden="true">[${esc(initials())}]</span>
					</a>
					<button class="side-toggle" type="button" data-collapse aria-label="Menü einklappen" title="Menü ein-/ausklappen">${icon('sidebar')}</button>
				</div>
				<a class="btn primary new" href="#/rechnung/neu" title="Neue Rechnung">${icon('plus')}<span class="lbl">Neue Rechnung</span></a>
				<nav class="nav">${NAV.map(([h, i, l]) => `<a href="${h}" data-nav="${h}" title="${l}">${icon(i)}<span class="lbl">${l}</span><span class="count" data-count="${h}"></span></a>`).join('')}
				</nav>
				<div class="side-foot">
					<button type="button" class="side-link" data-palette title="Suchen">${icon('search')}<span class="lbl">Suchen</span><span class="lbl keys"><kbd>${mac ? '⌘' : 'Strg'}</kbd><kbd>K</kbd></span></button>
					<a class="side-link" href="#/einstellungen" data-nav="#/einstellungen" title="Einstellungen">${icon('cog')}<span class="lbl">Einstellungen</span></a>
					<div class="side-row">
						<button type="button" class="side-link" data-logout title="Abmelden">${icon('logout')}<span class="lbl">Abmelden</span></button>
						<button type="button" class="side-icon" data-theme-btn title="Hell / Dunkel / Automatisch" aria-label="Hell oder dunkel umschalten">${icon(themeIcon())}</button>
					</div>
					<a class="side-version lbl" href="#/einstellungen/update" title="Version und Updates">Version ${esc(S.version || '')}</a>
				</div>
			</aside>
			<div>
				<header class="topbar">
					<button class="btn ghost icon" data-menu aria-label="Menü öffnen" aria-expanded="false">${icon('menu')}</button>
					<a class="logo" href="#/" aria-label="Übersicht">${LOGO()}</a>
					<button class="btn ghost icon" data-palette aria-label="Suchen">${icon('search')}</button>
				</header>
				<div class="side-scrim" data-menu-close></div>
				<main class="main" id="main"></main>
			</div>
			<nav class="tabbar">
				<a href="#/" data-nav="#/">${icon('home')}Übersicht</a>
				<a href="#/rechnungen" data-nav="#/rechnungen">${icon('file')}Rechnungen<span class="dot hide" data-dot></span></a>
				<a href="#/rechnung/neu" class="plus" aria-label="Neue Rechnung"><span class="fab">${icon('plus')}</span></a>
				<a href="#/kunden" data-nav="#/kunden">${icon('users')}Kunden</a>
				<a href="#/mehr" data-nav="#/mehr">${icon('more')}Mehr</a>
			</nav>
		</div>`;
		$$('[data-palette]').forEach((b) => (b.onclick = openPalette));
		$$('[data-theme-btn]').forEach((b) => (b.onclick = cycleTheme));
		$('[data-logout]').onclick = logout;
		// Handy: Seitenleiste als Menü von links
		const setMenu = (open) => {
			$('.shell').classList.toggle('menu-open', open);
			$('[data-menu]')?.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) $('.side .nav a')?.focus();
		};
		$('[data-menu]').onclick = () => setMenu(true);
		$('[data-menu-close]').onclick = () => setMenu(false);
		$$('.side a, .side button:not([data-collapse])').forEach((a) => a.addEventListener('click', () => setMenu(false)));
		document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && $('.shell.menu-open')) setMenu(false); });
		$('[data-collapse]').onclick = () => {
			const on = !$('.shell').classList.contains('collapsed');
			$('.shell').classList.toggle('collapsed', on);
			store.set('collapsed', on);
			$('[data-collapse]').setAttribute('aria-label', on ? 'Menü ausklappen' : 'Menü einklappen');
		};
		updateNavCounts();
	}

	function updateNavCounts() {
		const open = S.invoices.filter((i) => i.state === 'open' || i.state === 'overdue' || i.state === 'partial');
		const overdue = open.filter((i) => i.days_overdue > 0).length;
		const due = S.recurring.filter((r) => r.due).length;
		const set = (h, txt, alert) => { const el = $(`[data-count="${h}"]`); if (el) { el.textContent = txt || ''; el.classList.toggle('alert', !!alert); } };
		set('#/rechnungen', open.length ? (overdue ? overdue + ' / ' : '') + open.length : '', overdue);
		set('#/dauerrechnungen', due ? due + ' fällig' : '', due);
		const offOpen = (S.offers || []).filter((o) => o.state === 'sent').length;
		set('#/angebote', offOpen ? String(offOpen) : '', false);
		const dot = $('[data-dot]');
		if (dot) { dot.textContent = overdue; dot.classList.toggle('hide', !overdue); }
	}

	async function logout() {
		await api('logout', {}).catch(() => {});
		location.hash = '#/';
		boot();
	}

	/* ================================================================ Router */

	let current = null;      // aktuelle Ansicht { cleanup, dirty }
	const routes = [
		[/^#?\/?$/, viewDashboard],
		[/^#\/rechnungen$/, viewInvoices],
		[/^#\/rechnung\/neu$/, (q) => viewInvoice(null, q)],
		[/^#\/angebote$/, viewOffers],
		[/^#\/stunden$/, viewHours],
		[/^#\/angebot\/neu$/, (q) => viewInvoice(null, q, 'offer')],
		[/^#\/angebot\/(\d+)$/, (q, m) => viewInvoice(+m[1], q)],
		[/^#\/rechnung\/(\d+)$/, (q, m) => viewInvoice(+m[1], q)],
		[/^#\/kunden$/, viewCustomers],
		[/^#\/kunde\/(\d+)$/, (q, m) => viewCustomer(+m[1])],
		[/^#\/artikel$/, viewProducts],
		[/^#\/dauerrechnungen$/, viewRecurring],
		[/^#\/ausgaben$/, viewExpenses],
		[/^#\/einstellungen(?:\/(\w+))?$/, (q, m) => viewSettings(m[1] || 'firma')],
		[/^#\/mehr$/, viewMore],
	];
	let lastHash = location.hash;
	async function route() {
		if (current?.dirty && location.hash !== lastHash) {
			const ok = await confirmDialog('Änderungen verwerfen?', 'Der Entwurf hat ungespeicherte Änderungen.', 'Verwerfen', { danger: true });
			if (!ok) { history.replaceState(null, '', lastHash); return; }
		}
		while (layers.length) closeTop();
		current?.cleanup?.();
		current = null;
		const [path, qs] = (location.hash || '#/').split('?');
		const query = Object.fromEntries(new URLSearchParams(qs || ''));
		lastHash = location.hash;
		$$('[data-nav]').forEach((a) => {
			const h = a.dataset.nav;
			a.classList.toggle('on', h === '#/' ? path === '#/' || path === '' : path.startsWith(h) || (h === '#/rechnungen' && path.startsWith('#/rechnung/')) || (h === '#/angebote' && path.startsWith('#/angebot/')) || (h === '#/kunden' && path.startsWith('#/kunde/')) || (h === '#/mehr' && ['#/angebot', '#/stunden', '#/artikel', '#/dauerrechnungen', '#/ausgaben', '#/einstellungen'].some((p) => path.startsWith(p))));
		});
		const main = $('#main');
		for (const [re, fn] of routes) {
			const m = path.match(re);
			if (m) {
				try { current = (await fn(query, m)) || {}; }
				catch (e) { main.innerHTML = `<div class="page"><div class="empty"><span class="big">[ ! ]</span>${esc(e.message)}</div></div>`; }
				window.scrollTo(0, 0);
				return;
			}
		}
		main.innerHTML = '<div class="page"><div class="empty"><span class="big">[ ? ]</span>Seite nicht gefunden.</div></div>';
	}
	const go = (hash) => { if (location.hash === hash) route(); else location.hash = hash; };

	/** Seitenkopf: optional Zurück-Link (eigene Zeile, bündig mit dem Titel), Titel, Unterzeile, Aktionen. */
	const pageHead = (title, o = {}, actions = '') => `
		<header class="page-head">
			${o.back ? `<nav class="crumbs"><a class="back" href="${o.back[0]}">${icon('left')}<span>${esc(o.back[1])}</span></a>${(o.crumbs || []).map(([h, l]) => `<span class="sep">/</span><a href="${h}">${esc(l)}</a>`).join('')}</nav>` : ''}
			<div class="page-title"><div class="grow"><h1>${title}</h1>${o.sub ? `<p class="page-sub">${o.sub}</p>` : ''}</div>${actions ? `<div class="btns">${actions}</div>` : ''}</div>
		</header>`;

	/* ================================================================ Übersicht */

	/* ---------------------------------------------------------------- Übersicht: Widgets (verschieben, minimieren) */

	const DASH_DEFAULT = { top: ['kpis'], main: ['chart', 'open'], side: ['hours', 'limit', 'recurring', 'offers', 'drafts', 'years', 'top', 'activity'], min: [] };
	const dashLayout = () => {
		let l = null;
		try { l = JSON.parse(S.settings.dash_layout || 'null'); } catch (e) { l = null; }
		if (!l || !Array.isArray(l.main)) l = JSON.parse(JSON.stringify(DASH_DEFAULT));
		l.top = l.top || []; l.side = l.side || []; l.min = l.min || [];
		// neue Widgets (nach Updates) ergänzen, unbekannte entfernen
		const all = [...DASH_DEFAULT.top, ...DASH_DEFAULT.main, ...DASH_DEFAULT.side];
		['top', 'main', 'side'].forEach((z) => (l[z] = l[z].filter((id) => all.includes(id))));
		all.forEach((id) => { if (!['top', 'main', 'side'].some((z) => l[z].includes(id))) (DASH_DEFAULT.top.includes(id) ? l.top : DASH_DEFAULT.main.includes(id) ? l.main : l.side).push(id); });
		return l;
	};
	const pushDash = debounce(async () => {
		try { await api('settings_save', { dash_layout: S.settings.dash_layout }); } catch (e) { fail(e); }
	}, 400);
	/** Anordnung sofort lokal übernehmen, verzögert speichern. */
	const saveDash = (l) => { S.settings.dash_layout = JSON.stringify(l); pushDash(); };

	async function viewDashboard() {
		const main = $('#main');
		const d = await api('dashboard');
		const y = d.year;
		const prevYtd = d.revenue_prev_ytd;
		const delta = prevYtd ? Math.round(((d.revenue - prevYtd) / prevYtd) * 100) : null;
		const limitPct = d.limit ? Math.min(100, (d.revenue / d.limit) * 100) : 0;
		const hour = new Date().getHours();
		const hello = hour < 11 ? 'Guten Morgen' : hour < 18 ? 'Hallo' : 'Guten Abend';
		const dueRec = d.recurring_due;
		const plural = (n, one, many) => n + ' ' + (n === 1 ? one : many);

		/* Jedes Widget: Titel, Kurzfassung (für minimiert), Inhalt, optional Kopf-Aktion, Karte ohne Rahmen (kpis) */
		const W = {
			kpis: {
				title: 'Kennzahlen', bare: true,
				sum: `Umsatz ${moneyShort(d.revenue)} · offen ${moneyShort(d.open_sum)}${d.overdue_count ? ' · überfällig ' + moneyShort(d.overdue_sum) : ''}`,
				body: () => `<div class="grid g4 kpis">
					<div class="card kpi"><div class="label">Umsatz ${y}</div><div class="value num">${moneyShort(d.revenue)}</div>
						<div class="sub">${delta === null ? 'Vorjahr: ' + moneyShort(d.revenue_prev) : `<span class="delta ${delta >= 0 ? 'up' : 'down'}">${delta >= 0 ? '+' : ''}${delta} %</span> zum Vorjahreszeitraum`}</div></div>
					<div class="card kpi"><a class="cover" href="#/rechnungen?f=open" aria-label="Offene Rechnungen"></a><div class="label">Offen</div><div class="value num">${moneyShort(d.open_sum)}</div>
						<div class="sub">${plural(d.open_count, 'Rechnung', 'Rechnungen')}</div></div>
					<div class="card kpi ${d.overdue_count ? 'alert' : ''}"><a class="cover" href="#/rechnungen?f=overdue" aria-label="Überfällige Rechnungen"></a><div class="label">${d.overdue_count ? icon('alert') : ''}Überfällig</div><div class="value num">${moneyShort(d.overdue_sum)}</div>
						<div class="sub">${d.overdue_count ? plural(d.overdue_count, 'Rechnung', 'Rechnungen') : 'alles im grünen Bereich'}</div></div>
					<div class="card kpi"><a class="cover" href="#/dauerrechnungen" aria-label="Dauerrechnungen"></a><div class="label">Dauerrechnungen</div><div class="value num">${moneyShort(d.recurring_yearly)}</div>
						<div class="sub">pro Jahr · ${d.recurring_count} aktiv</div></div>
				</div>`,
			},
			chart: {
				title: 'Umsatz nach Monat',
				sum: `${y}: ${moneyShort(d.revenue)}`,
				head: `<div class="legend"><span><i style="background:var(--prev)"></i>${y - 1}</span><span><i style="background:var(--cur)"></i>${y}</span></div>`,
				body: () => `<div class="card-body"><div class="chart" id="chart"></div></div>`,
				after: () => monthChart($('#chart'), d.months, d.months_prev, y),
			},
			open: {
				title: 'Offene Rechnungen',
				sum: d.open_count ? `${plural(d.open_count, 'Rechnung', 'Rechnungen')} · ${money(d.open_sum)}` : 'alles bezahlt',
				head: `<a class="btn sm ghost" href="#/rechnungen?f=open">Alle ${icon('right')}</a>`,
				body: () => `<div class="card-body" style="padding-top:4px">${d.open.length ? `<div class="list">${d.open.map((i) => `
					<div class="list-item">
						<button class="check ${i.state === 'partial' ? 'half' : ''}" data-pay="${i.id}" title="Zahlungseingang abhaken" aria-label="Rechnung ${esc(i.number)} als bezahlt abhaken">${icon('check')}</button>
						<a class="li-main" href="#/rechnung/${i.id}" style="text-decoration:none"><div class="li-title">${withRec(esc(i.recipient.name), i.is_recurring)}</div>
							<div class="li-sub"><span class="mono">${esc(i.number)}</span> · ${date(i.invoice_date)}${i.state === 'partial' ? ` · <span style="color:var(--warn);font-weight:700">teilweise bezahlt</span>` : ''}${i.days_overdue > 0 ? ` · <span style="color:var(--bad);font-weight:700">${i.days_overdue} Tage überfällig</span>` : i.state !== 'partial' && i.payment_days > 0 ? ' · fällig ' + date(i.due_date) : ''}</div></a>
						<b class="num">${money(i.open)}</b>
					</div>`).join('')}</div>` : '<div class="empty"><span class="big">[ ✓ ]</span>Alles bezahlt.</div>'}</div>`,
			},
			limit: d.small_business ? {
				title: `Kleinunternehmergrenze ${y}`,
				sum: `${Math.round(limitPct)} % · noch ${moneyShort(Math.max(0, d.limit - d.revenue))}`,
				head: `<span class="muted num" style="font-size:12.5px">${Math.round(limitPct)} %</span>`,
				body: () => `<div class="card-body" style="padding-top:8px"><div class="meter ${limitPct > 90 ? 'bad' : limitPct > 75 ? 'warn' : ''}" style="margin-top:0"><i style="width:${limitPct}%"></i></div>
					<div class="muted" style="font-size:12.5px;margin-top:8px">${money(d.revenue)} von ${money(d.limit)} · noch ${money(Math.max(0, d.limit - d.revenue))} Spielraum</div></div>`,
			} : null,
			hours: {
				title: 'Stunden',
				sum: `${fmtH(d.hours.open_h)} offen · diese Woche ${fmtH(d.hours.week)}`,
				head: `<a class="btn sm ghost" href="#/stunden">Alle ${icon('right')}</a>`,
				body: () => `<div class="card-body" style="padding-top:8px">
					<div class="hours-mini"><span><b class="num">${fmtH(d.hours.week)}</b> diese Woche</span><span><b class="num">${fmtH(d.hours.month)}</b> diesen Monat</span><span><b class="num">${fmtH(d.hours.open_h)}</b> offen${d.hours.open_val ? ' · ' + money(d.hours.open_val) : ''}</span></div>
					<details class="hours-add"><summary>${icon('plus')} Stunden erfassen</summary><div id="dhform" style="margin-top:12px"></div></details>
					${d.hours.open.length ? `<div class="list" style="margin-top:6px">${d.hours.open.slice(0, 4).map((o) => `
						<div class="list-item"><div class="li-main"><div class="li-title">${esc(o.customer_name)}</div><div class="li-sub">${fmtH(o.hours)} offen${o.rate ? ' · ' + money(o.value) : ''}</div></div>
						<a class="btn sm" href="#/rechnung/neu?kunde=${o.customer_id}&stunden=1">${icon('file')} Abrechnen</a></div>`).join('')}</div>` : ''}
				</div>`,
				after: () => hourForm($('#dhform'), { compact: true, projects: d.hours.projects, id: 'w', onSaved: () => viewDashboard() }),
			},
			recurring: {
				title: 'Nächste Dauerrechnungen',
				sum: d.recurring_upcoming.length ? `${d.recurring_upcoming.length} in 60 Tagen · nächste ${date(d.recurring_upcoming[0].next_date)}` : 'keine in den nächsten 60 Tagen',
				head: `<a class="btn sm ghost" href="#/dauerrechnungen" aria-label="Alle Dauerrechnungen">${icon('right')}</a>`,
				body: () => `<div class="card-body" style="padding-top:4px">${d.recurring_upcoming.length ? `<div class="list">${d.recurring_upcoming.map((r) => `
					<a class="list-item" href="#/dauerrechnungen?id=${r.id}"><div class="li-main"><div class="li-title">${withRec(esc(r.customer_name), true, 'Dauerrechnung')}</div>
						<div class="li-sub">${r.due ? '<b style="color:var(--accent)">fällig</b>' : date(r.next_date)} · ${esc(r.title || INTERVALS[r.interval_months])}${r.email_missing ? ' · <span style="color:var(--warn)">E-Mail fehlt</span>' : ''}</div></div>
						<b class="num">${money(r.gross)}</b></a>`).join('')}</div>` : '<div class="empty">In den nächsten 60 Tagen keine.</div>'}</div>`,
			},
			offers: {
				title: 'Offene Angebote',
				sum: d.offers_open.length ? `${plural(d.offers_open.length, 'Angebot', 'Angebote')} · ${money(d.offers_open.reduce((a, o) => a + o.gross, 0))}` : 'keine offenen',
				head: `<a class="btn sm ghost" href="#/angebote?f=sent" aria-label="Alle Angebote">${icon('right')}</a>`,
				body: () => `<div class="card-body" style="padding-top:4px">${d.offers_open.length ? `<div class="list">${d.offers_open.map((o) => `
					<a class="list-item" href="#/angebot/${o.id}"><div class="li-main"><div class="li-title">${esc(o.recipient.name)}</div><div class="li-sub"><span class="mono">${esc(o.number)}</span> · gültig bis ${date(o.valid_until)}</div></div><b class="num">${money(o.gross)}</b></a>`).join('')}</div>` : '<div class="empty">Keine offenen Angebote.</div>'}</div>`,
			},
			drafts: {
				title: 'Entwürfe',
				sum: d.drafts.length ? plural(d.drafts.length, 'Entwurf', 'Entwürfe') : 'keine',
				body: () => `<div class="card-body" style="padding-top:4px">${d.drafts.length ? `<div class="list">${d.drafts.map((i) => `
					<a class="list-item" href="#/${i.kind === 'offer' ? 'angebot' : 'rechnung'}/${i.id}"><div class="li-main"><div class="li-title">${withRec(esc(i.recipient.name || 'Ohne Empfänger'), i.is_recurring)}</div><div class="li-sub">${i.kind === 'offer' ? 'Angebot' : 'Rechnung'} · zuletzt ${date(i.updated_at)}</div></div><b class="num">${money(i.gross)}</b></a>`).join('')}</div>` : '<div class="empty">Keine Entwürfe.</div>'}</div>`,
			},
			years: {
				title: 'Umsatz pro Jahr',
				sum: d.years.length ? `${d.years[0].y}–${d.years[d.years.length - 1].y}` : '—',
				body: () => `<div class="card-body">${yearBars(d.years)}</div>`,
			},
			top: {
				title: `Top-Kunden ${y}`,
				sum: d.top_customers.length ? `${esc(d.top_customers[0].name)} · ${moneyShort(+d.top_customers[0].revenue)}` : '—',
				body: () => `<div class="card-body" style="padding-top:4px">${d.top_customers.length ? `<div class="list">${d.top_customers.map((c) => `
					<a class="list-item" href="#/kunde/${c.id}"><div class="li-main"><div class="li-title">${esc(c.name)}</div></div><b class="num">${money(c.revenue)}</b></a>`).join('')}</div>` : '<div class="empty">Noch keine Rechnungen in diesem Jahr.</div>'}</div>`,
			},
			activity: {
				title: 'Zuletzt',
				sum: d.activity.length ? esc(d.activity[0].text).slice(0, 60) : '—',
				body: () => `<div class="card-body"><div class="timeline">${d.activity.map((a) => `
					<div class="tl"><div>${a.invoice_id ? `<a href="#/rechnung/${a.invoice_id}">${esc(a.text)}</a>` : esc(a.text)}${a.customer ? ` <span class="muted">· ${esc(a.customer)}</span>` : ''}<div class="when">${relTime(a.created_at)}</div></div></div>`).join('')}</div></div>`,
			},
		};

		let L = dashLayout();
		const widget = (id) => {
			const w = W[id];
			if (!w) return '';
			const min = L.min.includes(id);
			return `<section class="widget ${w.bare && !min ? 'bare' : 'card'} ${min ? 'is-min' : ''}" data-w="${id}" aria-label="${esc(w.title)}">
				<div class="w-head">
					<button type="button" class="w-grip" data-grip title="Ziehen zum Verschieben (oder Pfeiltasten)" aria-label="${esc(w.title)} verschieben">${icon('grip')}</button>
					<h2>${esc(w.title)}</h2>
					${min ? `<span class="w-sum">${w.sum}</span>` : `<span class="w-actions">${w.head || ''}</span>`}
					<button type="button" class="w-min" data-min title="${min ? 'Aufklappen' : 'Minimieren'}" aria-label="${esc(w.title)} ${min ? 'aufklappen' : 'minimieren'}" aria-expanded="${!min}">${icon(min ? 'down' : 'up')}</button>
				</div>
				${min ? '' : `<div class="w-body">${w.body()}</div>`}
			</section>`;
		};
		const zone = (z) => `<div class="dash-col" data-zone="${z}">${L[z].map(widget).join('')}</div>`;

		main.innerHTML = `<div class="page">
			<div class="app-name">Rechnungsprogramm</div>
			${pageHead(S.settings.owner ? `${hello}, ${esc(S.settings.owner.split(' ')[0])}.` : `${hello}.`, { sub: new Date().toLocaleDateString('de-AT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) },
				`<a class="btn" href="#/angebot/neu">${icon('offer')} Neues Angebot</a><a class="btn primary" href="#/rechnung/neu">${icon('plus')} Neue Rechnung</a>`)}

			${!S.settings.company || !S.settings.iban ? `<div class="note-warn" style="margin-bottom:16px">${icon('alert')}<span>Bitte zuerst <a href="#/einstellungen/firma">Firmendaten</a> und <a href="#/einstellungen/bank">Bankverbindung</a> eintragen – sie stehen auf jeder Rechnung.</span></div>` : ''}
			${dueRec.length ? `<div class="card card-pad" style="margin-bottom:16px;border-color:var(--accent-line);background:var(--accent-soft)">
				<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
					<div style="flex:1;min-width:220px"><h2>${dueRec.length} Dauerrechnung${dueRec.length > 1 ? 'en sind' : ' ist'} fällig</h2>
					<div class="muted" style="margin-top:4px">${dueRec.map((r) => esc(r.customer_name) + ' (' + money(r.gross) + ')').join(' · ')}</div></div>
					<button class="btn primary" data-run-due>${icon('play')} Jetzt alle erstellen</button>
				</div></div>` : ''}

			<div class="dash" id="dash">
				${zone('top')}
				<div class="dash-cols">${zone('main')}${zone('side')}</div>
			</div>
			<div class="dash-foot"><button type="button" class="btn sm ghost" data-dash-reset>Anordnung zurücksetzen</button><span class="muted">Widgets am Griff ${icon('grip')} ziehen, mit ${icon('up')} minimieren.</span></div>
		</div>`;

		const readLayout = () => {
			const l = { top: [], main: [], side: [], min: L.min };
			$$('.dash-col', main).forEach((c) => (l[c.dataset.zone] = $$('.widget', c).map((w) => w.dataset.w)));
			return l;
		};
		const bind = () => {
			Object.entries(W).forEach(([id, w]) => { if (w && w.after && !L.min.includes(id) && $(`[data-w="${id}"]`, main)) w.after(); });
			$$('[data-pay]', main).forEach((b) => (b.onclick = () => quickPay(+b.dataset.pay, b, () => viewDashboard())));
			$$('[data-min]', main).forEach((b) => (b.onclick = () => {
				const id = b.closest('.widget').dataset.w;
				L.min = L.min.includes(id) ? L.min.filter((x) => x !== id) : [...L.min, id];
				const el = b.closest('.widget');
				el.outerHTML = widget(id);
				saveDash(L); bind();
				$(`[data-w="${id}"] [data-min]`, main)?.focus();
			}));
			$$('[data-grip]', main).forEach((g) => {
				g.onpointerdown = (e) => startDrag(e, g.closest('.widget'));
				g.onkeydown = (e) => keyMove(e, g.closest('.widget'));
			});
		};

		/* Ziehen mit Maus und Finger (Pointer Events): Platzhalter wandert mit, Loslassen setzt das Widget dort ab */
		const startDrag = (e, el) => {
			if (e.button !== undefined && e.button !== 0) return;
			e.preventDefault();
			const r = el.getBoundingClientRect();
			const ph = document.createElement('div');
			ph.className = 'w-placeholder';
			ph.style.height = r.height + 'px';
			el.after(ph);
			const dx = e.clientX - r.left, dy = e.clientY - r.top;
			Object.assign(el.style, { position: 'fixed', left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', zIndex: 60, pointerEvents: 'none' });
			el.classList.add('dragging');
			document.body.classList.add('is-dragging');
			const move = (ev) => {
				el.style.left = ev.clientX - dx + 'px';
				el.style.top = ev.clientY - dy + 'px';
				const under = document.elementFromPoint(ev.clientX, ev.clientY);
				const col = under?.closest('.dash-col') || (under?.closest('.dash-cols') ? null : null);
				if (col) {
					const after = $$('.widget:not(.dragging)', col).find((w) => { const b = w.getBoundingClientRect(); return ev.clientY < b.top + b.height / 2; });
					if (after) col.insertBefore(ph, after); else col.appendChild(ph);
				}
				// am Rand automatisch scrollen
				if (ev.clientY < 60) window.scrollBy(0, -14); else if (ev.clientY > innerHeight - 60) window.scrollBy(0, 14);
			};
			const up = () => {
				removeEventListener('pointermove', move);
				removeEventListener('pointerup', up);
				removeEventListener('pointercancel', up);
				el.removeAttribute('style');
				el.classList.remove('dragging');
				document.body.classList.remove('is-dragging');
				ph.replaceWith(el);
				L = readLayout(); saveDash(L);
				const id = el.dataset.w;
				// Diagramm passt sich der neuen Breite an
				if (W[id]?.after && !L.min.includes(id)) W[id].after();
				$('[data-grip]', el)?.focus();
			};
			addEventListener('pointermove', move);
			addEventListener('pointerup', up);
			addEventListener('pointercancel', up);
		};
		/* Tastatur: ↑/↓ innerhalb der Spalte, ←/→ in die Nachbarspalte */
		const keyMove = (e, el) => {
			const zones = $$('.dash-col', main);
			const col = el.parentElement;
			if (e.key === 'ArrowUp' && el.previousElementSibling) col.insertBefore(el, el.previousElementSibling);
			else if (e.key === 'ArrowDown' && el.nextElementSibling) col.insertBefore(el.nextElementSibling, el);
			else if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
				const to = zones[zones.indexOf(col) + (e.key === 'ArrowLeft' ? -1 : 1)];
				if (!to) return;
				to.appendChild(el);
			} else return;
			e.preventDefault();
			L = readLayout(); saveDash(L);
			$('[data-grip]', el).focus();
			const id = el.dataset.w;
			if (W[id]?.after && !L.min.includes(id)) W[id].after();
		};

		bind();
		$('[data-dash-reset]', main).onclick = () => { L = JSON.parse(JSON.stringify(DASH_DEFAULT)); saveDash(L); viewDashboard(); toast('Anordnung zurückgesetzt'); };
		api('update_check').then((u) => {
			if (u.newer && $('#main .page')) $('#main .app-name')?.insertAdjacentHTML('afterend', `<a class="note-ok" href="#/einstellungen/update" style="margin:-12px 0 20px;text-decoration:none">${icon('download')}<span>Update auf Version ${esc(u.latest)} verfügbar – jetzt ansehen</span></a>`);
		}).catch(() => {});
		$('[data-run-due]', main)?.addEventListener('click', runDue);
	}

	function relTime(ts) {
		if (!ts) return '';
		const d = new Date(ts.replace(' ', 'T'));
		const diff = (Date.now() - d) / 1000;
		if (diff < 60) return 'gerade eben';
		if (diff < 3600) return 'vor ' + Math.round(diff / 60) + ' Min.';
		if (diff < 86400) return 'vor ' + Math.round(diff / 3600) + ' Std.';
		if (diff < 86400 * 7) return 'vor ' + Math.round(diff / 86400) + ' Tag' + (Math.round(diff / 86400) > 1 ? 'en' : '');
		return d.toLocaleDateString('de-AT');
	}

	function yearBars(years) {
		if (!years.length) return '<div class="empty">Noch keine Daten.</div>';
		const max = Math.max(...years.map((y) => +y.revenue));
		return `<div style="display:flex;flex-direction:column;gap:7px">${years.slice().reverse().map((y) => `
			<div style="display:grid;grid-template-columns:42px minmax(0,1fr) 92px;gap:10px;align-items:center;font-size:12.5px" title="${y.n} Rechnungen">
				<span class="mono muted">${y.y}</span>
				<div style="height:8px;border-radius:99px;background:var(--line-2);overflow:hidden"><div style="height:100%;width:${(y.revenue / max) * 100}%;background:${+y.y === new Date().getFullYear() ? 'var(--accent)' : 'var(--ink-2)'};border-radius:99px;opacity:${+y.y === new Date().getFullYear() ? 1 : .55}"></div></div>
				<b class="num" style="text-align:right">${moneyShort(+y.revenue)}</b>
			</div>`).join('')}</div>`;
	}

	/** Säulen je Monat: Vorjahr (orange) und aktuelles Jahr (blau), Tooltip pro Monat. */
	function monthChart(el, cur, prev, year) {
		const W = 640, H = 220, padL = 44, padB = 24, padT = 8;
		const max = Math.max(100, ...cur, ...prev);
		const step = niceStep(max / 4);
		const top = Math.ceil(max / step) * step;
		const ih = H - padB - padT, iw = W - padL;
		const cw = iw / 12, bw = Math.min(16, (cw - 10) / 2);
		const yv = (v) => padT + ih - (v / top) * ih;
		let s = `<svg viewBox="0 0 ${W} ${H}" role="img" aria-label="Umsatz pro Monat ${year} und ${year - 1}">`;
		for (let v = 0; v <= top + 0.01; v += step) {
			s += `<line class="grid-line" x1="${padL}" x2="${W}" y1="${yv(v)}" y2="${yv(v)}"/><text class="axis" x="${padL - 8}" y="${yv(v) + 4}" text-anchor="end">${v >= 1000 ? (v / 1000).toLocaleString('de-AT') + 'k' : v}</text>`;
		}
		const thisMonth = new Date().getFullYear() === year ? new Date().getMonth() : 11;
		for (let m = 0; m < 12; m++) {
			const x = padL + m * cw + cw / 2;
			const bar = (v, xx, cls) => {
				if (v <= 0) return '';
				const h = Math.max(2, (v / top) * ih), y0 = padT + ih;
				const r = Math.min(4, h / 2, bw / 2);
				return `<path class="${cls}" d="M${xx} ${y0}V${y0 - h + r}q0 -${r} ${r} -${r}h${bw - 2 * r}q${r} 0 ${r} ${r}V${y0}z"/>`;
			};
			s += `<g class="col" data-m="${m}"><rect class="hit" x="${padL + m * cw + 2}" y="${padT}" width="${cw - 4}" height="${ih}" rx="6"/>
				${bar(prev[m], x - bw - 1, 'bar-prev')}${bar(m <= thisMonth ? cur[m] : 0, x + 1, 'bar-cur')}</g>
				<text class="axis" x="${x}" y="${H - 6}" text-anchor="middle">${MONTHS[m]}</text>`;
		}
		s += '</svg>';
		el.innerHTML = s + '<div class="tip hide"></div>';
		const tip = $('.tip', el);
		$$('g.col', el).forEach((g) => {
			const m = +g.dataset.m;
			const show = () => {
				const r = g.getBoundingClientRect(), pr = el.getBoundingClientRect();
				tip.innerHTML = `<b>${MONTHS_LONG[m]}</b><br><i style="background:var(--cur)"></i>${year}: <b>${money(m <= thisMonth ? cur[m] : 0)}</b><br><i style="background:var(--prev)"></i>${year - 1}: ${money(prev[m])}`;
				tip.style.left = Math.min(Math.max(r.left - pr.left + r.width / 2, 70), pr.width - 70) + 'px';
				tip.style.top = (r.top - pr.top + 6) + 'px';
				tip.classList.remove('hide');
			};
			g.addEventListener('mouseenter', show);
			g.addEventListener('touchstart', show, { passive: true });
			g.addEventListener('mouseleave', () => tip.classList.add('hide'));
		});
	}
	function niceStep(raw) {
		const p = Math.pow(10, Math.floor(Math.log10(raw)));
		const n = raw / p;
		return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p;
	}

	async function quickPay(id, btn, after) {
		btn.classList.add('on', 'pop');
		try {
			await api('invoice_pay', { id, date: S.today });
			await refresh();
			toast('Vollständig bezahlt', { action: 'Rückgängig', onAction: async () => { await api('invoice_unpay', { id }); await refresh(); after?.(); } });
			setTimeout(() => after?.(), 350);
		} catch (e) { btn.classList.remove('on'); fail(e); }
	}

	async function runDue() {
		const ok = await confirmDialog('Fällige Dauerrechnungen erstellen?', 'Je nach Einstellung werden die Rechnungen als Entwurf angelegt, ausgestellt oder ausgestellt und per E-Mail versendet.', 'Erstellen');
		if (!ok) return;
		try {
			const r = await api('recurring_run_due', {});
			await refresh();
			const errs = r.done.filter((d) => d.error || d.note);
			toast(r.done.length + ' Dauerrechnung' + (r.done.length === 1 ? '' : 'en') + ' erstellt' + (errs.length ? ' – ' + errs.map((e) => e.error || e.note).join(' / ') : ''), errs.length ? { error: true } : {});
			route();
		} catch (e) { fail(e); }
	}

	/* ================================================================ Rechnungen */

	async function viewInvoices(q) {
		const main = $('#main');
		const years = [...new Set(S.invoices.map((i) => i.invoice_date.slice(0, 4)))].sort().reverse();
		let f = q.f || store.get('inv.f', 'all');
		let year = q.year || store.get('inv.year', '');
		let search = q.q || '';
		const filters = [['all', 'Alle'], ['open', 'Offen'], ['overdue', 'Überfällig'], ['paid', 'Bezahlt'], ['draft', 'Entwürfe'], ['cancelled', 'Storniert']];
		const match = (i) => {
			if (f === 'open' && !(i.state === 'open' || i.state === 'overdue' || i.state === 'partial')) return false;
			if (f !== 'all' && f !== 'open' && f !== 'cancelled' && i.state !== f) return false;
			if (f === 'cancelled' && !(i.state === 'cancelled' || i.state === 'storno')) return false;
			if (year && i.invoice_date.slice(0, 4) !== year) return false;
			if (search) {
				const n = norm(search);
				if (!norm([i.number, i.recipient.name, i.recipient.number, i.item_names, i.subject, dec(i.gross)].join(' ')).includes(n)) return false;
			}
			return true;
		};
		main.innerHTML = `<div class="page">
			${pageHead('Rechnungen', { sub: `${S.invoices.filter((i) => i.status !== 'draft').length} Rechnungen seit ${years[years.length - 1] || ''}` },
				`<button class="btn" data-export>${icon('download')} Export</button><a class="btn primary" href="#/rechnung/neu">${icon('plus')} Neue Rechnung</a>`)}
			<div class="toolbar">
				<div class="chips" id="chips"></div>
				<div class="grow"></div>
				<select id="year" style="width:auto"><option value="">Alle Jahre</option>${years.map((y) => `<option ${y === year ? 'selected' : ''}>${y}</option>`).join('')}</select>
				<label class="search"><span class="sr">Suchen</span>${icon('search')}<input type="search" id="q" placeholder="Nummer, Kunde, Leistung …" value="${esc(search)}"></label>
			</div>
			<div class="card"><div class="table-wrap" id="list"></div></div>
		</div>`;
		const draw = () => {
			store.set('inv.f', f); store.set('inv.year', year);
			const base = S.invoices.filter((i) => !year || i.invoice_date.slice(0, 4) === year);
			const count = (k) => base.filter((i) => k === 'all' ? true : k === 'open' ? (i.state === 'open' || i.state === 'overdue' || i.state === 'partial') : k === 'cancelled' ? (i.state === 'cancelled' || i.state === 'storno') : i.state === k).length;
			$('#chips').innerHTML = filters.map(([k, l]) => `<button class="chip ${f === k ? 'on' : ''}" data-f="${k}">${l} <span class="n">${count(k)}</span></button>`).join('');
			$$('#chips .chip').forEach((c) => (c.onclick = () => { f = c.dataset.f; draw(); }));
			const rows = S.invoices.filter(match);
			const sum = rows.filter((i) => i.status !== 'draft').reduce((a, i) => a + i.gross, 0);
			const open = rows.filter((i) => i.state === 'open' || i.state === 'overdue' || i.state === 'partial').reduce((a, i) => a + i.open, 0);
			$('#list').innerHTML = rows.length ? `<table class="table resp"><thead><tr><th style="width:44px" title="Bezahlt">✓</th><th>Nr.</th><th>Kunde</th><th class="hide-m">Datum</th><th>Status</th><th class="th-r">Betrag</th></tr></thead><tbody>
				${rows.map((i) => `<tr class="click" data-id="${i.id}">
					<td class="m-a"><button class="check ${i.state === 'paid' ? 'on' : i.state === 'partial' ? 'half' : ''} ${['open', 'overdue', 'paid', 'partial'].includes(i.state) ? '' : 'na'}" data-pay="${i.id}" aria-label="Bezahlt umschalten" title="${i.state === 'paid' ? 'Bezahlt am ' + date(i.paid_at) + ' – zum Zurücknehmen klicken' : i.state === 'partial' ? 'Teilweise bezahlt – Restbetrag ' + money(i.open) + ' abhaken' : 'Zahlungseingang abhaken'}">${icon('check')}</button></td>
					<td class="m-hide mono">${esc(i.number || '—')}</td>
					<td class="m-b strong"><div>${withRec(esc(i.recipient.name || 'Ohne Empfänger'), i.is_recurring)}</div><div class="sub">${esc(i.item_names || '')}</div></td>
					<td class="m-c muted nowrap"><span class="show-m mono">${esc(i.number || 'Entwurf')} · </span>${date(i.invoice_date)}</td>
					<td class="m-e">${badge(i.state, i.days_overdue > 0 ? ' · ' + i.days_overdue + ' T.' : '')}${i.sent_at ? ` <span class="muted" title="Per E-Mail versendet am ${date(i.sent_at)}">${icon('mail')}</span>` : ''}</td>
					<td class="m-d td-r num strong" style="${i.gross < 0 ? 'color:var(--muted)' : ''}">${money(i.gross)}${i.state === 'partial' ? `<div class="sub" style="font-weight:600">offen ${money(i.open)}</div>` : ''}</td>
				</tr>`).join('')}</tbody></table>
				<div class="sumbar"><span>${rows.length} Einträge</span><span>Summe <b class="num">${money(sum)}</b></span>${open ? `<span>davon offen <b class="num">${money(open)}</b></span>` : ''}</div>`
				: '<div class="empty"><span class="big">[ ]</span>Keine Rechnungen gefunden.</div>';
			$$('#list tr[data-id]').forEach((tr) => (tr.onclick = (e) => { if (!e.target.closest('.check')) go('#/rechnung/' + tr.dataset.id); }));
			$$('#list [data-pay]').forEach((b) => (b.onclick = async (e) => {
				e.stopPropagation();
				const id = +b.dataset.pay;
				const inv = S.invoices.find((i) => +i.id === id);
				if (inv.state === 'paid') {
					try { await api('invoice_unpay', { id }); await refresh(); toast('Als offen markiert'); draw(); } catch (er) { fail(er); }
				} else quickPay(id, b, draw);
			}));
		};
		$('#year').onchange = (e) => { year = e.target.value; draw(); };
		$('#q').oninput = debounce((e) => { search = e.target.value; draw(); }, 120);
		$('[data-export]').onclick = () => exportDialog(year);
		draw();
		if (search) $('#q').focus();
	}

	async function exportDialog(year) {
		const years = [...new Set(S.invoices.map((i) => i.invoice_date.slice(0, 4)))].sort().reverse();
		const r = await confirmDialog('Export', 'Für Steuerberatung oder Ablage – als Tabelle (CSV, öffnet in Excel/Numbers) oder alle Rechnungen als PDF in einer ZIP-Datei.', 'Herunterladen', {
			extra: `<div class="form-grid" style="margin-top:16px"><label class="field c3"><span>Jahr</span><select name="year"><option value="">Alle Jahre</option>${years.map((y) => `<option ${y === year ? 'selected' : ''}>${y}</option>`).join('')}</select></label>
				<label class="field c3"><span>Format</span><select name="fmt"><option value="export">Rechnungen als CSV</option><option value="export_pdfs">Rechnungen als PDF (ZIP)</option><option value="export_expenses">Ausgaben als CSV</option></select></label></div>`,
		});
		if (r) location.href = 'api.php?a=' + r.fmt + (r.year ? '&year=' + r.year : '');
	}

	/* ---------------------------------------------------------------- Rechnung: Ansicht oder Editor */

	async function viewInvoice(id, q, kind = 'invoice') {
		if (!id) {
			const c = q.kunde ? customerById(q.kunde) : null;
			const offer = kind === 'offer';
			return invoiceEditor({
				kind, valid_until: offer ? addDays(S.today, +S.settings.offer_days || 30) : null,
				id: 0, customer_id: c ? +c.id : null, recipient: c ? { name: c.name, number: c.number, lines: c.lines || [], email: c.email } : { name: '', number: '', lines: [], email: '' },
				invoice_date: S.today, payment_days: c && c.payment_days !== null && c.payment_days !== '' ? +c.payment_days : +S.settings.payment_days,
				service_date: S.today, period_from: null, period_to: null, subject: '', greeting: '', intro: offer ? S.settings.offer_intro : S.settings.intro, outro: offer ? S.settings.offer_outro : S.settings.outro, note: '',
				items: [{ sku: '', name: '', description: '', qty: 1, unit: '', price: 0, discount: 0 }], status: 'draft', _new: true, _fromCustomer: !!c, _hours: offer ? '' : (q.stunden || ''),
			});
		}
		const inv = await api('invoice', undefined, { query: { id } });
		if (inv.status === 'draft') return invoiceEditor(inv);
		return inv.kind === 'offer' ? offerDetail(inv) : invoiceDetail(inv);
	}

	function invoiceDetail(inv) {
		const main = $('#main');
		const s = inv.state;
		const isInv = inv.kind === 'invoice';
		const canPay = isInv && (s === 'open' || s === 'overdue' || s === 'partial');
		const pdfUrl = 'api.php?a=pdf&id=' + inv.id;
		const title = (inv.kind === 'storno' ? 'Stornorechnung ' : 'Rechnung ') + inv.number;
		main.innerHTML = `<div class="page">
			${pageHead(esc(title), { back: ['#/rechnungen', 'Rechnungen'], crumbs: inv.customer ? [['#/kunde/' + inv.customer.id, inv.recipient.name]] : [] },
				`<a class="btn" href="${pdfUrl}&dl=1">${icon('download')}<span class="hide-m">PDF</span></a>
				<button class="btn" data-share>${icon('share')}<span class="hide-m">Teilen</span></button>
				${inv.status !== 'draft' && isInv && s !== 'cancelled' ? `<button class="btn ${inv.sent_at ? '' : 'primary'}" data-mail>${icon('send')} ${inv.sent_at ? 'Erneut senden' : 'Per E-Mail senden'}</button>` : ''}`)}
			<div class="grid g-main">
				<div class="grid" style="align-content:start">
					<div class="card">
						<div class="status-hero">
							<div class="grow"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:6px">${badge(s, s === 'overdue' ? ' seit ' + inv.days_overdue + ' Tagen' : s === 'paid' ? ' am ' + date(inv.paid_at) : '')}${s === 'partial' && inv.days_overdue > 0 ? `<span class="badge b-overdue">${inv.days_overdue} Tage überfällig</span>` : ''}
								${inv.sent_at ? `<span class="badge plain">${icon('mail')} versendet ${date(inv.sent_at)}</span>` : ''}
								${inv.reminder_level ? `<span class="badge b-warn">${inv.reminder_level}× erinnert</span>` : ''}
								${inv.source === 'import' ? '<span class="badge plain" title="Aus den alten PDF-Rechnungen übernommen">Import</span>' : ''}</div>
								<div class="amount num">${money(inv.gross)}</div>
								${inv.paid > 0 && s !== 'paid' ? `<div class="pay-progress"><div class="meter"><i style="width:${Math.min(100, inv.paid / inv.gross * 100)}%;background:var(--ok)"></i></div><div class="muted" style="font-size:13px;margin-top:6px">bezahlt <b class="num" style="color:var(--ink)">${money(inv.paid)}</b> · offen <b class="num" style="color:var(--ink)">${money(inv.open)}</b></div></div>` : ''}
								<div class="muted">${withRec(esc(inv.recipient.name), inv.is_recurring)} · ${date(inv.invoice_date)}</div></div>
							<div class="btns">
								${canPay ? `<button class="btn primary" data-pay>${icon('check')} Zahlung erhalten</button>` : ''}
								${(inv.payments || []).length ? `<button class="btn" data-unpay>${(inv.payments || []).length > 1 ? 'Letzte Zahlung zurücknehmen' : 'Zahlung zurücknehmen'}</button>` : ''}
								${inv.days_overdue > 0 || (s === 'open' && inv.payment_days > 0) ? `<button class="btn" data-remind>${icon('bell')} Erinnerung</button>` : ''}
							</div>
						</div>
						<dl class="facts">
							<div><dt>Rechnungsdatum</dt><dd>${date(inv.invoice_date)}</dd></div>
							<div><dt>${inv.period_from ? 'Leistungszeitraum' : 'Leistungsdatum'}</dt><dd>${inv.period_from ? date(inv.period_from) + ' – ' + date(inv.period_to) : date(inv.service_date || inv.invoice_date)}</dd></div>
							<div><dt>Fällig</dt><dd>${inv.payment_days > 0 ? date(inv.due_date) : 'sofort'}</dd></div>
							<div><dt>Kunden Nr.</dt><dd class="mono">${esc(inv.recipient.number || '—')}</dd></div>
							${inv.ref ? `<div><dt>Storno zu</dt><dd><a href="#/rechnung/${inv.ref.id}">${esc(inv.ref.number)}</a></dd></div>` : ''}
							${inv.storno ? `<div><dt>Storniert mit</dt><dd><a href="#/rechnung/${inv.storno.id}">${esc(inv.storno.number)}</a></dd></div>` : ''}
							${inv.from_offer ? `<div><dt>Aus Angebot</dt><dd><a href="#/angebot/${inv.from_offer.id}" class="mono">${esc(inv.from_offer.number)}</a></dd></div>` : ''}
						</dl>
					</div>
					<div class="card" style="overflow:hidden">
						<div class="preview-head">${icon('file')} <span style="flex:1">${esc(title)}.pdf</span>
							${inv.original_file ? `<a class="btn sm" href="api.php?a=original&id=${inv.id}" target="_blank" rel="noopener" title="Das alte, damals versendete PDF">${icon('eye')} Original</a>` : ''}
							<a class="btn sm" href="${pdfUrl}" target="_blank" rel="noopener">${icon('eye')} In neuem Tab</a></div>
						<iframe class="pdf-frame" src="${pdfUrl}#view=FitH&navpanes=0" title="Rechnung als PDF"></iframe>
					</div>
				</div>
				<div class="grid" style="align-content:start">
					<div class="card card-pad">
						<h3 style="margin-bottom:10px">Empfänger</h3>
						<div style="line-height:1.55">${inv.recipient.lines.map((l, i) => i === 0 ? `<b>${esc(l)}</b>` : esc(l)).join('<br>')}</div>
						<label class="field" style="margin-top:14px"><span>E-Mail für den Versand</span><input type="email" id="email" value="${esc(inv.recipient.email || inv.customer?.email || '')}" placeholder="kunde@example.com"></label>
					</div>
					<div class="card card-pad">
						<h3 style="margin-bottom:10px">Aktionen</h3>
						<div class="btns" style="flex-direction:column;align-items:stretch">
							<button class="btn" data-dup>${icon('copy')} Als neue Rechnung kopieren</button>
							${isInv && inv.status === 'issued' ? `<button class="btn danger" data-cancel>${icon('ban')} Stornieren</button>` : ''}
							${!inv.sent_at && isInv ? `<button class="btn ghost" data-marksent>${icon('check')} Als versendet markieren</button>` : ''}
							<button class="btn ghost danger" data-delete>${icon('trash')} ${inv.kind === 'storno' ? 'Stornorechnung' : 'Rechnung'} löschen</button>
						</div>
						<p class="muted" style="font-size:12.5px;margin-top:12px">Ausgestellte Rechnungen lassen sich nicht mehr ändern. Zum Korrigieren stornieren und eine Kopie neu ausstellen.</p>
					</div>
					${inv.time_ids?.length ? `<div class="card card-pad">
						<h3 style="margin-bottom:6px">Stunden</h3>
						<p style="margin:0 0 10px"><b class="num">${fmtH(inv.time_hours)}</b> <span class="muted">· ${inv.time_ids.length} Eintr${inv.time_ids.length === 1 ? 'ag' : 'äge'} ${inv.status === 'draft' ? 'werden mit dem Ausstellen verrechnet' : 'mit dieser Rechnung verrechnet'}</span></p>
						<a class="btn sm" href="#/stunden?rechnung=${inv.id}">${icon('clock')} Stunden ansehen</a>
					</div>` : ''}
					${(inv.payments || []).length ? `<div class="card card-pad">
						<h3 style="margin-bottom:6px">Zahlungen</h3>
						<div class="list">${inv.payments.map((p) => `<div class="list-item"><div class="li-main"><div class="li-title num">${money(p.amount)}</div><div class="li-sub">${date(p.date)}${p.note ? ' · ' + esc(p.note) : ''}</div></div><button class="btn sm ghost icon" data-delpay="${p.id}" title="Zahlung löschen" aria-label="Zahlung löschen">${icon('trash')}</button></div>`).join('')}</div>
						<div style="display:flex;justify-content:space-between;margin-top:10px;font-weight:700"><span>Summe</span><span class="num">${money(inv.paid)} von ${money(inv.gross)}</span></div>
					</div>` : ''}
					<div class="card card-pad">
						<h3 style="margin-bottom:10px">Interne Notiz</h3>
						<textarea id="note" rows="3" placeholder="Nur für dich – erscheint nicht auf der Rechnung">${esc(inv.note || '')}</textarea>
					</div>
					<div class="card card-pad">
						<h3 style="margin-bottom:12px">Verlauf</h3>
						<div class="timeline">${inv.activity.map((a) => `<div class="tl"><div>${esc(a.text)}<div class="when">${relTime(a.created_at)}</div></div></div>`).join('') || '<span class="muted">—</span>'}
						${inv.mails.filter((m) => !m.ok).slice(0, 3).map((m) => `<div class="note-bad">${icon('alert')}<span>Mail an ${esc(m.to_addr)} fehlgeschlagen: ${esc(m.error)}</span></div>`).join('')}</div>
					</div>
				</div>
			</div>
		</div>`;
		const saveMeta = debounce(async () => {
			try { await api('invoice_save', { id: inv.id, note: $('#note').value, recipient: { email: $('#email').value } }); } catch (e) { fail(e); }
		}, 600);
		$('#note').oninput = saveMeta;
		$('#email').oninput = saveMeta;
		const reload = async (updated) => { await refresh(); invoiceDetail(updated || await api('invoice', undefined, { query: { id: inv.id } })); };
		$('[data-pay]', main)?.addEventListener('click', async () => {
			const r = await confirmDialog('Zahlung erhalten', inv.paid > 0
				? `Rechnung ${esc(inv.number)}: bereits ${money(inv.paid)} von ${money(inv.gross)} bezahlt, offen <b>${money(inv.open)}</b>.`
				: `Rechnung ${esc(inv.number)} über <b>${money(inv.gross)}</b>. Bei einem kleineren Betrag wird sie als teilweise bezahlt markiert.`, 'Speichern', {
				extra: `<div class="form-grid" style="margin-top:14px"><label class="field c3"><span>Eingegangen am</span><input type="date" name="date" value="${S.today}"></label><label class="field c3"><span>Betrag €</span><input type="text" inputmode="decimal" name="amount" value="${dec(inv.open)}"></label><label class="field c6"><span>Notiz <small>(optional, z. B. Bar, Überweisung)</small></span><input type="text" name="note"></label></div>`,
			});
			if (!r) return;
			try {
				const u = await api('invoice_pay', { id: inv.id, date: r.date, amount: num(r.amount), note: r.note });
				await reload(u);
				toast(u.state === 'paid' ? 'Vollständig bezahlt' : `Teilzahlung erfasst – offen ${money(u.open)}`);
			} catch (e) { fail(e); }
		});
		$('[data-unpay]', main)?.addEventListener('click', async () => { try { await reload(await api('invoice_unpay', { id: inv.id })); toast('Zahlung zurückgenommen'); } catch (e) { fail(e); } });
		$$('[data-delpay]', main).forEach((b) => (b.onclick = async () => {
			if (!await confirmDialog('Zahlung löschen?', 'Die Zahlung wird entfernt und der offene Betrag neu berechnet.', 'Löschen', { danger: true })) return;
			try { await reload(await api('invoice_unpay', { id: inv.id, payment_id: +b.dataset.delpay })); } catch (e) { fail(e); }
		}));
		$('[data-dup]', main).onclick = async () => { try { const n = await api('invoice_duplicate', { id: inv.id }); await refresh(); go('#/rechnung/' + n.id); toast('Kopie als Entwurf angelegt'); } catch (e) { fail(e); } };
		$('[data-cancel]', main)?.addEventListener('click', async () => {
			const ok = await confirmDialog('Rechnung stornieren?', `Es wird eine Stornorechnung mit eigener Nummer über ${money(-inv.gross)} ausgestellt. Die Rechnung ${esc(inv.number)} gilt dann als storniert.`, 'Stornieren', { danger: true });
			if (ok) try { const st = await api('invoice_cancel', { id: inv.id }); await refresh(); go('#/rechnung/' + st.id); toast('Stornorechnung ' + st.number + ' ausgestellt'); } catch (e) { fail(e); }
		});
		$('[data-marksent]', main)?.addEventListener('click', async () => { try { await reload(await api('invoice_mark_sent', { id: inv.id })); } catch (e) { fail(e); } });
		$('[data-delete]', main).onclick = () => deleteDoc(inv);
		$('[data-mail]', main)?.addEventListener('click', () => mailDialog(inv, 'invoice', reload));
		$('[data-remind]', main)?.addEventListener('click', () => mailDialog(inv, 'reminder', reload));
		$('[data-share]', main).onclick = () => sharePdf(pdfUrl, title, inv);
	}

	async function sharePdf(url, title, inv) {
		try {
			const blob = await fetch(url, { credentials: 'same-origin' }).then((r) => r.blob());
			const name = (title.replace(/\s+/g, '-') + '-' + (inv.recipient.name || '')).replace(/[^\wäöüÄÖÜß.-]+/g, '-') + '.pdf';
			const file = new File([blob], name, { type: 'application/pdf' });
			if (navigator.canShare && navigator.canShare({ files: [file] })) {
				await navigator.share({ files: [file], title });
			} else {
				const a = document.createElement('a');
				a.href = URL.createObjectURL(blob); a.download = name; a.click();
				setTimeout(() => URL.revokeObjectURL(a.href), 5000);
				toast('PDF heruntergeladen');
			}
		} catch (e) { if (e.name !== 'AbortError') fail(e); }
	}

	async function mailDialog(inv, type, after) {
		let pre;
		try { pre = await api('mail_preview', undefined, { query: { id: inv.id, type } }); } catch (e) { return fail(e); }
		const email = $('#email')?.value || pre.to;
		const el = drawer(type === 'reminder' ? 'Zahlungserinnerung senden' : inv.kind === 'offer' ? 'Angebot per E-Mail senden' : 'Rechnung per E-Mail senden', `
			${pre.configured ? '' : `<div class="note-warn" style="margin-bottom:14px">${icon('alert')}<span>Der E-Mail-Versand ist noch nicht eingerichtet (SMTP in <code>config.php</code>, siehe Einstellungen → E-Mail). Du kannst die Mail stattdessen im eigenen Mailprogramm öffnen.</span></div>`}
			<div class="form-grid">
				<label class="field c6"><span>An</span><input type="text" name="to" value="${esc(email)}" placeholder="kunde@example.com" autofocus></label>
				<label class="field c6"><span>Kopie (CC)</span><input type="text" name="cc" value="${esc(pre.cc || '')}"></label>
				<label class="field c6"><span>Betreff</span><input type="text" name="subject" value="${esc(pre.subject)}"></label>
				<label class="field c6"><span>Nachricht</span><textarea name="body" rows="11">${esc(pre.body)}</textarea></label>
				<div class="c6 hint">${icon('paperclip')} Anhang: <b>${esc(pre.filename)}</b>${S.mail.reply_to ? ` · Antworten an ${esc(S.mail.reply_to)}` : ''}${S.mail.bcc ? ` · Kopie an ${esc(S.mail.bcc)}` : ''}</div>
			</div>`,
			`<button class="btn" data-mailto>${icon('mail')} Im Mailprogramm öffnen</button><span class="grow"></span><button class="btn" data-close>Abbrechen</button><button class="btn primary" data-send ${pre.configured ? '' : 'disabled'}>${icon('send')} Senden</button>`);
		const val = (n) => $(`[name=${n}]`, el).value;
		$('[data-send]', el).onclick = async (e) => {
			e.target.disabled = true; e.target.innerHTML = 'Wird gesendet …';
			try {
				const r = await api('invoice_mail', { id: inv.id, type, to: val('to'), cc: val('cc'), subject: val('subject'), body: val('body') });
				closeTop(); toast('Gesendet an ' + r.to); after?.(r.invoice);
			} catch (er) { fail(er); e.target.disabled = false; e.target.innerHTML = icon('send') + ' Senden'; }
		};
		$('[data-mailto]', el).onclick = () => {
			location.href = `mailto:${encodeURIComponent(val('to'))}?subject=${encodeURIComponent(val('subject'))}&body=${encodeURIComponent(val('body') + '\n\n(Rechnung als PDF im Anhang)')}`;
			toast('PDF bitte noch anhängen – es wird heruntergeladen');
			setTimeout(() => (location.href = 'api.php?a=pdf&dl=1&id=' + inv.id), 600);
		};
	}

	/* ---------------------------------------------------------------- Positionen-Editor (Rechnung + Dauerrechnung) */

	function itemsEditor(container, items, onChange, opts = {}) {
		const draw = () => {
			container.innerHTML = `
				<div class="items">${items.map((it, i) => `
					<div class="item" data-i="${i}">
						<div class="pos"><button type="button" data-up title="Nach oben" ${i === 0 ? 'disabled' : ''}>${icon('up')}</button><span>${i + 1}</span><button type="button" data-down title="Nach unten" ${i === items.length - 1 ? 'disabled' : ''}>${icon('down')}</button></div>
						<div class="name-wrap"><input type="text" class="sku" name="sku" value="${esc(it.sku)}" placeholder="Art.Nr." aria-label="Artikelnummer"><input type="text" name="name" value="${esc(it.name)}" placeholder="Leistung – tippen für Artikel …" aria-label="Bezeichnung" autocomplete="off"></div>
						<div class="f-qty"><label class="mini">Menge</label><input type="text" inputmode="decimal" name="qty" value="${qty(it.qty)}" class="right"></div>
						<div class="f-unit"><label class="mini">Einheit</label><input type="text" name="unit" value="${esc(it.unit)}" list="units"></div>
						<div class="f-price"><label class="mini">Einzelpreis €</label><input type="text" inputmode="decimal" name="price" value="${dec(it.price)}" class="right"></div>
						<div class="f-disc"><label class="mini">Rabatt %</label><input type="text" inputmode="decimal" name="discount" value="${it.discount ? qty(it.discount) : ''}" placeholder="–" class="right"></div>
						<div class="sum"><label class="mini">Gesamt</label><div class="num" data-sum>${money(lineNet(it))}</div></div>
						<div class="del"><button type="button" class="btn ghost sm icon" data-del title="Position entfernen" aria-label="Position entfernen">${icon('trash')}</button></div>
						<div class="desc"><textarea name="description" rows="${Math.min(10, Math.max(1, (it.description || '').split('\n').length))}" placeholder="Beschreibung (optional, mehrzeilig)">${esc(it.description)}</textarea></div>
					</div>`).join('')}</div>
				<datalist id="units"><option>Stk.</option><option>Std.</option><option>pauschal</option><option>Monat</option><option>Jahr</option><option>Seite</option></datalist>
				<div class="btns" style="margin-top:10px"><button type="button" class="btn" data-add>${icon('plus')} Position</button>${opts.placeholders ? '<span class="muted" style="font-size:12.5px">Platzhalter: <code class="code">{JAHR}</code> <code class="code">{VORJAHR}</code> <code class="code">{MONAT}</code> <code class="code">{ZEITRAUM}</code></span>' : ''}</div>`;
			$$('.item', container).forEach((row) => {
				const i = +row.dataset.i;
				$$('input,textarea', row).forEach((inp) => {
					inp.addEventListener('input', () => {
						const k = inp.name;
						items[i][k] = ['qty', 'price', 'discount'].includes(k) ? num(inp.value) : inp.value;
						if (k === 'description') inp.rows = Math.min(10, Math.max(1, inp.value.split('\n').length));
						$('[data-sum]', row).textContent = money(lineNet(items[i]));
						onChange();
					});
					if (['qty', 'price'].includes(inp.name)) inp.addEventListener('blur', () => { inp.value = inp.name === 'price' ? dec(items[i].price) : qty(items[i].qty); });
				});
				const fillProduct = (p) => {
					Object.assign(items[i], { product_id: +p.id, sku: p.sku, name: p.name, unit: p.unit, price: +p.price, description: items[i].description || p.description || '' });
					draw(); onChange();
					$(`.item[data-i="${i}"] [name=qty]`, container)?.select();
				};
				autocomplete($('[name=name]', row), productSource, fillProduct);
				$('[name=sku]', row).addEventListener('change', (e) => { const p = productBySku(e.target.value.trim()); if (p && !items[i].name) fillProduct(p); });
				$('[data-del]', row).onclick = () => { items.splice(i, 1); if (!items.length) items.push(blankItem()); draw(); onChange(); };
				$('[data-up]', row).onclick = () => { [items[i - 1], items[i]] = [items[i], items[i - 1]]; draw(); onChange(); };
				$('[data-down]', row).onclick = () => { [items[i + 1], items[i]] = [items[i], items[i + 1]]; draw(); onChange(); };
			});
			$('[data-add]', container).onclick = () => { items.push(blankItem()); draw(); onChange(); $$('.item [name=name]', container).pop()?.focus(); };
		};
		draw();
		return { redraw: draw };
	}
	const blankItem = () => ({ sku: '', name: '', description: '', qty: 1, unit: '', price: 0, discount: 0 });
	const lineNet = (it) => round2(num(it.qty) * num(it.price) * (1 - num(it.discount) / 100));
	const itemsTotal = (items) => round2(items.reduce((a, it) => a + lineNet(it), 0));
	const small = () => S.settings.small_business === '1';

	/* ---------------------------------------------------------------- Rechnungseditor */

	function invoiceEditor(inv) {
		const main = $('#main');
		const ed = JSON.parse(JSON.stringify(inv));
		ed.kind = ed.kind === 'offer' ? 'offer' : 'invoice';
		if (ed.time_ids?.length) { const hn = S.settings.hour_name || 'Arbeitsstunden'; const it = (ed.items || []).find((x) => x.name === hn); if (it) it._hours = true; }
		const isOffer = ed.kind === 'offer';
		const docPath = isOffer ? '#/angebot/' : '#/rechnung/';
		ed.items = ed.items.length ? ed.items : [blankItem()];
		if (!ed.items.length) ed.items.push(blankItem());
		let mode = ed.period_from ? 'period' : 'date';
		const view = { dirty: false };
		let previewUrl = null, previewBusy = false;
		const showPreview = window.matchMedia('(min-width: 861px)');

		main.innerHTML = `<div class="page page-editor">
			${pageHead(isOffer ? (ed.id ? 'Angebot bearbeiten' : 'Neues Angebot') : (ed.id ? 'Entwurf bearbeiten' : 'Neue Rechnung'), { back: isOffer ? ['#/angebote', 'Angebote'] : ['#/rechnungen', 'Rechnungen'], sub: `Bekommt beim Ausstellen die Nummer <span class="mono">${esc(isOffer ? S.next_offer : S.next_number)}</span>` },
				`${ed.id ? `<button class="btn ghost danger" data-delete>${icon('trash')}<span class="hide-m">Löschen</span></button>` : ''}
				<button class="btn show-m" data-preview-m>${icon('eye')}</button>
				<button class="btn" data-save>Speichern</button>
				<button class="btn primary" data-issue>${icon('check')} Ausstellen</button>`)}
			<div class="editor">
				<div class="grid" style="align-content:start">
					<div class="card card-pad">
						<div class="form-grid">
							<div class="field c4 m-full"><span>Kunde</span><div style="position:relative"><input type="text" id="cust" autocomplete="off" placeholder="Kunde suchen oder neu anlegen …" value="${esc(ed.customer_id ? (customerById(ed.customer_id)?.name || ed.recipient.name) : '')}"></div></div>
							<label class="field c2 m-full"><span>Rechnungsdatum</span><input type="date" name="invoice_date" value="${ed.invoice_date}"></label>
							<label class="field c3 m-full"><span>Anschrift <small>(eine Zeile pro Feld)</small></span><textarea id="lines" rows="5">${esc((ed.recipient.lines || []).join('\n'))}</textarea></label>
							<div class="c3 m-full" style="display:flex;flex-direction:column;gap:12px">
								${isOffer ? `<label class="field"><span>Gültig bis</span><input type="date" name="valid_until" value="${ed.valid_until || ''}"></label>` : ''}
								<label class="field"><span>Zahlungsziel${isOffer ? ' <small>(bei Auftrag)</small>' : ''}</span><select name="payment_days">${[[0, 'sofort'], [7, '7 Tage'], [14, '14 Tage'], [21, '21 Tage'], [30, '30 Tage']].map(([v, l]) => `<option value="${v}" ${+ed.payment_days === v ? 'selected' : ''}>${l}</option>`).join('')}${[0, 7, 14, 21, 30].includes(+ed.payment_days) ? '' : `<option value="${ed.payment_days}" selected>${ed.payment_days} Tage</option>`}</select></label>
								<div class="field"><span style="display:flex;justify-content:space-between;align-items:center">Leistung <span class="seg" id="svcmode"><button type="button" data-m="date">Datum</button><button type="button" data-m="period">Zeitraum</button></span></span>
									<div id="svc"></div></div>
							</div>
							<label class="field c6"><span>Betreff <small>(optional, steht hinter der Rechnungsnummer)</small></span><input type="text" name="subject" value="${esc(ed.subject)}" placeholder="z. B. Hosting 2027 oder Relaunch Website"></label>
						</div>
					</div>

					<div class="card card-pad">
						${isOffer ? '' : '<div id="hourpanel"></div>'}
						<div class="section-title" style="margin-top:0">Positionen</div>
						<div id="items"></div>
						<div class="totals" id="totals"></div>
					</div>

					<div class="card card-pad">
						<div class="form-grid">
							<label class="field c6"><span>Anrede</span><input type="text" name="greeting" value="${esc(ed.greeting)}" placeholder="leer = automatisch, z. B. „Sehr geehrter Herr …“"></label>
							<label class="field c6"><span>Einleitung</span><textarea name="intro" rows="2">${esc(ed.intro)}</textarea></label>
							<label class="field c6"><span>Schlusstext <small>(optional)</small></span><textarea name="outro" rows="2" placeholder="z. B. Laufende Kosten ab 2027: 110 € / Jahr">${esc(ed.outro)}</textarea></label>
							<label class="field c6"><span>Interne Notiz <small>(nicht auf der Rechnung)</small></span><textarea name="note" rows="2">${esc(ed.note)}</textarea></label>
						</div>
					</div>

					<div class="card card-pad shrink">
						<label class="switch"><input type="checkbox" id="shrink"> Schrumpfen</label>
						<span class="muted shrink-info" id="shrinkinfo"></span>
						<button type="button" class="btn sm ghost" id="shrinkauto">Automatisch</button>
					</div>
				</div>
				<section class="card preview" aria-label="Live-Vorschau">
					<div class="preview-head"><span class="dot" id="pdot"></span><span style="flex:1">Live-Vorschau – aktualisiert sich beim Tippen</span><button class="btn sm" data-preview-open>${icon('eye')} In neuem Tab</button></div>
					<div class="preview-paper"><iframe id="pframe" title="Vorschau der Rechnung"></iframe></div>
				</section>
			</div>
		</div>`;

		const touch = () => { view.dirty = true; drawTotals(); schedulePreview(); };

		/* Schrumpfen: automatisch, solange der Haken nicht von Hand gesetzt wurde */
		ed.compact = ed.compact || 'auto';
		let autoLevel = 0;
		const drawShrink = () => {
			const on = ed.compact === 'on' || (ed.compact === 'auto' && autoLevel > 0);
			$('#shrink').checked = on;
			$('#shrinkinfo').textContent = ed.compact === 'auto'
				? (autoLevel > 0 ? 'automatisch an – kleinere Positionszeilen, damit kein unnötiger Seitenumbruch entsteht' : 'automatisch – wird gesetzt, sobald sonst eine fast leere Seite entstünde')
				: ed.compact === 'on' ? 'von Hand an – Positionszeilen kleiner' : 'von Hand aus – normale Größe';
			$('#shrinkauto').classList.toggle('hide', ed.compact === 'auto');
		};
		$('#shrink').onchange = (e) => { ed.compact = e.target.checked ? 'on' : 'off'; drawShrink(); touch(); };
		$('#shrinkauto').onclick = () => { ed.compact = 'auto'; drawShrink(); touch(); };
		drawShrink();
		const drawSvc = () => {
			$$('#svcmode button').forEach((b) => b.classList.toggle('on', b.dataset.m === mode));
			$('#svc').innerHTML = mode === 'date'
				? `<input type="date" name="service_date" value="${ed.service_date || ed.invoice_date}">`
				: `<div style="display:flex;gap:6px;align-items:center"><input type="date" name="period_from" value="${ed.period_from || ''}"><span class="muted">–</span><input type="date" name="period_to" value="${ed.period_to || ''}"></div>`;
			$$('#svc input').forEach((i) => (i.oninput = () => { ed[i.name] = i.value; touch(); }));
		};
		$$('#svcmode button').forEach((b) => (b.onclick = () => {
			mode = b.dataset.m;
			if (mode === 'period' && !ed.period_from) { ed.period_from = ed.invoice_date; ed.period_to = addDays(addMonths(ed.invoice_date, 12), -1); }
			drawSvc(); touch();
		}));
		drawSvc();

		const drawTotals = () => {
			const net = itemsTotal(ed.items);
			let html = '';
			if (!small()) {
				const groups = {};
				ed.items.forEach((it) => { const r = it.tax_rate ?? +S.settings.default_tax; groups[r] = (groups[r] || 0) + lineNet(it); });
				let tax = 0;
				html += `<div><span>Netto</span><span class="num">${money(net)}</span></div>`;
				Object.entries(groups).forEach(([r, n]) => { const t = round2(n * r / 100); tax += t; if (+r) html += `<div><span>${r} % USt.</span><span class="num">${money(t)}</span></div>`; });
				html += `<div class="grand"><span>Gesamt</span><span class="num">${money(net + tax)}</span></div>`;
			} else {
				html += `<div class="grand"><span>Gesamt</span><span class="num">${money(net)}</span></div><div style="font-size:12px"><span>Kleinunternehmer – ohne USt.</span></div>`;
			}
			$('#totals').innerHTML = html;
		};
		// Wird die Stunden-Position von Hand gelöscht, zeigt die Leiste „ohne Position bestätigt“
		const itemsCtl = itemsEditor($('#items'), ed.items, () => { touch(); if (ed.time_ids?.length && hp.asItem !== hourItemIdx() >= 0) drawHours(); });
		drawTotals();

		/* Offene Stunden des Kunden: Leiste nur wenn vorhanden, ein-/ausblendbar, als Position übernehmen */
		ed.time_ids = ed.time_ids || [];
		let hp = { entries: [], rate: 0, open: false, pick: new Set(ed.time_ids), withNotes: true, asItem: false };
		const hourItemIdx = () => ed.items.findIndex((it) => it._hours);
		const drawHours = () => {
			const box = $('#hourpanel');
			if (!box) return;
			if (!hp.entries.length) { box.innerHTML = ''; return; }
			const total = hp.entries.reduce((a, t) => a + t.hours, 0);
			const picked = hp.entries.filter((t) => hp.pick.has(t.id));
			const ph = picked.reduce((a, t) => a + t.hours, 0);
			const taken = ed.time_ids.length > 0;
			hp.asItem = hourItemIdx() >= 0;
			const takenH = ed.time_ids.reduce((a, id) => a + (hp.entries.find((t) => t.id === id)?.hours || 0), 0);
			box.innerHTML = `<div class="hourpanel ${hp.open ? 'open' : ''}">
				<button type="button" class="hp-head" aria-expanded="${hp.open}" data-hp-toggle>
					${icon('clock')}<span class="grow"><b>${fmtH(total)} offene Stunden</b> für diesen Kunden${taken ? ` · <span class="hp-taken">${fmtH(takenH)} ${hp.asItem ? 'als Position übernommen' : 'bestätigt, ohne eigene Position'}</span>` : ''}</span>
					<span class="muted">${hp.open ? 'Ausblenden' : 'Einblenden'}</span>${icon(hp.open ? 'up' : 'down')}
				</button>
				${hp.open ? `<div class="hp-body">
					<div class="hp-list">${hp.entries.map((t) => `<label class="hp-row"><input type="checkbox" data-hp="${t.id}" ${hp.pick.has(t.id) ? 'checked' : ''}>
						<span class="mono muted">${date(t.date)}</span><span class="grow">${t.project ? `<b>${esc(t.project)}</b> · ` : ''}${esc(t.note) || '<span class="faint">ohne Notiz</span>'}</span><b class="num">${fmtH(t.hours)}</b></label>`).join('')}</div>
					<div class="hp-foot">
						<label class="field" style="width:140px"><span>Stundensatz €</span><input type="text" inputmode="decimal" id="hp-rate" value="${dec(hp.rate)}"></label>
						<label class="switch" style="font-size:13px"><input type="checkbox" id="hp-notes" ${hp.withNotes ? 'checked' : ''}> Notizen als Beschreibung</label>
						<span class="grow"></span>
						<span class="muted num">${fmtH(ph)} × ${money(num($('#hp-rate')?.value ?? hp.rate))}</span>
					</div>
					<div class="hp-actions">
						${taken ? `<button type="button" class="btn sm ghost" data-hp-remove>Entfernen</button>` : ''}
						<button type="button" class="btn sm" data-hp-confirm ${picked.length ? '' : 'disabled'} title="Die Stunden werden mit dieser Rechnung verrechnet, ohne eigene Position – z. B. wenn sie pauschal enthalten sind">${icon('check')} ${taken && !hp.asItem ? 'Bestätigung aktualisieren' : 'Ohne Position bestätigen'}</button>
						<button type="button" class="btn sm primary" data-hp-take ${picked.length ? '' : 'disabled'}>${icon('plus')} ${taken && hp.asItem ? 'Position aktualisieren' : 'Als Position übernehmen'}</button>
					</div>
					${taken && !hp.asItem ? `<p class="hp-note">${icon('check')} Diese Stunden stehen nicht als eigene Position auf der Rechnung, gelten aber mit dem Ausstellen als verrechnet und werden bei keiner weiteren Rechnung mehr angeboten.</p>` : ''}
					</div>` : ''}
			</div>`;
			$('[data-hp-toggle]', box).onclick = () => { hp.open = !hp.open; drawHours(); };
			$$('[data-hp]', box).forEach((c) => (c.onchange = () => { c.checked ? hp.pick.add(+c.dataset.hp) : hp.pick.delete(+c.dataset.hp); drawHours(); }));
			$('#hp-rate', box)?.addEventListener('change', (e) => { hp.rate = num(e.target.value); drawHours(); });
			$('#hp-notes', box)?.addEventListener('change', (e) => { hp.withNotes = e.target.checked; });
			$('[data-hp-take]', box)?.addEventListener('click', () => takeHours());
			$('[data-hp-confirm]', box)?.addEventListener('click', () => confirmHours());
			$('[data-hp-remove]', box)?.addEventListener('click', () => {
				const i = hourItemIdx(); if (i >= 0) ed.items.splice(i, 1);
				if (!ed.items.length) ed.items.push(blankItem());
				ed.time_ids = []; itemsCtl.redraw(); touch(); drawHours();
			});
		};
		const takeHours = () => {
			const picked = hp.entries.filter((t) => hp.pick.has(t.id)).sort((a, b) => a.date.localeCompare(b.date));
			if (!picked.length) return;
			const item = {
				_hours: true, sku: '', name: S.settings.hour_name || 'Arbeitsstunden', unit: 'Std.', discount: 0, price: hp.rate,
				qty: Math.round(picked.reduce((a, t) => a + t.hours, 0) * 100) / 100,
				description: hp.withNotes ? picked.map((t) => `${date(t.date)}${t.project ? ' · ' + t.project : ''}${t.note ? ' – ' + t.note.replace(/\s*\n\s*/g, '; ') : ''} (${qty(t.hours)} h)`).join('\n') : '',
			};
			const i = hourItemIdx();
			if (i >= 0) ed.items[i] = item;
			else if (ed.items.length === 1 && !ed.items[0].name && !num(ed.items[0].price)) ed.items[0] = item;
			else ed.items.push(item);
			ed.time_ids = picked.map((t) => t.id);
			itemsCtl.redraw(); touch(); drawHours();
			toast(fmtH(item.qty) + ' als Position übernommen – beim Ausstellen gelten sie als abgerechnet');
		};
		const confirmHours = () => {
			const picked = hp.entries.filter((t) => hp.pick.has(t.id));
			if (!picked.length) return;
			const i = hourItemIdx();
			if (i >= 0) { ed.items.splice(i, 1); if (!ed.items.length) ed.items.push(blankItem()); itemsCtl.redraw(); }
			ed.time_ids = picked.map((t) => t.id);
			touch(); drawHours();
			toast(fmtH(picked.reduce((a, t) => a + t.hours, 0)) + ' bestätigt – mit dem Ausstellen verrechnet, ohne eigene Position');
		};
		const loadHours = async (auto) => {
			if (isOffer || !ed.customer_id) { hp.entries = []; drawHours(); return; }
			try {
				const r = await api('hours_open', undefined, { query: { customer_id: ed.customer_id, invoice_id: ed.id || 0 } });
				hp.entries = r.entries; hp.rate = r.rate;
				// Vorauswahl: verknüpfte, aus der Stundenseite gewählte oder alle
				const want = String(auto || '');
				if (ed.time_ids.length) hp.pick = new Set(ed.time_ids);
				else if (want && want !== '1') hp.pick = new Set(want.split('.').map(Number));
				else hp.pick = new Set(r.entries.map((t) => t.id));
				if (auto && hp.entries.length) { hp.open = true; takeHours(); } else drawHours();
			} catch (e) { /* Leiste ist optional */ }
		};

		$$('[name]', main).forEach((i) => {
			if (i.closest('#items') || i.closest('#svc')) return;
			i.addEventListener('input', () => { ed[i.name] = i.value; touch(); });
		});
		$('#lines').oninput = (e) => { ed.recipient.lines = e.target.value.split('\n').map((s) => s.trim()).filter(Boolean); touch(); };

		const setCustomer = (c, skipHours) => {
			ed.customer_id = +c.id;
			ed.recipient = { name: c.name, number: c.number, lines: c.lines || [], email: c.email };
			if (c.payment_days !== null && c.payment_days !== '' && c.payment_days !== undefined) { ed.payment_days = +c.payment_days; $('[name=payment_days]').value = String(c.payment_days); }
			ed.greeting = '';
			$('[name=greeting]').value = '';
			$('#cust').value = c.name;
			$('#lines').value = ed.recipient.lines.join('\n');
			ed.refresh_recipient = true;
			if (ed.time_ids?.length) { const i = hourItemIdx(); if (i >= 0) ed.items.splice(i, 1); if (!ed.items.length) ed.items.push(blankItem()); ed.time_ids = []; itemsCtl.redraw(); }
			touch();
			if (!skipHours) loadHours();
		};
		autocomplete($('#cust'), customerSource, (c) => {
			if (c._new) return customerDrawer({ company: c.name }, (saved) => setCustomer(saved));
			setCustomer(c);
		}, { always: true });
		// Anschriftzeilen eines vorausgewählten Kunden (aus „Neue Rechnung für Kunde“)
		if (inv._fromCustomer && (!ed.recipient.lines || !ed.recipient.lines.length)) {
			api('customer', undefined, { query: { id: ed.customer_id } }).then((c) => setCustomer(c, true)).then(() => { view.dirty = false; loadHours(inv._hours); }).catch(() => {});
		} else {
			loadHours(inv._hours);
		}

		const payload = () => {
			const d = { ...ed };
			if (mode === 'date') { d.period_from = null; d.period_to = null; } else { d.service_date = null; }
			d.items = ed.items.map((it) => ({ ...it, qty: num(it.qty), price: num(it.price), discount: num(it.discount) }));
			return d;
		};

		/* Live-Vorschau (PDF, entprellt) */
		const schedulePreview = debounce(async () => {
			if (!showPreview.matches || previewBusy) return;
			previewBusy = true;
			$('#pdot')?.classList.add('busy');
			try {
				const res = await api('preview', payload(), { blob: true, headers: true });
				autoLevel = +(res.headers.get('X-NW-Compact') || 0);
				drawShrink();
				const url = URL.createObjectURL(res.blob);
				const fr = $('#pframe');
				if (!fr) return;
				fr.src = url + '#view=FitH&toolbar=0&navpanes=0';
				if (previewUrl) setTimeout(((u) => () => URL.revokeObjectURL(u))(previewUrl), 2000);
				previewUrl = url;
			} catch (e) { /* Vorschau-Fehler still */ }
			finally { previewBusy = false; $('#pdot')?.classList.remove('busy'); }
		}, 650);
		schedulePreview();
		showPreview.addEventListener?.('change', schedulePreview);

		const openPreview = async () => {
			const w = window.open('', '_blank');
			try {
				const blob = await api('preview', payload(), { blob: true });
				const url = URL.createObjectURL(blob);
				if (w) w.location = url; else location.href = url;
			} catch (e) { w?.close(); fail(e); }
		};
		$('[data-preview-open]').onclick = openPreview;
		$('[data-preview-m]').onclick = openPreview;

		const save = async (quiet) => {
			const saved = await api('invoice_save', payload());
			view.dirty = false;
			ed.id = saved.id;
			await refresh();
			if (!quiet) toast('Entwurf gespeichert');
			if (!inv.id) { history.replaceState(null, '', docPath + saved.id); lastHash = location.hash; }
			return saved;
		};
		$('[data-save]').onclick = () => save().catch(fail);
		$('[data-issue]').onclick = async () => {
			if (!ed.customer_id && !(ed.recipient.lines || []).length) return fail(new Error('Bitte einen Kunden wählen.'));
			const total = itemsTotal(ed.items);
			const email = ed.recipient.email || customerById(ed.customer_id)?.email || '';
			const r = await confirmDialog(isOffer ? 'Angebot ausstellen?' : 'Rechnung ausstellen?', isOffer
				? `Das Angebot bekommt die Nummer <b>${esc(S.next_offer)}</b>. Summe: <b>${money(total)}</b>, gültig bis ${date(ed.valid_until)}.`
				: `Die Rechnung bekommt die Nummer <b>${esc(S.next_number)}</b> und kann danach nicht mehr geändert werden. Betrag: <b>${money(total)}</b>.`, 'Ausstellen', {
				extra: S.mail.configured && email ? `<label class="switch" style="margin-top:14px"><input type="checkbox" name="send" checked> Danach per E-Mail an ${esc(email)} senden</label>` : '',
			});
			if (!r) return;
			try {
				const saved = await save(true);
				const issued = await api('invoice_issue', { id: saved.id });
				await refresh();
				view.dirty = false;
				go(docPath + issued.id);
				toast((isOffer ? 'Angebot ' : 'Rechnung ') + issued.number + ' ausgestellt');
				if (r.send) setTimeout(() => mailDialog(issued, 'invoice', async (u) => { await refresh(); route(); }), 400);
			} catch (e) { fail(e); }
		};
		$('[data-delete]', main)?.addEventListener('click', async () => {
			if (!await confirmDialog('Entwurf löschen?', 'Der Entwurf wird endgültig gelöscht.', 'Löschen', { danger: true })) return;
			try { await api('invoice_delete', { id: ed.id }); view.dirty = false; await refresh(); go(isOffer ? '#/angebote' : '#/rechnungen'); toast('Entwurf gelöscht'); } catch (e) { fail(e); }
		});
		const onKey = (e) => { if ((e.metaKey || e.ctrlKey) && e.key === 's') { e.preventDefault(); save().catch(fail); } };
		document.addEventListener('keydown', onKey);
		view.cleanup = () => { document.removeEventListener('keydown', onKey); if (previewUrl) URL.revokeObjectURL(previewUrl); };
		if (!ed.customer_id) setTimeout(() => $('#cust')?.focus(), 50);
		return view;
	}

	/* ================================================================ Angebote */

	async function viewOffers(q) {
		const main = $('#main');
		let f = q.f || store.get('off.f', 'all');
		let search = '';
		const filters = [['all', 'Alle'], ['draft', 'Entwürfe'], ['sent', 'Offen'], ['accepted', 'Angenommen'], ['declined', 'Abgelehnt'], ['expired', 'Abgelaufen']];
		main.innerHTML = `<div class="page">
			${pageHead('Angebote', { sub: 'Angebote erstellen, nachverfolgen und mit einem Klick in eine Rechnung umwandeln' }, `<a class="btn primary" href="#/angebot/neu">${icon('plus')} Neues Angebot</a>`)}
			<div class="toolbar"><div class="chips" id="chips"></div><div class="grow"></div>
				<label class="search"><span class="sr">Suchen</span>${icon('search')}<input type="search" id="q" placeholder="Nummer, Kunde, Leistung …"></label></div>
			<div class="card"><div class="table-wrap" id="list"></div></div></div>`;
		const draw = () => {
			store.set('off.f', f);
			const all = S.offers || [];
			$('#chips').innerHTML = filters.map(([k, l]) => `<button class="chip ${f === k ? 'on' : ''}" data-f="${k}">${l} <span class="n">${k === 'all' ? all.length : all.filter((o) => o.state === k).length}</span></button>`).join('');
			$$('#chips .chip').forEach((c) => (c.onclick = () => { f = c.dataset.f; draw(); }));
			const n = norm(search);
			const rows = all.filter((o) => (f === 'all' || o.state === f) && (!n || norm([o.number, o.recipient.name, o.item_names, o.subject].join(' ')).includes(n)));
			const open = rows.filter((o) => o.state === 'sent').reduce((a, o) => a + o.gross, 0);
			const won = rows.filter((o) => o.state === 'accepted').reduce((a, o) => a + o.gross, 0);
			$('#list').innerHTML = rows.length ? `<table class="table resp"><thead><tr><th>Nr.</th><th>Kunde</th><th class="hide-m">Datum</th><th class="hide-m">Gültig bis</th><th>Status</th><th class="th-r">Summe</th></tr></thead><tbody>
				${rows.map((o) => `<tr class="click" data-id="${o.id}">
					<td class="m-hide mono">${esc(o.number || '—')}</td>
					<td class="m-b strong"><div>${withRec(esc(o.recipient.name || 'Ohne Empfänger'), false)}</div><div class="sub">${esc(o.subject || o.item_names || '')}</div></td>
					<td class="m-c muted nowrap"><span class="show-m mono">${esc(o.number || 'Entwurf')} · </span>${date(o.invoice_date)}</td>
					<td class="m-hide muted nowrap">${date(o.valid_until) || '—'}</td>
					<td class="m-e">${badge(o.state)}${o.converted_id ? ` <span class="muted" title="In Rechnung umgewandelt">${icon('file')}</span>` : ''}</td>
					<td class="m-d td-r num strong">${money(o.gross)}</td></tr>`).join('')}</tbody></table>
				<div class="sumbar"><span>${rows.length} Angebote</span>${open ? `<span>offen <b class="num">${money(open)}</b></span>` : ''}${won ? `<span>angenommen <b class="num">${money(won)}</b></span>` : ''}</div>`
				: `<div class="empty"><span class="big">[ ]</span>Noch keine Angebote. <a href="#/angebot/neu">Erstes Angebot erstellen</a></div>`;
			$$('#list tr[data-id]').forEach((tr) => (tr.onclick = () => go('#/angebot/' + tr.dataset.id)));
		};
		$('#q').oninput = debounce((e) => { search = e.target.value; draw(); }, 120);
		draw();
	}

	function offerDetail(inv) {
		const main = $('#main');
		const s = inv.state;
		const pdfUrl = 'api.php?a=pdf&id=' + inv.id;
		const title = 'Angebot ' + inv.number;
		main.innerHTML = `<div class="page">
			${pageHead(esc(title), { back: ['#/angebote', 'Angebote'], crumbs: inv.customer ? [['#/kunde/' + inv.customer.id, inv.recipient.name]] : [] },
				`<a class="btn" href="${pdfUrl}&dl=1">${icon('download')}<span class="hide-m">PDF</span></a>
				<button class="btn" data-share>${icon('share')}<span class="hide-m">Teilen</span></button>
				<button class="btn ${inv.sent_at ? '' : 'primary'}" data-mail>${icon('send')} ${inv.sent_at ? 'Erneut senden' : 'Per E-Mail senden'}</button>`)}
			<div class="grid g-main">
				<div class="grid" style="align-content:start">
					<div class="card">
						<div class="status-hero">
							<div class="grow"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:6px">${badge(s)}
								${inv.sent_at ? `<span class="badge plain">${icon('mail')} versendet ${date(inv.sent_at)}</span>` : ''}</div>
								<div class="amount num">${money(inv.gross)}</div>
								<div class="muted">${esc(inv.recipient.name)} · ${date(inv.invoice_date)}</div></div>
							<div class="btns">
								${inv.converted ? `<a class="btn primary" href="#/rechnung/${inv.converted.id}">${icon('file')} Zur Rechnung ${esc(inv.converted.number || '(Entwurf)')}</a>`
									: `<button class="btn primary" data-convert>${icon('file')} In Rechnung umwandeln</button>`}
								${s === 'sent' || s === 'expired' ? `<button class="btn" data-state="accepted">${icon('check')} Angenommen</button><button class="btn" data-state="declined">${icon('x')} Abgelehnt</button>` : ''}
								${(s === 'accepted' || s === 'declined') && !inv.converted ? `<button class="btn" data-state="open">Wieder offen</button>` : ''}
							</div>
						</div>
						<dl class="facts">
							<div><dt>Angebotsdatum</dt><dd>${date(inv.invoice_date)}</dd></div>
							<div><dt>Gültig bis</dt><dd>${date(inv.valid_until) || '—'}${s === 'expired' ? ' <span class="badge b-expired">abgelaufen</span>' : ''}</dd></div>
							<div><dt>Zahlungsziel bei Auftrag</dt><dd>${inv.payment_days > 0 ? inv.payment_days + ' Tage' : 'sofort'}</dd></div>
							<div><dt>Kunden Nr.</dt><dd class="mono">${esc(inv.recipient.number || '—')}</dd></div>
						</dl>
					</div>
					<div class="card" style="overflow:hidden">
						<div class="preview-head">${icon('offer')} <span style="flex:1">${esc(title)}.pdf</span><a class="btn sm" href="${pdfUrl}" target="_blank" rel="noopener">${icon('eye')} In neuem Tab</a></div>
						<iframe class="pdf-frame" src="${pdfUrl}#view=FitH&navpanes=0" title="Angebot als PDF"></iframe>
					</div>
				</div>
				<div class="grid" style="align-content:start">
					<div class="card card-pad">
						<h3 style="margin-bottom:10px">Empfänger</h3>
						<div style="line-height:1.55">${inv.recipient.lines.map((l, i) => i === 0 ? `<b>${esc(l)}</b>` : esc(l)).join('<br>')}</div>
						<label class="field" style="margin-top:14px"><span>E-Mail für den Versand</span><input type="email" id="email" value="${esc(inv.recipient.email || inv.customer?.email || '')}" placeholder="kunde@example.com"></label>
					</div>
					<div class="card card-pad">
						<h3 style="margin-bottom:10px">Aktionen</h3>
						<div class="btns" style="flex-direction:column;align-items:stretch">
							<button class="btn" data-dup>${icon('copy')} Als neues Angebot kopieren</button>
							${!inv.sent_at ? `<button class="btn ghost" data-marksent>${icon('check')} Als versendet markieren</button>` : ''}
							<button class="btn ghost danger" data-delete>${icon('trash')} Angebot löschen</button>
						</div>
						<p class="muted" style="font-size:12.5px;margin-top:12px">„In Rechnung umwandeln“ legt einen Rechnungsentwurf mit denselben Positionen an und markiert das Angebot als angenommen.</p>
					</div>
					<div class="card card-pad">
						<h3 style="margin-bottom:10px">Interne Notiz</h3>
						<textarea id="note" rows="3" placeholder="Nur für dich – erscheint nicht auf dem Angebot">${esc(inv.note || '')}</textarea>
					</div>
					<div class="card card-pad">
						<h3 style="margin-bottom:12px">Verlauf</h3>
						<div class="timeline">${inv.activity.map((a) => `<div class="tl"><div>${esc(a.text)}<div class="when">${relTime(a.created_at)}</div></div></div>`).join('') || '<span class="muted">—</span>'}</div>
					</div>
				</div>
			</div>
		</div>`;
		const reload = async (u) => { await refresh(); offerDetail(u || await api('invoice', undefined, { query: { id: inv.id } })); };
		const saveMeta = debounce(async () => { try { await api('invoice_save', { id: inv.id, note: $('#note').value, recipient: { email: $('#email').value } }); } catch (e) { fail(e); } }, 600);
		$('#note').oninput = saveMeta;
		$('#email').oninput = saveMeta;
		$$('[data-state]', main).forEach((b) => (b.onclick = async () => {
			try {
				const u = await api('offer_state', { id: inv.id, state: b.dataset.state });
				if (b.dataset.state === 'accepted' && await confirmDialog('Angebot angenommen', 'Gleich eine Rechnung daraus erstellen? Du kannst sie vor dem Ausstellen noch anpassen.', 'Rechnung erstellen')) {
					const r = await api('offer_convert', { id: inv.id }); await refresh(); go('#/rechnung/' + r.id); toast('Rechnungsentwurf aus Angebot erstellt'); return;
				}
				reload(u);
			} catch (e) { fail(e); }
		}));
		$('[data-convert]', main)?.addEventListener('click', async () => {
			try { const r = await api('offer_convert', { id: inv.id }); await refresh(); go('#/rechnung/' + r.id); toast('Rechnungsentwurf aus Angebot erstellt'); } catch (e) { fail(e); }
		});
		$('[data-dup]', main).onclick = async () => { try { const n = await api('invoice_duplicate', { id: inv.id }); await refresh(); go('#/angebot/' + n.id); toast('Kopie als Entwurf angelegt'); } catch (e) { fail(e); } };
		$('[data-marksent]', main)?.addEventListener('click', async () => { try { reload(await api('invoice_mark_sent', { id: inv.id })); } catch (e) { fail(e); } });
		$('[data-mail]', main).onclick = () => mailDialog(inv, 'invoice', reload);
		$('[data-share]', main).onclick = () => sharePdf(pdfUrl, title, inv);
		$('[data-delete]', main).onclick = () => deleteDoc(inv);
	}

	/** Rechnung, Stornorechnung oder Angebot löschen – mit Auskunft, was mit der Nummer passiert. */
	async function deleteDoc(inv) {
		const offer = inv.kind === 'offer';
		const what = offer ? 'Angebot' : inv.kind === 'storno' ? 'Stornorechnung' : 'Rechnung';
		if (inv.storno) return fail(new Error(`Zu dieser Rechnung gibt es die Stornorechnung ${inv.storno.number} – bitte zuerst diese löschen.`));
		const next = offer ? +String(S.next_offer).replace('A-', '') : +S.next_number;
		const n = offer ? +String(inv.number).replace('A-', '') : +inv.number;
		const last = n + 1 === next;
		const notes = [
			last ? `Es ist die zuletzt vergebene Nummer – <b>${esc(inv.number)}</b> wird beim nächsten ${offer ? 'Angebot' : 'Ausstellen'} wieder vergeben.` : `Die Nummer <b>${esc(inv.number)}</b> bleibt frei, es wird mit ${esc(offer ? S.next_offer : S.next_number)} weitergezählt.`,
			inv.payments?.length ? `${inv.payments.length} erfasste Zahlung${inv.payments.length > 1 ? 'en werden' : ' wird'} mitgelöscht.` : '',
			inv.time_ids?.length ? `${inv.time_ids.length} Stundeneinträge werden wieder offen.` : '',
			inv.kind === 'storno' ? 'Die stornierte Rechnung gilt danach wieder.' : '',
			!offer ? 'Hinweis: Ausgestellte Rechnungen sind aufzubewahren – bei Fehlern ist Stornieren meist der richtige Weg.' : '',
		].filter(Boolean);
		const ok = await confirmDialog(`${what} ${inv.number} löschen?`, notes.map((x) => `<span style="display:block;margin-bottom:6px">${x}</span>`).join(''), 'Endgültig löschen', { danger: true });
		if (!ok) return;
		try {
			const r = await api('invoice_delete', { id: inv.id });
			await refresh();
			go(offer ? '#/angebote' : '#/rechnungen');
			toast(`${what} ${inv.number} gelöscht` + (r.reused ? ' – Nummer wird wieder vergeben' : ''));
		} catch (e) { fail(e); }
	}

	/* ================================================================ Stunden */

	/** „1,5“, „1.5“, „1:30“, „90m“, „2h“, „1h 15m“ → Stunden (wie nw_parse_hours im Server). */
	const parseHours = (v) => {
		v = String(v ?? '').trim().toLowerCase().replace(',', '.');
		let m = v.match(/^(\d+):(\d{1,2})$/);
		if (m) return Math.round((+m[1] + +m[2] / 60) * 100) / 100;
		m = v.match(/^(?:(\d+(?:\.\d+)?)\s*h)?\s*(?:(\d+)\s*m(?:in)?)?$/);
		if (v && m && (m[1] || m[2])) return Math.round(((+m[1] || 0) + (+m[2] || 0) / 60) * 100) / 100;
		return isNaN(+v) ? 0 : Math.round(+v * 100) / 100;
	};
	const fmtH = (h) => qty(Math.round(h * 100) / 100) + ' h';
	const HOUR_STATE = { open: ['offen', 'b-open'], draft: ['im Entwurf', 'b-draft'], billed: ['verrechnet', 'b-paid'] };
	const hourBadge = (t) => `<span class="badge ${HOUR_STATE[t.state][1]}">${HOUR_STATE[t.state][0]}${t.invoice_number ? ' · ' + esc(t.invoice_number) : ''}</span>`;

	/* Stoppuhr: läuft im Browser weiter (auch nach Neuladen), pro Gerät */
	const timer = {
		get: () => store.get('timer', null),
		start: () => store.set('timer', { start: Date.now() }),
		stop: () => { const t = store.get('timer', null); store.set('timer', null); return t ? (Date.now() - t.start) / 3600000 : 0; },
		label: () => { const t = store.get('timer', null); if (!t) return ''; const sec = Math.floor((Date.now() - t.start) / 1000); return [Math.floor(sec / 3600), Math.floor(sec / 60) % 60, sec % 60].map((n) => String(n).padStart(2, '0')).join(':'); },
	};

	/**
	 * Erfassungsformular (Seite, Kunde, Übersicht, Dialog).
	 * o.customer – fester Kunde · o.entry – bearbeiten · o.projects – Vorschläge · o.onSaved
	 */
	function hourForm(box, o = {}) {
		const e = o.entry || {};
		let cust = o.customer || (e.customer_id ? customerById(e.customer_id) : customerById(store.get('hours.customer', 0)));
		box.innerHTML = `<form class="hour-form" autocomplete="off">
			${o.customer ? '' : `<div class="field hf-cust"><span>Kunde</span><div style="position:relative"><input type="text" name="cust" placeholder="Kunde suchen …" value="${esc(cust?.name || '')}"></div></div>`}
			<label class="field hf-date"><span>Datum</span><input type="date" name="date" value="${e.date || S.today}"></label>
			<div class="field hf-hours"><span>Dauer</span><div class="hf-dur"><input type="text" name="hours" inputmode="decimal" placeholder="1,5 oder 1:30" value="${e.hours ? qty(e.hours) : ''}" ${e.state === 'billed' ? 'disabled' : ''}>
				<div class="hf-quick">${[0.25, 0.5, 1].map((h) => `<button type="button" class="chip" data-add="${h}">+${h === 0.25 ? '¼' : h === 0.5 ? '½' : '1'}</button>`).join('')}</div></div></div>
			<label class="field hf-proj"><span>Projekt <small>(optional)</small></span><input type="text" name="project" value="${esc(e.project || '')}" list="hf-projects-${o.id || 'x'}" placeholder="z. B. Website-Relaunch"><datalist id="hf-projects-${o.id || 'x'}">${(o.projects || []).map((p) => `<option>${esc(p)}</option>`).join('')}</datalist></label>
			<label class="field hf-note"><span>Was wurde gemacht?</span><textarea name="note" rows="${o.compact ? 2 : 3}" placeholder="Kurz notieren – erscheint auf Wunsch in der Rechnung">${esc(e.note || '')}</textarea></label>
			<div class="hf-actions">
				${e.id ? '' : `<button type="button" class="btn" data-timer>${icon(timer.get() ? 'ban' : 'play')} <span data-timer-label>${timer.get() ? timer.label() : 'Stoppuhr'}</span></button>`}
				<span class="grow"></span>
				<button type="submit" class="btn primary">${icon('check')} ${e.id ? 'Speichern' : 'Stunden erfassen'}</button>
			</div>
		</form>`;
		const f = $('form', box);
		if (!o.customer) autocomplete($('[name=cust]', f), (q) => customerSource(q).filter((x) => !x.value._new), (c) => { cust = c; $('[name=cust]', f).value = c.name; $('[name=hours]', f).focus(); }, { always: true });
		$$('[data-add]', f).forEach((b) => (b.onclick = () => { const i = $('[name=hours]', f); i.value = qty(parseHours(i.value) + +b.dataset.add); i.focus(); }));
		// Stoppuhr
		let tick = null;
		const tb = $('[data-timer]', f);
		const drawTimer = () => { if (!tb) return; const on = !!timer.get(); tb.classList.toggle('running', on); tb.innerHTML = `${icon(on ? 'ban' : 'play')} <span data-timer-label>${on ? timer.label() : 'Stoppuhr'}</span>`; };
		const run = () => { clearInterval(tick); if (timer.get()) tick = setInterval(() => { if (!document.body.contains(f)) return clearInterval(tick); const l = $('[data-timer-label]', f); if (l) l.textContent = timer.label(); }, 1000); };
		if (tb) tb.onclick = () => {
			if (timer.get()) {
				const h = Math.max(0.25, Math.ceil(timer.stop() * 4) / 4); // auf Viertelstunden aufrunden
				$('[name=hours]', f).value = qty(h);
				toast('Stoppuhr angehalten – ' + fmtH(h) + ' eingetragen');
				$('[name=note]', f).focus();
			} else { timer.start(); toast('Stoppuhr läuft'); }
			drawTimer(); run();
		};
		drawTimer(); run();
		f.onsubmit = async (ev) => {
			ev.preventDefault();
			const c = o.customer || cust;
			const h = parseHours($('[name=hours]', f).value);
			if (!c) return fail(new Error('Bitte einen Kunden wählen.'));
			if (!(h > 0)) return fail(new Error('Bitte die Dauer angeben, z. B. 1,5 oder 1:30.'));
			try {
				const saved = await api('hours_save', { id: e.id || 0, customer_id: +c.id, date: $('[name=date]', f).value, hours: e.state === 'billed' ? e.hours : h, project: $('[name=project]', f).value, note: $('[name=note]', f).value });
				store.set('hours.customer', +c.id);
				if (!e.id) { $('[name=hours]', f).value = ''; $('[name=note]', f).value = ''; }
				toast(e.id ? 'Gespeichert' : fmtH(saved.hours) + ' für ' + (c.name || '') + ' erfasst');
				o.onSaved?.(saved);
			} catch (er) { fail(er); }
		};
		return f;
	}

	function hourDrawer(entry, after) {
		const el = drawer(entry.id ? 'Stunden bearbeiten' : 'Stunden erfassen', '<div id="hd"></div>', entry.id && entry.state !== 'billed' ? `<button class="btn ghost danger" data-del>${icon('trash')} Löschen</button>` : '');
		hourForm($('#hd', el), { entry, customer: entry.customer_id && entry.fixed ? customerById(entry.customer_id) : null, id: 'd', onSaved: (t) => { closeTop(); after?.(t); } });
		$('[data-del]', el)?.addEventListener('click', async () => {
			if (!await confirmDialog('Eintrag löschen?', `${fmtH(entry.hours)} vom ${date(entry.date)} werden gelöscht.`, 'Löschen', { danger: true })) return;
			try { await api('hours_delete', { id: entry.id }); closeTop(); toast('Gelöscht'); after?.(); } catch (e) { fail(e); }
		});
	}

	async function viewHours(q) {
		const main = $('#main');
		let data = await api('hours');
		let tab = q.f === 'billed' || q.rechnung ? 'billed' : 'open';
		let group = q.rechnung ? 'invoice' : q.g || store.get('hours.g', 'year');
		let cust = q.kunde ? +q.kunde : 0, search = '', focus = q.rechnung ? 'i' + q.rechnung : '';
		const sel = new Set();
		const opened = new Set(focus ? [focus] : []);
		const sumH = (rows) => rows.reduce((a, t) => a + t.hours, 0);
		const isBilled = (t) => t.state === 'billed';
		const MON = ['Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
		main.innerHTML = `<div class="page">
			${pageHead('Stunden', { sub: 'Zeit für Kunden und Projekte mitschreiben – und mit einem Klick abrechnen' }, `<a class="btn" href="api.php?a=export_hours">${icon('download')} CSV</a>`)}
			<div class="grid g-main">
				<div class="grid" style="align-content:start">
					<section class="card card-pad"><h2 style="margin-bottom:14px">Stunden erfassen</h2><div id="hform"></div></section>
					<section class="card">
						<nav class="tabs hours-tabs" id="htabs" role="tablist" aria-label="Stunden"></nav>
						<div class="card-body" style="padding-top:14px">
							<div class="toolbar" style="margin-bottom:12px">
								<div id="hgroup"></div><div class="grow"></div>
								<select id="hcust" style="width:auto;max-width:220px" aria-label="Kunde"></select>
								<label class="search" style="min-width:160px"><span class="sr">Suchen</span>${icon('search')}<input type="search" id="hq" placeholder="Notiz, Projekt, Rechnung …"></label>
							</div>
							<div id="hbulk"></div>
							<div id="hlist"></div>
						</div>
					</section>
				</div>
				<div class="grid" style="align-content:start" id="hside"></div>
			</div></div>`;
		const reload = async () => { data = await api('hours'); draw(); side(); };
		hourForm($('#hform'), { projects: data.summary.projects, id: 'p', onSaved: reload });
		const side = () => {
			const sm = data.summary;
			const y = String(new Date().getFullYear());
			const billedY = sumH(data.entries.filter((t) => isBilled(t) && t.date.startsWith(y)));
			$('#hside').innerHTML = `
				<div class="grid g2" style="gap:12px">
					<div class="card kpi"><div class="label">Diese Woche</div><div class="value num">${fmtH(sm.week)}</div></div>
					<div class="card kpi"><div class="label">Dieser Monat</div><div class="value num">${fmtH(sm.month)}</div></div>
				</div>
				<section class="card">
					<div class="card-head"><h2>Offen nach Kunde</h2><span class="muted num" style="font-size:13px">${fmtH(sm.open_h)}${sm.open_val ? ' · ' + money(sm.open_val) : ''}</span></div>
					<div class="card-body" style="padding-top:4px">${sm.open.length ? `<div class="list">${sm.open.map((o) => `
						<div class="list-item"><div class="li-main"><div class="li-title"><a href="#/kunde/${o.customer_id}" style="text-decoration:none">${esc(o.customer_name)}</a></div>
							<div class="li-sub" style="white-space:normal">${fmtH(o.hours)}${o.rate ? ' · <b>' + money(o.value) + '</b>' : ''} · ${o.entries} Eintr${o.entries === 1 ? 'ag' : 'äge'} seit ${date(o.since)}</div></div>
							<a class="btn sm" href="#/rechnung/neu?kunde=${o.customer_id}&stunden=1" title="Rechnung mit diesen Stunden erstellen">${icon('file')} Rechnung</a>
						</div>`).join('')}</div>` : '<div class="empty"><span class="big">[ ✓ ]</span>Alles verrechnet.</div>'}</div>
				</section>
				<div class="card kpi"><div class="label">Verrechnet ${y}</div><div class="value num">${fmtH(billedY)}</div><div class="sub"><button type="button" class="linkish" data-showbilled>Verrechnete Stunden ansehen</button></div></div>
				${sm.rate ? '' : `<div class="hint">Tipp: Unter <a href="#/einstellungen/nummern">Einstellungen → Nummern & Steuer</a> einen Standard-Stundensatz eintragen, pro Kunde lässt er sich überschreiben.</div>`}`;
			$('[data-showbilled]').onclick = () => { tab = 'billed'; sel.clear(); draw(); $('#htabs').scrollIntoView({ behavior: 'smooth', block: 'start' }); };
		};
		const matches = (t, n) => !n || norm([t.note, t.project, t.customer_name, t.invoice_number].join(' ')).includes(n);

		/* Offene Einträge: Tabelle mit Auswahl */
		const openTable = (rows) => rows.length ? `<div class="table-wrap"><table class="table resp"><thead><tr><th style="width:36px"><input type="checkbox" id="hall" aria-label="Alle auswählen"></th><th>Datum</th><th>Kunde · Projekt</th><th class="th-r">Dauer</th><th>Status</th></tr></thead><tbody>
			${rows.map((t) => `<tr class="click" data-id="${t.id}">
				<td class="m-a"><input type="checkbox" data-sel="${t.id}" ${sel.has(t.id) ? 'checked' : ''} aria-label="Auswählen"></td>
				<td class="m-c muted nowrap">${date(t.date)}</td>
				<td class="m-b strong">${esc(t.customer_name)}${t.project ? ` <span class="muted" style="font-weight:500">· ${esc(t.project)}</span>` : ''}<div class="sub" style="white-space:normal;max-width:none">${esc(t.note)}</div></td>
				<td class="m-d td-r num strong">${fmtH(t.hours)}</td>
				<td class="m-e">${t.state === 'draft' ? `<a class="badge b-draft" href="#/rechnung/${t.invoice_id}" title="In einem Rechnungsentwurf – mit dem Ausstellen verrechnet">im Entwurf</a>` : hourBadge(t)}</td></tr>`).join('')}</tbody></table></div>
			<div class="sumbar"><span>${rows.length} Einträge</span><span>Summe <b class="num">${fmtH(sumH(rows))}</b></span></div>`
			: `<div class="empty"><span class="big">[ ✓ ]</span>${search || cust ? 'Keine passenden offenen Stunden.' : 'Keine offenen Stunden – alles verrechnet.'}</div>`;

		/* Verrechnete Einträge: gruppiert nach Jahr oder nach Rechnung */
		const billedGroups = (rows) => {
			const map = new Map();
			rows.forEach((t) => {
				const k = group === 'year' ? 'y' + t.date.slice(0, 4) : t.invoice_id ? 'i' + t.invoice_id : 'm';
				if (!map.has(k)) map.set(k, []);
				map.get(k).push(t);
			});
			const list = [...map.entries()].map(([k, es]) => ({ k, es, t: es[0], h: sumH(es) }));
			if (group === 'year') list.sort((a, b) => b.k.localeCompare(a.k));
			else list.sort((a, b) => (a.k === 'm') - (b.k === 'm') || String(b.t.invoice_date).localeCompare(String(a.t.invoice_date)) || (+b.t.invoice_number || 0) - (+a.t.invoice_number || 0));
			return list;
		};
		const groupHead = (g) => {
			const custs = new Set(g.es.map((t) => t.customer_id)).size;
			if (group === 'year') {
				const invs = new Set(g.es.filter((t) => t.invoice_id).map((t) => t.invoice_id)).size;
				return `<span class="hg-title num">${g.k.slice(1)}</span>
					<span class="hg-meta">${g.es.length} Eintr${g.es.length === 1 ? 'ag' : 'äge'} · ${custs} Kunde${custs === 1 ? '' : 'n'} · ${invs} Rechnung${invs === 1 ? '' : 'en'}</span>`;
			}
			if (g.k === 'm') return `<span class="hg-title">Ohne Rechnung</span><span class="hg-meta">von Hand als verrechnet markiert · ${custs} Kunde${custs === 1 ? '' : 'n'}</span>`;
			return `<span class="hg-title">Rechnung <span class="mono">${esc(g.t.invoice_number)}</span></span>
				<span class="hg-meta">${esc(g.t.customer_name)} · ${date(g.t.invoice_date)}${g.t.invoice_status === 'cancelled' ? ' · storniert' : ''}</span>
`;
		};
		const groupLink = (g) => group === 'invoice' && g.k !== 'm' ? `<a class="btn sm ghost hg-open" href="#/rechnung/${g.t.invoice_id}" title="Rechnung öffnen">${icon('file')} ${money(g.t.invoice_gross)}</a>` : '';
		const months = (es) => {
			const m = Array(12).fill(0);
			es.forEach((t) => (m[+t.date.slice(5, 7) - 1] += t.hours));
			const max = Math.max(...m) || 1;
			return `<div class="hg-months" aria-label="Stunden je Monat">${m.map((h, i) => `<div class="hg-m ${h ? '' : 'zero'}" title="${MON[i]}: ${fmtH(h)}"><i style="height:${Math.max(h ? 6 : 2, Math.round((h / max) * 100))}%"></i><span>${MON[i]}</span></div>`).join('')}</div>`;
		};
		const billedRow = (t) => `<div class="hg-row" data-id="${t.id}">
			${t.invoice_id ? '<span class="hg-chk"></span>' : `<input type="checkbox" data-sel="${t.id}" ${sel.has(t.id) ? 'checked' : ''} aria-label="Auswählen">`}
			<span class="mono muted hg-date">${date(t.date)}</span>
			<div class="hg-main"><div class="hg-what">${group === 'invoice' && t.invoice_id ? '' : `<b>${esc(t.customer_name)}</b>`}${t.project ? `${group === 'invoice' && t.invoice_id ? '<b>' + esc(t.project) + '</b>' : ' <span class="muted">· ' + esc(t.project) + '</span>'}` : ''}</div>
				${t.note ? `<div class="hg-note">${esc(t.note)}</div>` : ''}</div>
			${group === 'year' ? (t.invoice_id ? `<a class="hg-inv" href="#/rechnung/${t.invoice_id}">${icon('file')} ${esc(t.invoice_number)}</a>` : '<span class="hg-inv manual">von Hand</span>') : ''}
			<b class="num hg-h">${fmtH(t.hours)}</b></div>`;
		const billedView = (rows) => {
			if (!rows.length) return `<div class="empty"><span class="big">[ ]</span>${search || cust ? 'Keine passenden verrechneten Stunden.' : 'Noch keine Stunden verrechnet. Offene Stunden übernimmst du im Rechnungs-Editor – als Position oder nur bestätigt.'}</div>`;
			const list = billedGroups(rows);
			if (!list.some((g) => opened.has(g.k))) opened.add(list[0].k);
			return `<div class="hgroups">${list.map((g) => `<details class="hgroup" data-gk="${g.k}" ${opened.has(g.k) ? 'open' : ''}>
				<summary>${icon('right')}<span class="hg-head">${groupHead(g)}</span>${groupLink(g)}<b class="num hg-sum">${fmtH(g.h)}</b></summary>
				<div class="hg-body">${group === 'year' ? months(g.es) : ''}${g.es.map(billedRow).join('')}</div>
			</details>`).join('')}</div>
			<div class="sumbar"><span>${rows.length} Einträge in ${list.length} ${group === 'year' ? (list.length === 1 ? 'Jahr' : 'Jahren') : list.length === 1 ? 'Gruppe' : 'Gruppen'}</span><span>Summe <b class="num">${fmtH(sumH(rows))}</b></span></div>`;
		};

		const draw = () => {
			const all = data.entries;
			const base = all.filter((t) => !cust || +t.customer_id === cust);
			const open = base.filter((t) => !isBilled(t)), billed = base.filter(isBilled);
			$('#htabs').innerHTML = [['open', 'Offen', open], ['billed', 'Verrechnet', billed]].map(([k, l, r]) =>
				`<button type="button" role="tab" aria-selected="${tab === k}" class="${tab === k ? 'on' : ''}" data-tab="${k}">${l} <span class="n">${fmtH(sumH(r))}</span></button>`).join('');
			$$('#htabs [data-tab]').forEach((b) => (b.onclick = () => { tab = b.dataset.tab; sel.clear(); draw(); }));
			$('#hgroup').innerHTML = tab === 'billed' ? `<div class="seg" role="group" aria-label="Gruppieren">${[['year', 'Nach Jahr'], ['invoice', 'Nach Rechnung']].map(([k, l]) => `<button type="button" data-g="${k}" class="${group === k ? 'on' : ''}">${l}</button>`).join('')}</div>` : '';
			$$('#hgroup [data-g]').forEach((b) => (b.onclick = () => { group = b.dataset.g; store.set('hours.g', group); opened.clear(); draw(); }));
			const custs = [...new Map(all.map((t) => [+t.customer_id, t.customer_name])).entries()].sort((a, b) => String(a[1]).localeCompare(String(b[1])));
			$('#hcust').innerHTML = `<option value="0">Alle Kunden</option>` + custs.map(([id, n]) => `<option value="${id}" ${id === cust ? 'selected' : ''}>${esc(n)}</option>`).join('');
			const n = norm(search);
			const rows = (tab === 'open' ? open : billed).filter((t) => matches(t, n));
			$('#hlist').innerHTML = tab === 'open' ? openTable(rows) : billedView(rows);
			$$('#hlist [data-id]').forEach((el) => (el.onclick = (e) => { if (e.target.closest('input, a')) return; hourDrawer(data.entries.find((t) => t.id === +el.dataset.id), reload); }));
			$$('#hlist [data-sel]').forEach((c) => (c.onchange = () => { c.checked ? sel.add(+c.dataset.sel) : sel.delete(+c.dataset.sel); bulk(); }));
			$$('#hlist details.hgroup').forEach((d) => d.addEventListener('toggle', () => (d.open ? opened.add(d.dataset.gk) : opened.delete(d.dataset.gk))));
			$('#hall')?.addEventListener('change', (e) => { rows.forEach((t) => (e.target.checked ? sel.add(t.id) : sel.delete(t.id))); draw(); });
			if (focus) { $(`#hlist [data-gk="${focus}"]`)?.scrollIntoView({ block: 'center' }); focus = ''; }
			bulk();
		};
		const bulk = () => {
			const picked = data.entries.filter((t) => sel.has(t.id));
			const h = fmtH(sumH(picked));
			const one = [...new Set(picked.map((t) => +t.customer_id))];
			if (!picked.length) { $('#hbulk').innerHTML = ''; return; }
			$('#hbulk').innerHTML = tab === 'open'
				? `<div class="bulkbar"><b>${picked.length} ausgewählt · ${h}</b><span class="grow"></span>
					${one.length === 1 ? `<a class="btn sm primary" href="#/rechnung/neu?kunde=${one[0]}&stunden=${[...sel].join('.')}">${icon('file')} Rechnung erstellen</a>` : ''}
					<button class="btn sm" data-markbilled>${icon('check')} Als verrechnet markieren</button></div>`
				: `<div class="bulkbar"><b>${picked.length} ausgewählt · ${h}</b><span class="grow"></span><button class="btn sm" data-reopen>${icon('undo')} Wieder öffnen</button></div>`;
			$('[data-markbilled]', main)?.addEventListener('click', async () => {
				if (!await confirmDialog('Als verrechnet markieren?', `${picked.length} Einträge (${h}) werden ohne Rechnung als verrechnet markiert – z. B. bar bezahlt oder pauschal verrechnet. Sie erscheinen unter „Verrechnet“ in der Gruppe „Ohne Rechnung“.`, 'Markieren')) return;
				try { await api('hours_mark', { ids: [...sel], billed: 1 }); sel.clear(); toast('Als verrechnet markiert'); reload(); } catch (e) { fail(e); }
			});
			$('[data-reopen]', main)?.addEventListener('click', async () => {
				try { await api('hours_mark', { ids: [...sel], billed: 0 }); sel.clear(); toast(`${h} wieder offen`); reload(); } catch (e) { fail(e); }
			});
		};
		$('#hcust').onchange = (e) => { cust = +e.target.value; sel.clear(); draw(); };
		$('#hq').oninput = debounce((e) => { search = e.target.value; draw(); }, 120);
		draw(); side();
	}

	/* ================================================================ Kunden */

	async function viewCustomers() {
		const main = $('#main');
		let search = '', showArchived = false;
		main.innerHTML = `<div class="page">
			${pageHead('Kunden', { sub: `${S.customers.filter((c) => !+c.archived).length} aktive Kunden` }, `<button class="btn primary" data-new>${icon('plus')} Neuer Kunde</button>`)}
			<div class="toolbar"><label class="search" style="flex:1;max-width:420px"><span class="sr">Suchen</span>${icon('search')}<input type="search" id="q" placeholder="Name, Ort, Nummer …"></label><div class="grow"></div>
				<label class="switch" style="font-size:13px"><input type="checkbox" id="arch"> Archivierte zeigen</label></div>
			<div class="card"><div class="table-wrap" id="list"></div></div></div>`;
		const draw = () => {
			const n = norm(search);
			const rows = S.customers.filter((c) => (showArchived || !+c.archived) && (!n || norm([c.name, c.person, c.city, c.number, c.email].join(' ')).includes(n)));
			$('#list').innerHTML = rows.length ? `<table class="table resp"><thead><tr><th>Nr.</th><th>Kunde</th><th class="hide-m">Ort</th><th class="hide-m">Letzte Rechnung</th><th class="th-r">Offen</th><th class="th-r">Umsatz gesamt</th></tr></thead><tbody>
				${rows.map((c) => `<tr class="click" data-id="${c.id}" style="${+c.archived ? 'opacity:.55' : ''}">
					<td class="m-hide mono muted">${esc(c.number)}</td>
					<td class="m-b m-wide strong"><div style="display:flex;gap:8px;align-items:center">${withRec(esc(c.name), +c.recurring_count, 'Bekommt eine Dauerrechnung')}</div><div class="sub">${esc([c.person, c.email].filter(Boolean).join(' · '))}</div></td>
					<td class="m-c m-wide muted">${esc(c.city)}</td>
					<td class="m-hide muted">${date(c.last_invoice) || '—'}</td>
					<td class="m-e td-r num" style="${+c.open_amount ? 'color:var(--accent);font-weight:700' : 'color:var(--faint)'}">${+c.open_amount ? money(c.open_amount) : '—'}</td>
					<td class="m-d td-r num strong">${money(c.revenue)}</td></tr>`).join('')}</tbody></table>`
				: '<div class="empty"><span class="big">[ ]</span>Keine Kunden gefunden.</div>';
			$$('#list tr[data-id]').forEach((tr) => (tr.onclick = () => go('#/kunde/' + tr.dataset.id)));
		};
		$('#q').oninput = (e) => { search = e.target.value; draw(); };
		$('#arch').onchange = (e) => { showArchived = e.target.checked; draw(); };
		$('[data-new]').onclick = () => customerDrawer({}, (c) => go('#/kunde/' + c.id));
		draw();
	}

	async function viewCustomer(id) {
		const main = $('#main');
		const c = await api('customer', undefined, { query: { id } });
		const issued = c.invoices.filter((i) => i.status !== 'draft');
		const revenue = issued.reduce((a, i) => a + i.gross, 0);
		const open = c.invoices.filter((i) => i.state === 'open' || i.state === 'overdue' || i.state === 'partial');
		const years = {};
		issued.forEach((i) => { const y = i.invoice_date.slice(0, 4); years[y] = (years[y] || 0) + i.gross; });
		main.innerHTML = `<div class="page">
			${pageHead(esc(c.name), { back: ['#/kunden', 'Kunden'], sub: `Kundennummer <span class="mono">${esc(c.number)}</span>${+c.archived ? ' – archiviert' : ''}` },
				`<button class="btn" data-edit>${icon('edit')} Bearbeiten</button><button class="btn" data-rec>${icon('repeat')}<span class="hide-m">Dauerrechnung</span></button><a class="btn" href="#/angebot/neu?kunde=${c.id}">${icon('offer')}<span class="hide-m">Angebot</span></a><a class="btn primary" href="#/rechnung/neu?kunde=${c.id}">${icon('plus')} Rechnung</a>`)}
			<div class="grid g4 kpis" style="margin-bottom:16px">
				<div class="card kpi"><div class="label">Umsatz gesamt</div><div class="value num">${moneyShort(revenue)}</div><div class="sub">${issued.filter((i) => i.kind === 'invoice').length} Rechnungen</div></div>
				<div class="card kpi"><div class="label">Offen</div><div class="value num">${money(open.reduce((a, i) => a + i.open, 0))}</div><div class="sub">${open.length} Rechnung${open.length === 1 ? '' : 'en'}</div></div>
				<div class="card kpi"><div class="label">Kunde seit</div><div class="value">${c.created_at ? c.created_at.slice(0, 4) : '—'}</div><div class="sub">letzte Rechnung ${date(issued[0]?.invoice_date) || '—'}</div></div>
				<div class="card kpi"><div class="label">Dauerrechnung</div><div class="value num">${c.recurring.filter((r) => +r.active).length ? money(c.recurring.filter((r) => +r.active).reduce((a, r) => a + r.yearly, 0)) : '—'}</div><div class="sub">${c.recurring.filter((r) => +r.active).length ? 'pro Jahr' : 'keine aktiv'}</div></div>
			</div>
			<div class="grid g-main">
				<div class="grid" style="align-content:start">
				<div class="card"><div class="card-head"><h2>Rechnungen</h2></div><div class="table-wrap">${c.invoices.length ? `<table class="table resp"><tbody>
					${c.invoices.map((i) => `<tr class="click" data-id="${i.id}">
						<td class="m-a"><button class="check ${i.state === 'paid' ? 'on' : i.state === 'partial' ? 'half' : ''} ${['open', 'overdue', 'paid', 'partial'].includes(i.state) ? '' : 'na'}" data-pay="${i.id}" aria-label="Bezahlt">${icon('check')}</button></td>
						<td class="m-b"><span class="mono">${esc(i.number || 'Entwurf')}</span> <span class="muted">· ${date(i.invoice_date)}</span><div class="sub">${esc(i.item_names || '')}</div></td>
						<td class="m-e">${badge(i.state)}</td>
						<td class="m-d td-r num strong">${money(i.gross)}</td></tr>`).join('')}</tbody></table>` : '<div class="empty">Noch keine Rechnungen.</div>'}</div></div>
				${(() => {
					const openH = c.hours.filter((t) => t.state !== 'billed');
					const sumH = openH.reduce((a, t) => a + t.hours, 0);
					return `<section class="card" id="chours">
						<div class="card-head"><h2>Stunden</h2>
							${sumH ? `<span class="muted num" style="font-size:13px">${fmtH(sumH)} offen${c.rate ? ' · ' + money(sumH * c.rate) : ''}</span>` : ''}
							${openH.some((t) => t.state === 'open') ? `<a class="btn sm primary" href="#/rechnung/neu?kunde=${c.id}&stunden=1">${icon('file')} Abrechnen</a>` : ''}</div>
						<div class="card-body">
							<details class="hours-add" ${c.hours.length ? '' : 'open'}><summary>${icon('plus')} Stunden erfassen</summary><div id="chform" style="margin-top:12px"></div></details>
							${c.hours.length ? `<div class="list" style="margin-top:8px">${c.hours.slice(0, 8).map((t) => `
								<button type="button" class="list-item hour-item" data-hid="${t.id}"><span class="mono muted">${date(t.date)}</span>
									<div class="li-main"><div class="li-title">${t.project ? esc(t.project) : '<span class="muted">Ohne Projekt</span>'}</div><div class="li-sub">${esc(t.note)}</div></div>
									${hourBadge(t)}<b class="num">${fmtH(t.hours)}</b></button>`).join('')}</div>
								${c.hours.length > 8 ? `<a class="btn sm ghost" href="#/stunden?kunde=${c.id}" style="margin-top:8px">Alle ${c.hours.length} Einträge ${icon('right')}</a>` : ''}` : ''}
						</div></section>`;
				})()}
				</div>
				<div class="grid" style="align-content:start">
					<div class="card card-pad">
						<h3 style="margin-bottom:10px">Anschrift</h3>
						<div style="line-height:1.6">${c.lines.map((l, i) => i === 0 ? `<b>${esc(l)}</b>` : esc(l)).join('<br>')}</div>
						<div style="margin-top:14px;display:flex;flex-direction:column;gap:6px;font-size:13.5px">
							${c.email ? `<a href="mailto:${esc(c.email)}">${icon('mail')} ${esc(c.email)}</a>` : `<span class="note-warn">${icon('alert')}<span>Keine E-Mail-Adresse – nötig für den automatischen Versand.</span></span>`}
							${c.phone ? `<a href="tel:${esc(c.phone)}">${esc(c.phone)}</a>` : ''}
							${c.website ? `<a href="${esc(/^https?:/.test(c.website) ? c.website : 'https://' + c.website)}" target="_blank" rel="noopener">${esc(c.website)}</a>` : ''}
						</div>
						${c.note ? `<div class="hint" style="margin-top:14px;white-space:pre-wrap">${esc(c.note)}</div>` : ''}
					</div>
					${c.offers.length ? `<div class="card"><div class="card-head"><h2>Angebote</h2></div><div class="card-body" style="padding-top:4px"><div class="list">${c.offers.map((o) => `
						<a class="list-item" href="#/angebot/${o.id}"><div class="li-main"><div class="li-title"><span class="mono">${esc(o.number || 'Entwurf')}</span> · ${date(o.invoice_date)}</div><div class="li-sub">${esc(o.subject || o.item_names || '')}</div></div>${badge(o.state)}<b class="num">${money(o.gross)}</b></a>`).join('')}</div></div></div>` : ''}
					<div class="card"><div class="card-head"><h2>Dauerrechnungen</h2></div><div class="card-body" style="padding-top:4px">${c.recurring.length ? `<div class="list">${c.recurring.map((r) => `
						<a class="list-item" href="#/dauerrechnungen?id=${r.id}" style="${+r.active ? '' : 'opacity:.55'}"><div class="li-main"><div class="li-title">${esc(r.title || 'Dauerrechnung')}</div><div class="li-sub">${INTERVALS[r.interval_months]} · nächste ${date(r.next_date)}${+r.active ? '' : ' · pausiert'}</div></div><b class="num">${money(r.gross)}</b></a>`).join('')}</div>` : '<div class="empty">Keine.</div>'}</div></div>
					${Object.keys(years).length ? `<div class="card"><div class="card-head"><h2>Umsatz pro Jahr</h2></div><div class="card-body">${yearBars(Object.entries(years).sort().map(([y, r]) => ({ y, revenue: r, n: 0 })))}</div></div>` : ''}
				</div>
			</div></div>`;
		$$('tr[data-id]', main).forEach((tr) => (tr.onclick = (e) => { if (!e.target.closest('.check')) go('#/rechnung/' + tr.dataset.id); }));
		$$('[data-pay]', main).forEach((b) => (b.onclick = async (e) => {
			e.stopPropagation();
			const inv = c.invoices.find((i) => +i.id === +b.dataset.pay);
			if (inv.state === 'paid') { await api('invoice_unpay', { id: inv.id }).catch(fail); await refresh(); viewCustomer(id); }
			else quickPay(inv.id, b, () => viewCustomer(id));
		}));
		hourForm($('#chform'), { customer: c, compact: true, id: 'c', onSaved: () => viewCustomer(id) });
		$$('[data-hid]', main).forEach((b) => (b.onclick = () => hourDrawer({ ...c.hours.find((t) => t.id === +b.dataset.hid), fixed: true }, () => viewCustomer(id))));
		$('[data-edit]').onclick = () => customerDrawer(c, () => viewCustomer(id));
		$('[data-rec]').onclick = () => recurringDrawer({ customer_id: +c.id }, () => viewCustomer(id));
	}

	function customerDrawer(c, after) {
		const isNew = !c.id;
		const v = (k) => esc(c[k] ?? '');
		const el = drawer(isNew ? 'Neuer Kunde' : 'Kunde bearbeiten', `
			<form class="form-grid" id="cf" autocomplete="off">
				<label class="field c4"><span>Firma</span><input type="text" name="company" value="${v('company')}" autofocus></label>
				<label class="field c2"><span>Kundennummer</span><input type="text" name="number" value="${v('number')}" placeholder="automatisch" class="mono"></label>
				<label class="field c6"><span>Firmenzusatz <small>(2. Zeile, optional)</small></span><input type="text" name="company2" value="${v('company2')}"></label>
				<label class="field c2"><span>Anrede</span><select name="salutation">${[['', '–'], ['herr', 'Herr'], ['frau', 'Frau'], ['familie', 'Familie']].map(([k, l]) => `<option value="${k}" ${c.salutation === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
				<label class="field c4"><span>Ansprechperson <small>(Vor- und Nachname)</small></span><input type="text" name="person" value="${v('person')}"></label>
				<label class="field c6"><span>Straße</span><input type="text" name="street" value="${v('street')}"></label>
				<label class="field c2"><span>PLZ</span><input type="text" name="zip" value="${v('zip')}" inputmode="numeric"></label>
				<label class="field c4"><span>Ort</span><input type="text" name="city" value="${v('city')}"></label>
				<label class="field c3"><span>Land</span><input type="text" name="country" value="${esc(c.country ?? 'Österreich')}"></label>
				<label class="field c3"><span>UID-Nummer</span><input type="text" name="vat_id" value="${v('vat_id')}" placeholder="ATU…"></label>
				<div class="section-title c6">Kontakt & Versand</div>
				<label class="field c6"><span>E-Mail <small>(Rechnungen gehen hierhin)</small></span><input type="email" name="email" value="${v('email')}" placeholder="buchhaltung@firma.at"></label>
				<label class="field c6"><span>E-Mail in Kopie <small>(optional, mehrere mit Komma)</small></span><input type="text" name="email_cc" value="${v('email_cc')}"></label>
				<label class="field c3"><span>Telefon</span><input type="tel" name="phone" value="${v('phone')}"></label>
				<label class="field c3"><span>Website</span><input type="text" name="website" value="${v('website')}"></label>
				<label class="field c3"><span>Stundensatz € <small>(leer = Standard${S.settings.hour_rate ? ' ' + esc(S.settings.hour_rate) + ' €' : ''})</small></span><input type="text" inputmode="decimal" name="hour_rate" value="${c.hour_rate !== null && c.hour_rate !== undefined && c.hour_rate !== '' ? String(c.hour_rate).replace('.', ',') : ''}"></label>
				<label class="field c3"><span>Zahlungsziel</span><select name="payment_days"><option value="">Standard (${S.settings.payment_days} Tage)</option>${[0, 7, 14, 21, 30].map((d) => `<option value="${d}" ${c.payment_days !== null && c.payment_days !== undefined && c.payment_days !== '' && +c.payment_days === d ? 'selected' : ''}>${d ? d + ' Tage' : 'sofort'}</option>`).join('')}</select></label>
				<label class="field c6"><span>Notiz</span><textarea name="note" rows="3">${v('note')}</textarea></label>
				${isNew ? '' : `<label class="switch c6"><input type="checkbox" name="archived" ${+c.archived ? 'checked' : ''}> Archiviert (ausgeblendet, Rechnungen bleiben)</label>`}
			</form>`,
			`${isNew ? '' : `<button class="btn ghost danger" data-del>${icon('trash')} Löschen</button>`}<span class="grow"></span><button class="btn" data-close>Abbrechen</button><button class="btn primary" data-ok>Speichern</button>`);
		plzAssist($('[name=zip]', el), $('[name=city]', el), $('[name=country]', el));
		const submit = async () => {
			const fd = Object.fromEntries(new FormData($('#cf', el)));
			fd.archived = $('[name=archived]', el)?.checked ? 1 : 0;
			try {
				const saved = await api('customer_save', { ...fd, id: c.id || 0 });
				await refresh();
				closeTop();
				toast(isNew ? 'Kunde angelegt' : 'Gespeichert');
				after?.(saved);
			} catch (e) { fail(e); }
		};
		$('[data-ok]', el).onclick = submit;
		$('#cf', el).onsubmit = (e) => { e.preventDefault(); submit(); };
		$('[data-del]', el)?.addEventListener('click', async () => {
			if (!await confirmDialog('Kunde löschen?', 'Hat der Kunde Rechnungen, wird er nur archiviert und seine Dauerrechnungen pausiert.', 'Löschen', { danger: true })) return;
			try { const r = await api('customer_delete', { id: c.id }); await refresh(); closeTop(); toast(r.result === 'archived' ? 'Kunde archiviert' : 'Kunde gelöscht'); go('#/kunden'); } catch (e) { fail(e); }
		});
	}

	/* ================================================================ Artikel */

	async function viewProducts() {
		const main = $('#main');
		let search = '', showArchived = false;
		main.innerHTML = `<div class="page">
			${pageHead('Artikel', { sub: 'Leistungen und Preise, die du in Rechnungen übernehmen kannst' }, `<button class="btn primary" data-new>${icon('plus')} Neuer Artikel</button>`)}
			<div class="toolbar"><label class="search" style="flex:1;max-width:420px"><span class="sr">Suchen</span>${icon('search')}<input type="search" id="q" placeholder="Bezeichnung, Nummer …"></label><div class="grow"></div>
				<label class="switch" style="font-size:13px"><input type="checkbox" id="arch"> Archivierte zeigen</label></div>
			<div id="list"></div></div>`;
		const draw = () => {
			const n = norm(search);
			const rows = S.products.filter((p) => (showArchived || !+p.archived) && (!n || norm(p.sku + ' ' + p.name + ' ' + p.category + ' ' + p.description).includes(n)));
			const cats = [...new Set(rows.map((p) => p.category || 'Sonstiges'))];
			$('#list').innerHTML = rows.length ? cats.map((cat) => `<div class="section-title">${esc(cat)}</div><div class="card"><table class="table resp"><tbody>
				${rows.filter((p) => (p.category || 'Sonstiges') === cat).map((p) => `<tr class="click" data-id="${p.id}" style="${+p.archived ? 'opacity:.55' : ''}">
					<td class="m-hide mono muted" style="width:80px">${esc(p.sku)}</td>
					<td class="m-b m-wide strong">${esc(p.name)} ${+p.recurring ? `<span class="badge b-open plain" title="Wiederkehrend">${icon('repeat')}</span>` : ''}<div class="sub">${esc((p.description || '').split('\n')[0])}</div></td>
					<td class="m-c m-wide muted" style="width:220px">${+p.used ? `${p.used}× verrechnet · zuletzt ${date(p.last_used)}` : 'noch nicht verrechnet'}</td>
					<td class="m-d td-r num strong" style="width:140px">${money(p.price)}<span class="muted" style="font-weight:500">${p.unit ? ' / ' + esc(p.unit) : ''}</span></td></tr>`).join('')}</tbody></table></div>`).join('')
				: '<div class="card"><div class="empty"><span class="big">[ ]</span>Keine Artikel.</div></div>';
			$$('#list tr[data-id]').forEach((tr) => (tr.onclick = () => productDrawer(S.products.find((p) => +p.id === +tr.dataset.id), draw)));
		};
		$('#q').oninput = (e) => { search = e.target.value; draw(); };
		$('#arch').onchange = (e) => { showArchived = e.target.checked; draw(); };
		$('[data-new]').onclick = () => productDrawer({}, draw);
		draw();
	}

	function productDrawer(p, after) {
		const isNew = !p.id;
		const cats = [...new Set(S.products.map((x) => x.category).filter(Boolean))];
		const el = drawer(isNew ? 'Neuer Artikel' : 'Artikel bearbeiten', `
			<form class="form-grid" id="pf">
				<label class="field c2"><span>Art.Nr.</span><input type="text" name="sku" value="${esc(p.sku || '')}" placeholder="automatisch" class="mono"></label>
				<label class="field c4"><span>Bezeichnung</span><input type="text" name="name" value="${esc(p.name || '')}" autofocus></label>
				<label class="field c6"><span>Beschreibung <small>(wird in die Rechnungsposition übernommen)</small></span><textarea name="description" rows="4">${esc(p.description || '')}</textarea></label>
				<label class="field c2"><span>Preis € <small>${small() ? '' : 'netto'}</small></span><input type="text" inputmode="decimal" name="price" value="${dec(p.price || 0)}"></label>
				<label class="field c2"><span>Einheit</span><input type="text" name="unit" value="${esc(p.unit || '')}" list="units2"><datalist id="units2"><option>Stk.</option><option>Std.</option><option>pauschal</option><option>Monat</option><option>Jahr</option></datalist></label>
				<label class="field c2 ${small() ? 'hide' : ''}"><span>USt. %</span><input type="text" inputmode="decimal" name="tax_rate" value="${p.tax_rate ?? S.settings.default_tax}"></label>
				<label class="field c3"><span>Kategorie</span><input type="text" name="category" value="${esc(p.category || '')}" list="cats"><datalist id="cats">${cats.map((c) => `<option>${esc(c)}</option>`).join('')}</datalist></label>
				<div class="c3" style="display:flex;flex-direction:column;gap:10px;justify-content:flex-end">
					<label class="switch"><input type="checkbox" name="recurring" ${+p.recurring ? 'checked' : ''}> Wiederkehrend (Hosting, Lizenz …)</label>
					${isNew ? '' : `<label class="switch"><input type="checkbox" name="archived" ${+p.archived ? 'checked' : ''}> Archiviert</label>`}
				</div>
			</form>`,
			`${isNew ? '' : `<button class="btn ghost danger" data-del>${icon('trash')}</button>`}<span class="grow"></span><button class="btn" data-close>Abbrechen</button><button class="btn primary" data-ok>Speichern</button>`);
		const submit = async () => {
			const fd = Object.fromEntries(new FormData($('#pf', el)));
			fd.price = num(fd.price); fd.tax_rate = num(fd.tax_rate ?? 0);
			fd.recurring = $('[name=recurring]', el).checked ? 1 : 0;
			fd.archived = $('[name=archived]', el)?.checked ? 1 : 0;
			try { await api('product_save', { ...fd, id: p.id || 0 }); await refresh(); closeTop(); toast('Artikel gespeichert'); after?.(); } catch (e) { fail(e); }
		};
		$('[data-ok]', el).onclick = submit;
		$('#pf', el).onsubmit = (e) => { e.preventDefault(); submit(); };
		$('[data-del]', el)?.addEventListener('click', async () => {
			try { const r = await api('product_delete', { id: p.id }); await refresh(); closeTop(); toast(r.result === 'archived' ? 'Artikel archiviert (wird in Rechnungen verwendet)' : 'Artikel gelöscht'); after?.(); } catch (e) { fail(e); }
		});
	}

	/* ================================================================ Dauerrechnungen */

	async function viewRecurring(q) {
		const main = $('#main');
		const list = S.recurring;
		const active = list.filter((r) => +r.active);
		const yearly = active.reduce((a, r) => a + r.yearly, 0);
		const due = list.filter((r) => r.due);
		const missing = active.filter((r) => r.email_missing);
		// Jahresleiste: die nächsten 12 Monate ab heute
		const start = S.today.slice(0, 7) + '-01';
		const months = Array.from({ length: 12 }, (_, i) => addMonths(start, i).slice(0, 7));
		const inMonth = (ym) => {
			const out = [];
			list.forEach((r) => {
				if (!r.next_date) return;
				let d = r.next_date;
				for (let k = 0; k < 40 && d.slice(0, 7) <= ym; k++) {
					if (d.slice(0, 7) === ym && (!r.end_date || d <= r.end_date)) out.push(r);
					d = addMonths(d, +r.interval_months);
				}
			});
			return out;
		};
		main.innerHTML = `<div class="page">
			${pageHead('Dauerrechnungen', { sub: 'Wer regelmäßig eine Rechnung bekommt und wann die nächste fällig ist' },
				`${due.length ? `<button class="btn" data-run-due>${icon('play')} ${due.length} fällige erstellen</button>` : ''}<button class="btn primary" data-new>${icon('plus')} Neue Dauerrechnung</button>`)}
			<div class="grid g4 kpis" style="margin-bottom:16px">
				<div class="card kpi"><div class="label">Pro Jahr</div><div class="value num">${money(yearly)}</div><div class="sub">aus ${active.length} aktiven</div></div>
				<div class="card kpi"><div class="label">Pro Monat</div><div class="value num">${money(yearly / 12)}</div><div class="sub">im Schnitt</div></div>
				<div class="card kpi"><div class="label">Als Nächstes</div><div class="value" style="font-size:20px">${active[0] ? date(active.slice().sort((a, b) => a.next_date.localeCompare(b.next_date))[0].next_date) : '—'}</div><div class="sub">${esc(active.slice().sort((a, b) => a.next_date.localeCompare(b.next_date))[0]?.customer_name || '')}</div></div>
				<div class="card kpi"><div class="label">Automatik</div><div class="value" style="font-size:20px">${S.mail.configured ? 'E-Mail bereit' : 'E-Mail fehlt'}</div><div class="sub">${S.settings.cron_last ? 'Cron zuletzt ' + relTime(S.settings.cron_last) : '<a href="#/einstellungen/mail">Cronjob einrichten →</a>'}</div></div>
			</div>
			${missing.length ? `<div class="note-warn" style="margin-bottom:16px">${icon('alert')}<span>${missing.length} Kunde${missing.length > 1 ? 'n haben' : ' hat'} keine E-Mail-Adresse: ${missing.map((r) => `<a href="#/kunde/${r.customer_id}">${esc(r.customer_name)}</a>`).join(', ')}. Diese Rechnungen werden ausgestellt, aber nicht verschickt.</span></div>` : ''}
			<div class="card card-pad" style="margin-bottom:16px">
				<div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:12px"><h2>Die nächsten 12 Monate</h2><span class="muted" style="font-size:12.5px">gestrichelt = pausiert</span></div>
				<div class="year-strip">${months.map((ym, i) => { const rs = inMonth(ym); const sum = rs.filter((r) => +r.active).reduce((a, r) => a + r.gross, 0); return `
					<div class="ys-col ${i === 0 ? 'now' : ''}"><div class="m"><span>${MONTHS[+ym.slice(5) - 1]}</span><span>${ym.slice(2, 4)}</span></div>
					${rs.map((r) => `<a class="ys-pill ${+r.active ? '' : 'off'}" href="#/dauerrechnungen?id=${r.id}" title="${esc(r.customer_name)} · ${money(r.gross)}">${esc(r.customer_name)}</a>`).join('')}
					${sum ? `<div class="ys-sum">${moneyShort(sum)}</div>` : ''}</div>`; }).join('')}</div>
			</div>
			<div class="card"><div class="table-wrap"><table class="table resp"><thead><tr><th>Kunde</th><th class="hide-m">Rhythmus</th><th>Nächste</th><th class="hide-m">Versand</th><th class="th-r">Betrag</th><th class="th-r hide-m">Pro Jahr</th></tr></thead><tbody>
				${list.map((r) => `<tr class="click" data-id="${r.id}" style="${+r.active ? '' : 'opacity:.55'}">
					<td class="m-b m-wide strong">${esc(r.customer_name)}<div class="sub">${esc(r.title || '')}${r.items.length ? ' · ' + esc(r.items.map((i) => i.name).join(', ')) : ''}</div></td>
					<td class="m-hide muted">${INTERVALS[r.interval_months]}</td>
					<td class="m-c">${r.due ? '<span class="badge b-open">fällig</span>' : +r.active ? date(r.next_date) : '<span class="badge">pausiert</span>'}</td>
					<td class="m-e">${r.email_missing && +r.active ? `<span class="badge b-warn">E-Mail fehlt</span>` : `<span class="badge plain">${r.mode === 'send' ? icon('send') : r.mode === 'draft' ? icon('edit') : icon('file')} ${MODE_LABEL[r.mode]}</span>`}</td>
					<td class="m-d td-r num strong">${money(r.gross)}</td>
					<td class="m-hide td-r num muted">${money(r.yearly)}</td></tr>`).join('') || '<tr><td><div class="empty">Noch keine Dauerrechnungen.</div></td></tr>'}</tbody></table></div></div>
		</div>`;
		$$('tr[data-id]', main).forEach((tr) => (tr.onclick = () => recurringDrawer(list.find((r) => +r.id === +tr.dataset.id), () => viewRecurring({}))));
		$('[data-new]').onclick = () => recurringDrawer({}, () => viewRecurring({}));
		$('[data-run-due]', main)?.addEventListener('click', runDue);
		if (q.id) { const r = list.find((x) => +x.id === +q.id); if (r) recurringDrawer(r, () => viewRecurring({})); }
	}

	function recurringDrawer(r, after) {
		const isNew = !r.id;
		const rec = JSON.parse(JSON.stringify({ interval_months: 12, mode: 'send', active: 1, next_date: S.today, items: [blankItem()], title: '', email: '', note: '', intro: '', ...r }));
		if (!rec.items.length) rec.items.push(blankItem());
		const cust = rec.customer_id ? customerById(rec.customer_id) : null;
		const el = drawer(isNew ? 'Neue Dauerrechnung' : 'Dauerrechnung', `
			<div class="form-grid">
				<div class="field c4"><span>Kunde</span><div style="position:relative"><input type="text" id="rcust" autocomplete="off" value="${esc(cust?.name || '')}" placeholder="Kunde suchen …" ${cust ? '' : 'autofocus'}></div></div>
				<label class="field c2"><span>Bezeichnung <small>(intern)</small></span><input type="text" name="title" value="${esc(rec.title)}" placeholder="z. B. Hosting"></label>
				<label class="field c2"><span>Rhythmus</span><select name="interval_months">${Object.entries(INTERVALS).map(([k, l]) => `<option value="${k}" ${+rec.interval_months === +k ? 'selected' : ''}>${l}</option>`).join('')}</select></label>
				<label class="field c2"><span>Nächste Rechnung am</span><input type="date" name="next_date" value="${rec.next_date || ''}"></label>
				<label class="field c2"><span>Endet am <small>(optional)</small></span><input type="date" name="end_date" value="${rec.end_date || ''}"></label>
				<div class="field c6"><span>Wenn fällig</span><div class="seg" id="mode">${Object.entries(MODE_LABEL).map(([k, l]) => `<button type="button" data-m="${k}" class="${rec.mode === k ? 'on' : ''}">${l}</button>`).join('')}</div>
					<small id="modehint"></small></div>
				<label class="field c6"><span>E-Mail-Empfänger <small>(leer = E-Mail des Kunden${cust?.email ? ': ' + esc(cust.email) : ''})</small></span><input type="text" name="email" value="${esc(rec.email)}" placeholder="${esc(cust?.email || 'kunde@example.com')}"></label>
			</div>
			<div class="section-title">Positionen</div>
			<div id="ritems"></div>
			<div class="totals" id="rtotals"></div>
			<div class="form-grid" style="margin-top:16px">
				<label class="field c6"><span>Einleitung <small>(leer = Standardtext)</small></span><textarea name="intro" rows="2" placeholder="${esc(S.settings.intro)}">${esc(rec.intro)}</textarea></label>
				<label class="field c6"><span>Notiz <small>(intern)</small></span><textarea name="note" rows="2">${esc(rec.note)}</textarea></label>
				<label class="switch c6"><input type="checkbox" name="active" ${+rec.active ? 'checked' : ''}> Aktiv</label>
			</div>
			${!isNew && r.invoices?.length ? '' : ''}
			${!isNew ? `<div class="hint" style="margin-top:16px">Leistungszeitraum auf der Rechnung: ab dem Fälligkeitsdatum für ${INTERVALS[rec.interval_months]}${rec.last_number ? ` · zuletzt Rechnung <b>${esc(rec.last_number)}</b>` : ''}.</div>` : ''}`,
			`${isNew ? '' : `<button class="btn ghost danger" data-del>${icon('trash')}</button><button class="btn" data-run>${icon('play')} Jetzt erstellen</button>`}<span class="grow"></span><button class="btn" data-close>Abbrechen</button><button class="btn primary" data-ok>Speichern</button>`,
			{ wide: true });
		const hint = () => {
			$('#modehint', el).textContent = { send: 'Rechnung wird ausgestellt und mit PDF an den Kunden gemailt (Kopie an dich).', issue: 'Rechnung wird ausgestellt – versenden machst du selbst.', draft: 'Es entsteht ein Entwurf, den du prüfst, ergänzt und dann ausstellst.' }[rec.mode];
		};
		hint();
		$$('#mode button', el).forEach((b) => (b.onclick = () => { rec.mode = b.dataset.m; $$('#mode button', el).forEach((x) => x.classList.toggle('on', x === b)); hint(); }));
		const drawTotals = () => { const t = itemsTotal(rec.items); $('#rtotals', el).innerHTML = `<div class="grand"><span>pro Rechnung</span><span class="num">${money(t)}</span></div><div><span>pro Jahr</span><span class="num">${money(t * 12 / +rec.interval_months)}</span></div>`; };
		itemsEditor($('#ritems', el), rec.items, drawTotals, { placeholders: true });
		drawTotals();
		$$('[name]', el).forEach((i) => { if (!i.closest('#ritems')) i.addEventListener('input', () => { rec[i.name] = i.type === 'checkbox' ? (i.checked ? 1 : 0) : i.value; if (i.name === 'interval_months') drawTotals(); }); });
		autocomplete($('#rcust', el), (qq) => customerSource(qq).filter((x) => !x.value._new), (c) => { rec.customer_id = +c.id; $('#rcust', el).value = c.name; $('[name=email]', el).placeholder = c.email || 'kunde@example.com'; }, { always: true });
		const save = async () => {
			const saved = await api('recurring_save', { ...rec, id: rec.id || 0, items: rec.items.map((it) => ({ ...it, qty: num(it.qty), price: num(it.price), discount: num(it.discount) })) });
			await refresh();
			return saved;
		};
		$('[data-ok]', el).onclick = async () => { try { await save(); closeTop(); toast('Dauerrechnung gespeichert'); after?.(); } catch (e) { fail(e); } };
		$('[data-del]', el)?.addEventListener('click', async () => {
			if (!await confirmDialog('Dauerrechnung löschen?', 'Bereits erstellte Rechnungen bleiben erhalten.', 'Löschen', { danger: true })) return;
			try { await api('recurring_delete', { id: rec.id }); await refresh(); closeTop(); toast('Gelöscht'); after?.(); } catch (e) { fail(e); }
		});
		$('[data-run]', el)?.addEventListener('click', async () => {
			const res = await confirmDialog('Jetzt eine Rechnung erstellen?', `Für den Zeitraum ab ${date(rec.next_date)}. Danach springt das nächste Datum weiter.`, 'Erstellen', {
				extra: `<div class="field" style="margin-top:14px"><span>Wie?</span><select name="mode">${Object.entries(MODE_LABEL).map(([k, l]) => `<option value="${k}" ${k === (rec.mode === 'send' ? 'draft' : rec.mode) ? 'selected' : ''}>${l}</option>`).join('')}</select></div>`,
			});
			if (!res) return;
			try {
				await save();
				const out = await api('recurring_run', { id: rec.id, mode: res.mode });
				await refresh(); closeTop();
				toast(out.note || (out.invoice.number ? 'Rechnung ' + out.invoice.number + ' erstellt' : 'Entwurf erstellt'), out.note ? { error: true } : {});
				go('#/rechnung/' + out.invoice.id);
			} catch (e) { fail(e); }
		});
	}

	/* ================================================================ Ausgaben */

	async function viewExpenses() {
		const main = $('#main');
		let list = await api('expenses');
		const years = () => [...new Set([S.today.slice(0, 4), ...list.map((e) => e.date.slice(0, 4))])].sort().reverse();
		let year = S.today.slice(0, 4);
		main.innerHTML = `<div class="page">
			${pageHead('Ausgaben', { sub: 'Belege und Kosten, für die Einnahmen-Ausgaben-Rechnung' }, `<button class="btn primary" data-new>${icon('plus')} Ausgabe erfassen</button>`)}
			<div class="toolbar"><select id="year" style="width:auto"></select><div class="grow"></div><span class="muted" id="sum"></span></div>
			<div class="card"><div class="table-wrap" id="list"></div></div></div>`;
		const draw = () => {
			$('#year').innerHTML = `<option value="">Alle Jahre</option>` + years().map((y) => `<option ${y === year ? 'selected' : ''}>${y}</option>`).join('');
			const rows = list.filter((e) => !year || e.date.startsWith(year));
			const sum = rows.reduce((a, e) => a + +e.amount, 0);
			const rev = S.invoices.filter((i) => i.status !== 'draft' && (!year || i.invoice_date.startsWith(year))).reduce((a, i) => a + i.gross, 0);
			$('#sum').innerHTML = `Ausgaben <b class="num" style="color:var(--ink)">${money(sum)}</b> · Einnahmen ${money(rev)} · Überschuss <b class="num" style="color:var(--ink)">${money(rev - sum)}</b>`;
			$('#list').innerHTML = rows.length ? `<table class="table resp"><thead><tr><th>Datum</th><th>Lieferant</th><th class="hide-m">Kategorie</th><th class="hide-m">Beleg</th><th class="th-r">Betrag</th></tr></thead><tbody>
				${rows.map((e) => `<tr class="click" data-id="${e.id}"><td class="m-c muted">${date(e.date)}</td><td class="m-b m-wide strong">${esc(e.vendor)}<div class="sub">${esc(e.description)}</div></td><td class="m-hide muted">${esc(e.category)}</td>
					<td class="m-e">${e.file ? `<a class="btn sm" href="api.php?a=expense_file&id=${e.id}" target="_blank" rel="noopener" data-stop>${icon('paperclip')}<span class="hide-m">Beleg</span></a>` : '<span class="faint">—</span>'}</td>
					<td class="m-d td-r num strong">${money(e.amount)}</td></tr>`).join('')}</tbody></table>` : '<div class="empty"><span class="big">[ ]</span>Keine Ausgaben in diesem Zeitraum.</div>';
			$$('#list tr[data-id]').forEach((tr) => (tr.onclick = (ev) => { if (!ev.target.closest('[data-stop]')) expenseDrawer(list.find((e) => +e.id === +tr.dataset.id)); }));
		};
		const expenseDrawer = (e) => {
			const isNew = !e.id;
			const cats = [...new Set(['Hosting & Server', 'Software & Lizenzen', 'Hardware', 'Gebühren', 'Büro', 'Fahrtkosten', 'Weiterbildung', ...list.map((x) => x.category).filter(Boolean)])];
			const el = drawer(isNew ? 'Ausgabe erfassen' : 'Ausgabe', `
				<form class="form-grid" id="ef">
					<label class="field c2"><span>Datum</span><input type="date" name="date" value="${e.date || S.today}"></label>
					<label class="field c4"><span>Lieferant</span><input type="text" name="vendor" value="${esc(e.vendor || '')}" autofocus></label>
					<label class="field c6"><span>Beschreibung</span><input type="text" name="description" value="${esc(e.description || '')}"></label>
					<label class="field c3"><span>Kategorie</span><input type="text" name="category" value="${esc(e.category || '')}" list="ecats"><datalist id="ecats">${cats.map((c) => `<option>${esc(c)}</option>`).join('')}</datalist></label>
					<label class="field c3"><span>Betrag € (brutto)</span><input type="text" inputmode="decimal" name="amount" value="${e.amount ? dec(e.amount) : ''}"></label>
					<label class="field c6"><span>Beleg <small>(PDF oder Foto – am Handy direkt fotografieren)</small></span><input type="file" name="file" accept="application/pdf,image/*" class="input" style="padding-top:7px"></label>
					${e.file ? `<div class="c6"><a class="btn sm" href="api.php?a=expense_file&id=${e.id}" target="_blank" rel="noopener">${icon('paperclip')} Vorhandenen Beleg ansehen</a></div>` : ''}
				</form>`,
				`${isNew ? '' : `<button class="btn ghost danger" data-del>${icon('trash')}</button>`}<span class="grow"></span><button class="btn" data-close>Abbrechen</button><button class="btn primary" data-ok>Speichern</button>`);
			$('[data-ok]', el).onclick = async () => {
				const fd = new FormData($('#ef', el));
				fd.set('amount', String(num(fd.get('amount'))));
				fd.set('id', e.id || 0);
				if (!$('[name=file]', el).files.length) fd.delete('file');
				try { await api('expense_save', fd); list = await api('expenses'); closeTop(); toast('Gespeichert'); draw(); } catch (er) { fail(er); }
			};
			$('[data-del]', el)?.addEventListener('click', async () => {
				if (!await confirmDialog('Ausgabe löschen?', 'Auch der Beleg wird gelöscht.', 'Löschen', { danger: true })) return;
				try { await api('expense_delete', { id: e.id }); list = await api('expenses'); closeTop(); draw(); } catch (er) { fail(er); }
			});
		};
		$('#year').onchange = (e) => { year = e.target.value; draw(); };
		$('[data-new]').onclick = () => expenseDrawer({});
		draw();
	}

	/* ================================================================ Mehr (mobil) */

	function viewMore() {
		$('#main').innerHTML = `<div class="page">${pageHead('Mehr')}
			<div class="card"><div class="card-body"><div class="list">
				${[['#/stunden', 'clock', 'Stunden', 'Zeit erfassen und abrechnen'], ['#/angebote', 'offer', 'Angebote', (S.offers || []).filter((o) => o.state === 'sent').length + ' offen'], ['#/dauerrechnungen', 'repeat', 'Dauerrechnungen', S.recurring.filter((r) => +r.active).length + ' aktiv'], ['#/artikel', 'box', 'Artikel', S.products.filter((p) => !+p.archived).length + ' Leistungen'], ['#/ausgaben', 'wallet', 'Ausgaben', 'Belege erfassen'], ['#/einstellungen/design', 'auto', 'Design', 'Farben und Stil der App'], ['#/einstellungen', 'cog', 'Einstellungen', 'Firma, Texte, E-Mail, Sicherung']]
					.map(([h, i, l, s]) => `<a class="list-item" href="${h}">${icon(i)}<div class="li-main"><div class="li-title">${l}</div><div class="li-sub">${s}</div></div>${icon('right')}</a>`).join('')}
				<a class="list-item" href="#/mehr" data-theme-btn="label">${icon(themeIcon())}<span>Design: ${{ auto: 'Automatisch', light: 'Hell', dark: 'Dunkel' }[store.get('theme', 'auto')]}</span></a>
				<a class="list-item" href="#/mehr" data-logout2>${icon('logout')}<div class="li-main"><div class="li-title">Abmelden</div></div></a>
			</div></div></div></div>`;
		$$('#main [data-theme-btn]').forEach((b) => (b.onclick = (e) => { e.preventDefault(); cycleTheme(); }));
		$('[data-logout2]').onclick = (e) => { e.preventDefault(); logout(); };
	}

	/* ================================================================ Einstellungen */

	async function viewSettings(tab) {
		const main = $('#main');
		const s = S.settings;
		const tabs = [['firma', 'Firma'], ['design', 'Design'], ['bank', 'Bank & Zahlung'], ['texte', 'Rechnungstexte'], ['mail', 'E-Mail & Automatik'], ['nummern', 'Nummern & Steuer'], ['sicherheit', 'Sicherheit'], ['protokoll', 'E-Mail-Protokoll'], ['daten', 'Daten'], ['update', 'Update']];
		const f = (k, label, opts = {}) => `<label class="field ${opts.c || 'c3'}"><span>${label}${opts.small ? ` <small>${opts.small}</small>` : ''}</span>${opts.area ? `<textarea name="${k}" rows="${opts.rows || 3}">${esc(s[k])}</textarea>` : `<input type="${opts.type || 'text'}" name="${k}" value="${esc(s[k])}" ${opts.attr || ''}>`}</label>`;
		const base = new URL('.', location.href).href;
		const body = {
			firma: `<div class="logo-edit">
					<div class="logo-preview" id="logoprev">${LOGO()}</div>
					<div class="btns"><label class="btn">${icon('download')} Logo hochladen<input type="file" id="logofile" accept=".svg,.png,.jpg,.jpeg,image/svg+xml,image/png,image/jpeg" hidden></label>${($('#logo-svg')?.textContent || '').trim() ? `<button type="button" class="btn ghost danger" data-logodel>${icon('trash')} Entfernen</button>` : ''}</div>
					<p class="muted" style="font-size:12.5px">Erscheint links oben auf jeder Rechnung und in der Seitenleiste. <b>SVG</b> (gestochen scharf, Texte bitte in Pfade umwandeln), <b>PNG</b> (auch mit transparentem Hintergrund) oder <b>JPG</b> – für den Druck mindestens 600 Pixel breit. Ohne Logo steht dort der Firmenname.</p>
				</div>
				<div class="form-grid">${f('company', 'Name / Firma')}${f('tagline', 'Zusatz', { small: 'unter dem Namen' })}${f('owner', 'Inhaber', { small: 'für Grußformel' })}${f('street', 'Straße')}${f('zip', 'PLZ', { c: 'c1' })}${f('city', 'Ort', { c: 'c2' })}${f('phone', 'Telefon')}${f('email', 'E-Mail', { type: 'email' })}${f('web', 'Website')}${f('vat_id', 'UID-Nummer', { small: 'falls vorhanden' })}${f('tax_number', 'Steuernummer')}</div>`,
			bank: `<div class="form-grid">${f('bank', 'Bank', { small: 'optional' })}${f('bank_owner', 'Kontoinhaber')}${f('iban', 'IBAN', { c: 'c4' })}${f('bic', 'BIC', { c: 'c2' })}${f('payment_days', 'Zahlungsziel Standard (Tage)', { type: 'number', attr: 'min="0"' })}
				<label class="field c3"><span>Zahlschein auf der Rechnung</span><select name="pay_box">${[['qr', 'Mit QR-Code für Banking-Apps'], ['plain', 'Nur Bankdaten'], ['off', 'Kein Zahlschein']].map(([k, l]) => `<option value="${k}" ${s.pay_box === k ? 'selected' : ''}>${l}</option>`).join('')}</select></label></div>
				<div class="hint" style="margin-top:16px">Der QR-Code ist ein EPC-„GiroCode“: Kunden scannen ihn mit der Banking-App, Empfänger, IBAN, Betrag und Rechnungsnummer sind dann schon ausgefüllt.</div>`,
			texte: `<div class="form-grid">${f('greeting', 'Standard-Anrede', { c: 'c6', small: 'wenn keine Person hinterlegt ist' })}${f('intro', 'Einleitung', { c: 'c6', area: true, rows: 2 })}${f('outro', 'Schlusstext', { c: 'c6', area: true, rows: 2 })}${f('footer_note', 'Hinweis auf jeder Rechnung', { c: 'c6', area: true, rows: 2 })}
				<div class="section-title c6">E-Mail-Vorlagen</div>
				${f('mail_subject', 'Betreff Rechnung', { c: 'c6' })}${f('mail_body', 'Text Rechnung', { c: 'c6', area: true, rows: 9 })}${f('remind_subject', 'Betreff Zahlungserinnerung', { c: 'c6' })}${f('remind_body', 'Text Zahlungserinnerung', { c: 'c6', area: true, rows: 8 })}
				<div class="section-title c6">Angebote</div>
				${f('offer_intro', 'Einleitung Angebot', { c: 'c6', area: true, rows: 2 })}${f('offer_outro', 'Schlusstext Angebot', { c: 'c6', area: true, rows: 2 })}${f('offer_subject', 'Betreff Angebot', { c: 'c6' })}${f('offer_body', 'Text Angebot', { c: 'c6', area: true, rows: 8 })}
				<div class="c6 hint">Platzhalter: <code>{ANREDE}</code> <code>{NUMMER}</code> <code>{DATUM}</code> <code>{BETRAG}</code> <code>{OFFEN}</code> <code>{FAELLIG}</code> <code>{ZAHLUNG}</code> <code>{ZEITRAUM}</code> <code>{KUNDE}</code> <code>{FIRMA}</code> <code>{INHABER}</code> <code>{GUELTIG}</code> (Angebot)</div></div>`,
			mail: `${S.mail.configured ? `<div class="note-ok">${icon('check')}<span>E-Mail-Versand eingerichtet: <b>${esc(S.mail.from)}</b> über ${esc(S.mail.host)}${S.mail.reply_to ? ` · Antworten an ${esc(S.mail.reply_to)}` : ''}${S.mail.bcc ? ` · Kopie an ${esc(S.mail.bcc)}` : ''}</span></div>`
				: `<div class="note-warn">${icon('alert')}<span>Noch kein E-Mail-Zugang eingerichtet – Rechnungen können noch nicht verschickt werden.</span></div>`}
				${S.mail.source === 'config' ? `<div class="hint" style="margin-top:16px">Der Zugang ist in der <code>config.php</code> festgelegt und hat dort Vorrang. Zum Ändern die Datei bearbeiten oder den SMTP-Eintrag dort leeren.</div>
					<div class="btns" style="margin-top:14px"><input type="email" id="testto" value="${esc(s.email)}" style="max-width:300px"><button class="btn" data-test>${icon('send')} Testmail senden</button></div>` : `
				<h3 style="margin:22px 0 4px">E-Mail-Zugang (SMTP)</h3>
				<p class="muted" style="font-size:13.5px;margin-bottom:12px">Am besten ein eigenes Postfach deiner Domain, z. B. <b>rechnung@deine-domain.at</b> – die Daten stehen beim Webhoster. Schnellauswahl:</p>
				<div class="chips" id="smtppre" style="margin-bottom:16px">${SMTP_PRESETS.map((p, i) => `<button type="button" class="chip" data-pre="${i}">${esc(p.name)}</button>`).join('')}</div>
				<div class="form-grid" id="smtpform">
					${f('smtp_host', 'SMTP-Server', { c: 'c3', attr: 'placeholder="z. B. mail.deine-domain.at" autocomplete="off"' })}
					${f('smtp_port', 'Port', { c: 'c1', type: 'number', attr: 'min="1"' })}
					<label class="field c2"><span>Verschlüsselung</span><select name="smtp_secure"><option value="ssl" ${s.smtp_secure !== 'tls' ? 'selected' : ''}>SSL/TLS (meist Port 465)</option><option value="tls" ${s.smtp_secure === 'tls' ? 'selected' : ''}>STARTTLS (meist Port 587)</option></select></label>
					${f('smtp_user', 'Benutzername', { c: 'c3', attr: 'autocomplete="off" placeholder="meist die E-Mail-Adresse"' })}
					<label class="field c3"><span>Passwort <small>${S.mail.has_pass ? '(gespeichert – leer lassen, um es zu behalten)' : ''}</small></span><input type="password" name="smtp_pass" autocomplete="new-password" placeholder="${S.mail.has_pass ? '••••••••' : ''}"></label>
					${f('smtp_from', 'Absenderadresse', { c: 'c3', type: 'email', attr: 'placeholder="leer = Benutzername"' })}
					${f('smtp_from_name', 'Absendername', { c: 'c3', attr: `placeholder="${esc(s.company)}"` })}
					${f('smtp_reply_to', 'Antwort an', { c: 'c3', type: 'email', attr: `placeholder="${esc(s.email || 'leer = Absenderadresse')}"`, small: 'wohin Kunden antworten' })}
					${f('smtp_bcc', 'Kopie jeder Mail an (BCC)', { c: 'c3', small: 'mehrere mit Komma' })}
					<div class="c6 hint" id="smtphint">Das Passwort wird verschlüsselt gespeichert. Der Schlüssel liegt getrennt von der Datenbank, eine Datenbank-Sicherung enthält es also nie im Klartext. Verbindungen sind immer verschlüsselt, das Zertifikat des Servers wird geprüft.</div>
				</div>
				<div class="btns" style="margin-top:14px;justify-content:space-between">
					<div class="btns" style="flex-wrap:nowrap;flex:1;min-width:260px;max-width:460px"><input type="email" id="testto" value="${esc(s.email)}" aria-label="Testmail an"><button type="button" class="btn" data-test>${icon('send')} Testmail</button></div>
					<div class="btns">${S.mail.has_pass ? `<button type="button" class="btn ghost danger" data-smtpclear>Passwort löschen</button>` : ''}<button type="button" class="btn primary" data-smtpsave>Speichern</button></div>
				</div>`}
				<h3 style="margin:26px 0 8px">Automatik für Dauerrechnungen (Cronjob)</h3>
				<p class="muted" style="margin-bottom:10px">Beim Webhoster einen täglichen Cronjob anlegen (z. B. 7:00 Uhr). Er erstellt fällige Dauerrechnungen und verschickt sie.</p>
				<div class="hint" style="display:flex;flex-direction:column;gap:8px"><div>Als Befehl: <code>php ${esc('/pfad/zu/rechnungen/cron.php')}</code></div><div>oder als URL: <code id="cronurl">${esc(base + S.cron_url)}</code> <button class="btn sm" data-copy>${icon('copy')} Kopieren</button></div>
					<div>Zuletzt gelaufen: <b>${S.settings.cron_last ? date(S.settings.cron_last) + ' ' + S.settings.cron_last.slice(11, 16) : 'noch nie'}</b></div></div>`,
			nummern: `<div class="form-grid">${f('next_number', 'Nächste Rechnungsnummer', { small: 'mindestens', attr: 'inputmode="numeric"' })}${f('next_customer', 'Nächste Kundennummer', { attr: 'inputmode="numeric"' })}${f('next_sku', 'Nächste Artikelnummer', { attr: 'inputmode="numeric"' })}${f('next_offer', 'Nächste Angebotsnummer', { small: 'ergibt A-…', attr: 'inputmode="numeric"' })}${f('offer_days', 'Angebote gültig (Tage)', { type: 'number', attr: 'min="1"' })}
				<div class="section-title c6">Stunden</div>
				${f('hour_rate', 'Stundensatz Standard (€)', { small: 'pro Kunde überschreibbar', attr: 'inputmode="decimal"' })}${f('hour_name', 'Bezeichnung auf der Rechnung', { small: 'z. B. Arbeitsstunden' })}
				<div class="section-title c6">Umsatzsteuer</div>
				<label class="switch c6"><input type="checkbox" name="small_business" ${s.small_business === '1' ? 'checked' : ''}> Kleinunternehmer (keine Umsatzsteuer auf Rechnungen)</label>
				${f('small_business_text', 'Hinweis auf der Rechnung', { c: 'c6' })}${f('revenue_limit', 'Umsatzgrenze (€)', { small: 'seit 2025: 55.000 € brutto' })}${f('default_tax', 'USt.-Satz Standard (%)', { small: 'falls nicht Kleinunternehmer' })}</div>
				<div class="hint" style="margin-top:16px">Nummern werden fortlaufend vergeben – erst beim Ausstellen, Entwürfe bekommen keine Nummer. Ist die nächste Nummer kleiner als die höchste vergebene, wird automatisch weitergezählt.</div>`,
			sicherheit: `<div class="form-grid" style="max-width:520px"><label class="field c6"><span>Aktuelles Passwort</span><input type="password" id="pw0" autocomplete="current-password"></label><label class="field c6"><span>Neues Passwort <small>(mind. 8 Zeichen)</small></span><input type="password" id="pw1" autocomplete="new-password"></label><div class="c6"><button class="btn primary" data-pw>Passwort ändern</button></div></div>
				<div class="hint" style="margin-top:16px">Nach 8 Fehlversuchen wird die Anmeldung für 15 Minuten gesperrt. Ein Passwortwechsel meldet alle anderen Geräte ab.</div>
				<div id="probe" style="margin-top:16px"></div>`,
			design: `<h3 style="margin-bottom:4px">Design der Oberfläche</h3>
				<p class="muted" style="margin-bottom:16px;font-size:13.5px">Wirkt sofort und für alle Geräte. Hell oder dunkel stellst du pro Gerät unten ein.</p>
				<div class="themes">${UI_THEMES.map((t) => `<button type="button" class="theme-card ${(S.settings.ui_theme || 'schlicht') === t.id ? 'on' : ''}" data-ui-pick="${t.id}" aria-pressed="${(S.settings.ui_theme || 'schlicht') === t.id}">
					${miniPreview(t)}<span class="tc-name"><i style="background:${t.color}"></i>${t.name}</span><span class="tc-desc">${t.desc}</span></button>`).join('')}</div>
				<div class="section-title">Weitere Einstellungen</div>
				<div style="display:flex;flex-direction:column;gap:14px">
					<label class="switch"><input type="checkbox" id="accentpdf" ${S.settings.ui_accent_pdf === '1' ? 'checked' : ''}> Akzentfarbe auch auf Rechnungen und Angeboten (Kopfzeile, Titel)</label>
					<div class="field" style="max-width:420px"><span>Hell oder dunkel (dieses Gerät)</span><div class="seg" id="lightdark">${[['auto', 'Automatisch'], ['light', 'Hell'], ['dark', 'Dunkel']].map(([k, l]) => `<button type="button" data-ld="${k}" class="${store.get('theme', 'auto') === k ? 'on' : ''}">${l}</button>`).join('')}</div></div>
				</div>`,
			protokoll: `<div class="toolbar" style="margin-bottom:12px"><div class="chips"><button type="button" class="chip on" data-lf="all">Alle</button><button type="button" class="chip" data-lf="errors">Nur Fehler</button></div><div class="grow"></div>
					<label class="search" style="min-width:220px"><span class="sr">Suchen</span>${icon('search')}<input type="search" id="lq" placeholder="Empfänger, Betreff, Nummer …"></label></div>
				<div id="logstats" class="muted" style="font-size:13px;margin-bottom:10px"></div><div id="loglist"></div>`,
			update: `<div id="upd"><div class="muted">Suche nach Updates …</div></div>`,
			daten: `<div id="storage" style="margin-bottom:16px"></div><div class="grid g2">
				<div class="card card-pad"><h3>Sicherung</h3><p class="muted" style="margin:6px 0 14px">Die komplette Datenbank (Kunden, Artikel, Rechnungen, Einstellungen) als eine Datei. Regelmäßig herunterladen!</p><a class="btn primary" href="api.php?a=backup">${icon('download')} Datenbank sichern</a></div>
				<div class="card card-pad"><h3>Export</h3><p class="muted" style="margin:6px 0 14px">Rechnungsliste als CSV für die Steuerberatung oder alle Rechnungen als PDF in einer ZIP-Datei.</p><button class="btn" data-export>${icon('download')} Exportieren …</button></div>
			</div>
			<div class="hint" style="margin-top:16px">Original-PDFs der importierten Rechnungen und Belege liegen im Datenordner unter <code>files/</code>. Rechnungen sind in Österreich 7 Jahre aufzubewahren.</div>`,
		}[tab];
		main.innerHTML = `<div class="page" style="max-width:1080px">${pageHead('Einstellungen')}
			<div class="tabs-wrap">
				<button type="button" class="tabs-arrow left" data-tabs="-1" aria-label="Reiter nach links">${icon('left')}</button>
				<nav class="tabs" id="stabs">${tabs.map(([k, l]) => `<a href="#/einstellungen/${k}" class="${k === tab ? 'on' : ''}">${l}</a>`).join('')}</nav>
				<button type="button" class="tabs-arrow right" data-tabs="1" aria-label="Reiter nach rechts">${icon('right')}</button>
			</div>
			<form id="sf" class="card card-pad" autocomplete="off">${body}</form>
			${['firma', 'bank', 'texte', 'nummern'].includes(tab) ? `<div class="btns" style="margin-top:14px;justify-content:flex-end"><button class="btn primary" data-save>Speichern</button></div>` : ''}</div>`;
		if (tab === 'firma') plzAssist($('[name=zip]', main), $('[name=city]', main), null);
		$('#logofile', main)?.addEventListener('change', async (e) => {
			const fd = new FormData();
			fd.append('logo', e.target.files[0]);
			try { const r = await api('logo_upload', fd); $('#logo-svg').textContent = r.logo; toast('Logo gespeichert'); renderShell(); route(); } catch (er) { fail(er); }
		});
		$('[data-logodel]', main)?.addEventListener('click', async () => {
			if (!await confirmDialog('Logo entfernen?', 'Auf Rechnungen steht dann der Firmenname.', 'Entfernen', { danger: true })) return;
			try { await api('logo_delete', {}); $('#logo-svg').textContent = ''; renderShell(); route(); } catch (er) { fail(er); }
		});
		$('[data-save]', main)?.addEventListener('click', async () => {
			const fd = Object.fromEntries(new FormData($('#sf')));
			if (tab === 'nummern') fd.small_business = $('[name=small_business]').checked ? '1' : '0';
			try { await api('settings_save', fd); await refresh(); toast('Einstellungen gespeichert'); } catch (e) { fail(e); }
		});
		$('#sf').onsubmit = (e) => e.preventDefault();
		// Reitermenü: Pfeile links/rechts, wenn nicht alle Reiter Platz haben
		const tabsEl = $('#stabs');
		const arrows = () => {
			const max = tabsEl.scrollWidth - tabsEl.clientWidth;
			$('.tabs-arrow.left', main).classList.toggle('show', tabsEl.scrollLeft > 2);
			$('.tabs-arrow.right', main).classList.toggle('show', tabsEl.scrollLeft < max - 2);
		};
		$$('[data-tabs]', main).forEach((b) => (b.onclick = () => tabsEl.scrollBy({ left: +b.dataset.tabs * tabsEl.clientWidth * 0.6, behavior: 'smooth' })));
		tabsEl.addEventListener('scroll', arrows, { passive: true });
		const onResize = () => { if (!document.body.contains(tabsEl)) return window.removeEventListener('resize', onResize); arrows(); };
		window.addEventListener('resize', onResize);
		$('.tabs a.on', tabsEl)?.scrollIntoView({ block: 'nearest', inline: 'center' });
		requestAnimationFrame(arrows);
		const smtpData = () => ($('#smtpform', main) ? Object.fromEntries($$('#smtpform [name]', main).map((i) => [i.name, i.value])) : {});
		$('[data-test]', main)?.addEventListener('click', async (e) => {
			const b = e.currentTarget; b.disabled = true;
			try { await api('mail_test', { to: $('#testto').value, ...smtpData() }); toast('Testmail gesendet – bitte Posteingang prüfen'); } catch (er) { fail(er); }
			b.disabled = false;
		});
		$('[data-smtpsave]', main)?.addEventListener('click', async () => {
			try { await api('smtp_save', smtpData()); await refresh(); toast('E-Mail-Zugang gespeichert'); viewSettings('mail'); } catch (e) { fail(e); }
		});
		$('[data-smtpclear]', main)?.addEventListener('click', async () => {
			if (!await confirmDialog('Passwort löschen?', 'Danach können keine E-Mails mehr verschickt werden, bis ein neues eingetragen ist.', 'Löschen', { danger: true })) return;
			try { await api('smtp_save', { ...smtpData(), smtp_clear_pass: 1 }); await refresh(); viewSettings('mail'); } catch (e) { fail(e); }
		});
		$$('[data-pre]', main).forEach((b) => (b.onclick = () => {
			const p = SMTP_PRESETS[+b.dataset.pre];
			$$('[data-pre]', main).forEach((x) => x.classList.toggle('on', x === b));
			if (p.host !== null) $('[name=smtp_host]', main).value = p.host;
			$('[name=smtp_port]', main).value = p.port;
			$('[name=smtp_secure]', main).value = p.secure;
			$('#smtphint', main).innerHTML = esc(p.hint);
			$('[name=smtp_host]', main).focus();
		}));
		$('[data-copy]', main)?.addEventListener('click', () => { navigator.clipboard?.writeText($('#cronurl').textContent); toast('Kopiert'); });
		$('[data-export]', main)?.addEventListener('click', () => exportDialog(''));
		$('[data-pw]', main)?.addEventListener('click', async () => {
			try { await api('password', { old: $('#pw0').value, new: $('#pw1').value }); toast('Passwort geändert'); $('#pw0').value = $('#pw1').value = ''; } catch (e) { fail(e); }
		});
		if (tab === 'update') updatePanel(false);
		if (tab === 'protokoll') mailLogPanel();
		if (tab === 'daten') storagePanel();
		if (tab === 'design') {
			$$('[data-ui-pick]', main).forEach((b) => (b.onclick = async () => {
				applyUi(b.dataset.uiPick);
				$$('[data-ui-pick]', main).forEach((x) => { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', x === b); });
				try { await api('settings_save', { ui_theme: b.dataset.uiPick }); await refresh(); toast('Design „' + UI_THEMES.find((t) => t.id === b.dataset.uiPick).name + '“ gespeichert'); } catch (e) { fail(e); }
			}));
			$('#accentpdf', main).onchange = async (e) => {
				try { await api('settings_save', { ui_accent_pdf: e.target.checked ? '1' : '0' }); await refresh(); toast(e.target.checked ? 'Rechnungen bekommen die Akzentfarbe' : 'Rechnungen wieder in Schwarz'); } catch (er) { fail(er); }
			};
			$$('[data-ld]', main).forEach((b) => (b.onclick = () => {
				store.set('theme', b.dataset.ld); applyTheme();
				$$('[data-ld]', main).forEach((x) => x.classList.toggle('on', x === b));
				$$('[data-theme-btn]').forEach((x) => (x.innerHTML = icon(themeIcon()) + (x.dataset.themeBtn === 'label' ? `<span class="lbl">Design: ${{ auto: 'Automatisch', light: 'Hell', dark: 'Dunkel' }[b.dataset.ld]}</span>` : '')));
			}));
		}
		if (tab === 'sicherheit') {
			// Prüfen, ob der Datenordner von außen erreichbar ist (darf er nicht sein)
			fetch('data/probe.txt', { cache: 'no-store' }).then((r) => r.ok ? r.text() : '').catch(() => '').then((t) => {
				$('#probe').innerHTML = t.includes('geschützt')
					? `<div class="note-bad">${icon('alert')}<span>Der Datenordner <code>data/</code> ist öffentlich erreichbar! Bitte <code>data_dir</code> in der <code>config.php</code> auf einen Ordner außerhalb des Webverzeichnisses setzen oder .htaccess aktivieren.</span></div>`
					: `<div class="note-ok">${icon('check')}<span>Datenordner ist von außen nicht erreichbar.</span></div>`;
			});
		}
	}

	/* ================================================================ Speicherplatz */

	function fmtBytes(b) {
		b = +b || 0;
		if (b < 1024) return b + ' B';
		const u = ['KB', 'MB', 'GB', 'TB'];
		let i = -1;
		do { b /= 1024; i++; } while (b >= 1024 && i < u.length - 1);
		return (b >= 100 || i === 0 ? Math.round(b) : b.toFixed(1).replace('.', ',')) + ' ' + u[i];
	}

	const TABLE_LABEL = {
		invoices: ['Rechnungen & Angebote', 'file'], invoice_items: ['Positionen', 'file'], customers: ['Kunden', 'users'], products: ['Artikel', 'box'],
		recurring: ['Dauerrechnungen', 'repeat'], payments: ['Zahlungen', 'wallet'], time_entries: ['Stunden', 'clock'], expenses: ['Ausgaben', 'wallet'],
		mail_log: ['E-Mail-Protokoll', 'mail'], activity: ['Verlauf', 'chart'], logins: ['Anmeldeversuche', 'key'], settings: ['Einstellungen', 'cog'],
	};

	async function storagePanel() {
		const box = $('#storage');
		if (!box) return;
		box.innerHTML = `<div class="card card-pad"><h3>Speicherplatz</h3><p class="muted" style="margin:8px 0 0">Wird berechnet …</p></div>`;
		let s;
		try { s = await api('storage'); } catch (e) { box.innerHTML = ''; return fail(e); }
		const parts = [
			['Datenbank', s.db.bytes, 'st-c1', `${s.tables.reduce((a, t) => a + t.rows, 0).toLocaleString('de-AT')} Einträge`],
			['Original-PDFs & Belege', s.files.bytes, 'st-c2', `${s.files.count} Dateien`],
			['Sicherungen vor Updates', s.updates.bytes, 'st-c3', `${s.updates.count} Dateien`],
			['Sonstiges', s.other, 'st-c4', 'Logo, Schlüssel, Temporäres'],
		];
		const total = Math.max(1, s.total);
		const known = s.tables.some((t) => t.bytes !== null);
		const rows = s.tables.filter((t) => t.rows || t.bytes > 4096).sort((a, b) => (b.bytes ?? 0) - (a.bytes ?? 0) || b.rows - a.rows);
		const maxB = Math.max(1, ...rows.map((t) => (known ? t.bytes : t.rows) || 0));
		const reclaim = s.db.free + s.db.wal;
		box.innerHTML = `<div class="card card-pad storage">
			<div class="st-head"><div><h3>Speicherplatz</h3><p class="muted" style="margin:4px 0 0">Alles im Datenordner – Datenbank, hochgeladene Dateien und Sicherungen</p></div>
				<button type="button" class="btn sm ghost" data-st-reload>${icon('repeat')} Neu berechnen</button></div>
			<div class="st-total"><b class="num">${fmtBytes(s.total)}</b><span class="muted">belegt${s.disk ? ` · ${fmtBytes(s.disk)} frei auf dem Server` : ''}</span></div>
			<div class="st-bar" role="img" aria-label="Aufteilung des Speicherplatzes">${parts.filter((p) => p[1]).map((p) => `<i class="${p[2]}" style="width:${Math.max(0.6, (p[1] / total) * 100)}%" title="${p[0]}: ${fmtBytes(p[1])}"></i>`).join('')}</div>
			<div class="st-legend">${parts.map((p) => `<div><span class="st-dot ${p[2]}"></span><div><b>${p[0]}</b><div class="muted">${fmtBytes(p[1])} · ${p[3]}</div></div></div>`).join('')}</div>
			<h4 class="st-sub">Inhalt der Datenbank</h4>
			<div class="st-tables">${rows.map((t) => {
				const [label, ic] = TABLE_LABEL[t.name] || [t.name, 'box'];
				const v = (known ? t.bytes : t.rows) || 0;
				return `<div class="st-row">${icon(ic)}<span class="st-name">${esc(label)}</span><span class="st-meter"><i style="width:${Math.max(1, (v / maxB) * 100)}%"></i></span>
					<span class="num muted st-n">${t.rows.toLocaleString('de-AT')}</span>${known ? `<b class="num st-b">${fmtBytes(t.bytes)}</b>` : ''}</div>`;
			}).join('')}</div>
			${known ? '' : '<p class="muted" style="font-size:12.5px;margin:8px 0 0">Die Größe je Bereich kann dieser Server nicht ermitteln – angezeigt wird die Anzahl der Einträge.</p>'}
			<div class="st-foot">
				<span class="st-health ${s.db.ok ? 'ok' : 'bad'}">${icon(s.db.ok ? 'check' : 'alert')} ${s.db.ok ? 'Datenbank geprüft – in Ordnung' : 'Prüfung meldet Fehler – bitte sofort eine Sicherung herunterladen'}</span>
				<span class="muted">SQLite ${esc(s.db.sqlite)}${reclaim > 0 ? ` · ${fmtBytes(reclaim)} ungenutzt` : ''}</span>
				<span class="grow"></span>
				<button type="button" class="btn sm" data-st-opt ${reclaim > 0 ? '' : 'disabled title="Die Datenbank ist bereits kompakt"'}>${icon('check')} Datenbank optimieren</button>
			</div>
		</div>`;
		$('[data-st-reload]', box).onclick = () => storagePanel();
		$('[data-st-opt]', box).onclick = async (e) => {
			e.currentTarget.disabled = true;
			try {
				const r = await api('db_optimize', {});
				toast(r.saved > 0 ? `Datenbank optimiert – ${fmtBytes(r.saved)} frei geworden` : 'Datenbank optimiert – war bereits kompakt');
				storagePanel();
			} catch (er) { fail(er); e.currentTarget.disabled = false; }
		};
	}

	/* ================================================================ E-Mail-Protokoll */

	function mailLogPanel() {
		let errors = false, q = '', offset = 0, rows = [];
		const KIND = { invoice: 'Rechnung', reminder: 'Erinnerung', offer: 'Angebot', test: 'Testmail' };
		const load = async (more) => {
			offset = more ? offset + 100 : 0;
			let r;
			try { r = await api('mail_log', undefined, { query: { errors: errors ? 1 : '', q, offset } }); } catch (e) { return fail(e); }
			rows = more ? rows.concat(r.rows) : r.rows;
			const st = r.stats || {};
			$('#logstats').innerHTML = st.total ? `${st.total} Mails protokolliert${+st.failed ? ` · <b style="color:var(--bad)">${st.failed} fehlgeschlagen</b>` : ''} · zuletzt ${relTime(st.last)}` : '';
			$('#loglist').innerHTML = rows.length ? `<div class="maillog">${rows.map((m) => `
				<details class="ml-row ${+m.ok ? '' : 'failed'}">
					<summary>
						<span class="ml-status" title="${+m.ok ? 'Zugestellt an den Mailserver' : 'Fehlgeschlagen'}">${icon(+m.ok ? 'check' : 'alert')}</span>
						<span class="ml-when mono">${date(m.created_at.slice(0, 10))} ${m.created_at.slice(11, 16)}</span>
						<span class="ml-main"><b>${esc(m.to_addr)}</b><span class="muted"> · ${esc(m.subject)}</span></span>
						<span class="badge plain">${KIND[m.kind] || esc(m.kind)}${m.number ? ' ' + esc(m.number) : ''}</span>
					</summary>
					<dl class="ml-detail">
						<dt>Zeit</dt><dd>${date(m.created_at.slice(0, 10))} ${esc(m.created_at.slice(11))} · ${esc(m.source || 'von Hand')}</dd>
						<dt>Von</dt><dd>${esc(m.from_addr || '—')}</dd>
						<dt>An</dt><dd>${esc(m.to_addr)}</dd>
						${m.cc ? `<dt>Kopie (CC)</dt><dd>${esc(m.cc)}</dd>` : ''}
						${m.bcc ? `<dt>Blindkopie (BCC)</dt><dd>${esc(m.bcc)}</dd>` : ''}
						${m.reply_to ? `<dt>Antwort an</dt><dd>${esc(m.reply_to)}</dd>` : ''}
						<dt>Betreff</dt><dd>${esc(m.subject)}</dd>
						${m.attachment ? `<dt>Anhang</dt><dd>${esc(m.attachment)}</dd>` : ''}
						${m.number ? `<dt>Beleg</dt><dd>${m.invoice_id ? `<a href="#/${m.kind === 'offer' ? 'angebot' : 'rechnung'}/${m.invoice_id}">${esc(m.number)}</a>` : esc(m.number) + ' <span class="muted">(gelöscht)</span>'}</dd>` : ''}
						${m.bytes ? `<dt>Größe</dt><dd>${fmtBytes(m.bytes)}</dd>` : ''}
						${m.smtp_host ? `<dt>Server</dt><dd class="mono">${esc(m.smtp_host)}</dd>` : ''}
						${m.server_reply ? `<dt>Antwort Server</dt><dd class="mono">${esc(m.server_reply)}</dd>` : ''}
						${m.message_id ? `<dt>Message-ID</dt><dd class="mono" style="word-break:break-all">${esc(m.message_id)}</dd>` : ''}
						${+m.ok ? '' : `<dt>Fehler</dt><dd style="color:var(--bad);font-weight:600">${esc(m.error)}</dd>`}
					</dl>
				</details>`).join('')}</div>${r.more ? `<div style="text-align:center;margin-top:12px"><button type="button" class="btn sm" data-more>Ältere laden</button></div>` : ''}`
				: `<div class="empty"><span class="big">[ ]</span>${errors || q ? 'Keine passenden Einträge.' : 'Noch keine Mails versendet.'}</div>`;
			$('[data-more]')?.addEventListener('click', () => load(true));
		};
		$$('[data-lf]').forEach((b) => (b.onclick = () => { errors = b.dataset.lf === 'errors'; $$('[data-lf]').forEach((x) => x.classList.toggle('on', x === b)); load(); }));
		$('#lq').oninput = debounce((e) => { q = e.target.value; load(); }, 250);
		load();
	}

	/* ================================================================ Software-Update */

	async function updatePanel(force) {
		const box = $('#upd');
		if (!box) return;
		let u;
		try { u = await api('update_check', undefined, { query: force ? { force: 1 } : {} }); } catch (e) { box.innerHTML = `<div class="note-bad">${icon('alert')}<span>${esc(e.message)}</span></div>`; return; }
		const notes = (u.notes || '').trim();
		box.innerHTML = `
			<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-end;margin-bottom:18px">
				<div><div class="label-s">Installiert</div><div style="font-size:24px;font-weight:750" class="mono">${esc(u.current)}</div></div>
				<div><div class="label-s">Neueste Version</div><div style="font-size:24px;font-weight:750" class="mono">${esc(u.latest || '—')}</div></div>
				<div style="flex:1"></div>
				<button class="btn" data-check>${icon('repeat')} Jetzt prüfen</button>
				${u.newer ? `<button class="btn primary" data-install ${u.writable ? '' : 'disabled'}>${icon('download')} Update installieren</button>` : ''}
			</div>
			${u.error ? `<div class="note-warn">${icon('alert')}<span>${esc(u.error)}</span></div>`
				: u.newer ? `<div class="note-ok" style="margin-bottom:14px">${icon('check')}<span>Version ${esc(u.latest)} ist verfügbar${u.published ? ' (veröffentlicht am ' + date(u.published.slice(0, 10)) + ')' : ''}.</span></div>`
				: `<div class="note-ok">${icon('check')}<span>Du hast die neueste Version.</span></div>`}
			${u.newer && !u.writable ? `<div class="note-bad" style="margin-top:10px">${icon('alert')}<span>Der Programmordner ist für PHP nicht beschreibbar – das Update bitte per FTP einspielen.</span></div>` : ''}
			${u.newer && notes ? `<div class="section-title">Was ist neu</div><div class="hint" style="white-space:pre-wrap;font-size:13.5px;color:var(--ink-2)">${esc(notes)}</div>` : ''}
			<div class="hint" style="margin-top:16px">Updates kommen von <a href="https://github.com/${esc(u.repo)}/releases" target="_blank" rel="noopener"><code>${esc(u.repo || '–')}</code></a>. Vor jedem Update werden Programm und Datenbank gesichert. Deine Daten (<code>data/</code>) und die <code>config.php</code> werden nie überschrieben.</div>
			${u.backups?.length ? `<div class="section-title">Sicherungen vor Updates</div><div class="list">${u.backups.map((b) => `
				<div class="list-item"><div class="li-main"><div class="li-title mono">${esc(b.name)}</div><div class="li-sub">${relTime(b.time)} · ${fmtBytes(b.size)}</div></div><button class="btn sm" data-rollback="${esc(b.name)}">Diesen Stand wiederherstellen</button></div>`).join('')}</div>` : ''}`;
		$('[data-check]', box).onclick = () => { box.innerHTML = '<div class="muted">Suche nach Updates …</div>'; updatePanel(true); };
		$('[data-install]', box)?.addEventListener('click', async (e) => {
			if (!await confirmDialog('Update installieren?', `Version ${esc(u.current)} wird durch ${esc(u.latest)} ersetzt. Vorher werden Programm und Datenbank gesichert.`, 'Installieren')) return;
			e.target.disabled = true; e.target.textContent = 'Wird installiert …';
			try {
				const r = await api('update_install', {});
				toast(`Update auf ${r.to} installiert – lade neu …`);
				setTimeout(() => location.reload(), 1200);
			} catch (er) { fail(er); updatePanel(false); }
		});
		$$('[data-rollback]', box).forEach((b) => (b.onclick = async () => {
			if (!await confirmDialog('Stand wiederherstellen?', `Die Programmdateien aus <b>${esc(b.dataset.rollback)}</b> werden zurückgespielt. Deine aktuellen Daten bleiben unverändert.`, 'Wiederherstellen', { danger: true })) return;
			try { await api('update_rollback', { name: b.dataset.rollback }); toast('Wiederhergestellt – lade neu …'); setTimeout(() => location.reload(), 1200); } catch (er) { fail(er); }
		}));
	}

	/* ================================================================ Befehlspalette (⌘K) */

	function openPalette() {
		if ($('.palette')) return;
		const el = openLayer(`<label class="search">${icon('search')}<input type="text" id="pq" placeholder="Suche Rechnung, Kunde, Artikel oder Aktion …" autocomplete="off" autofocus></label><div class="results" id="pres"></div>`, 'palette');
		const actions = [
			['Neue Rechnung', '#/rechnung/neu', 'plus'], ['Neues Angebot', '#/angebot/neu', 'offer'], ['Stunden erfassen', () => hourDrawer({}, () => route()), 'clock'], ['Stunden – Übersicht', '#/stunden', 'clock'], ['Offene Angebote', '#/angebote?f=sent', 'offer'], ['Neuer Kunde', () => customerDrawer({}, (c) => go('#/kunde/' + c.id)), 'users'], ['Dauerrechnungen', '#/dauerrechnungen', 'repeat'],
			['Offene Rechnungen', '#/rechnungen?f=open', 'file'], ['Ausgabe erfassen', '#/ausgaben', 'wallet'], ['Einstellungen', '#/einstellungen', 'cog'], ['Datenbank sichern', () => (location.href = 'api.php?a=backup'), 'download'],
		];
		let items = [], idx = 0;
		const draw = () => {
			const n = norm($('#pq', el).value.trim());
			const groups = [];
			const acts = actions.filter((a) => !n || norm(a[0]).includes(n)).map((a) => ({ html: `${icon(a[2])}<div class="grow"><div class="t">${a[0]}</div></div>`, run: a[1] }));
			if (n) {
				const inv = S.invoices.filter((i) => norm(i.number + ' ' + i.recipient.name + ' ' + i.item_names).includes(n)).slice(0, 6)
					.map((i) => ({ html: `<span class="mono">${esc(i.number || 'Entwurf')}</span><div class="grow"><div class="t">${withRec(esc(i.recipient.name), i.is_recurring)}</div><div class="s">${date(i.invoice_date)} · ${esc(i.item_names || '')}</div></div>${badge(i.state)}<b class="num">${money(i.gross)}</b>`, run: '#/rechnung/' + i.id }));
				const cus = S.customers.filter((c) => norm(c.name + ' ' + c.person + ' ' + c.number).includes(n)).slice(0, 5)
					.map((c) => ({ html: `${icon('users')}<div class="grow"><div class="t">${esc(c.name)}</div><div class="s">${esc(c.person || '')} · ${esc(c.city || '')}</div></div>`, run: '#/kunde/' + c.id }));
				const pro = S.products.filter((p) => norm(p.sku + ' ' + p.name).includes(n)).slice(0, 4)
					.map((p) => ({ html: `<span class="mono">${esc(p.sku)}</span><div class="grow"><div class="t">${esc(p.name)}</div></div><b class="num">${money(p.price)}</b>`, run: () => { go('#/artikel'); setTimeout(() => productDrawer(p, () => route()), 50); } }));
				const off = (S.offers || []).filter((o) => norm(o.number + ' ' + o.recipient.name + ' ' + o.item_names + ' ' + o.subject).includes(n)).slice(0, 4)
					.map((o) => ({ html: `<span class="mono">${esc(o.number || 'Entwurf')}</span><div class="grow"><div class="t">${esc(o.recipient.name)}</div><div class="s">Angebot · ${date(o.invoice_date)}</div></div>${badge(o.state)}<b class="num">${money(o.gross)}</b>`, run: '#/angebot/' + o.id }));
				if (cus.length) groups.push(['Kunden', cus]);
				if (inv.length) groups.push(['Rechnungen', inv]);
				if (off.length) groups.push(['Angebote', off]);
				if (pro.length) groups.push(['Artikel', pro]);
			}
			if (acts.length) groups.push(['Aktionen', acts]);
			items = groups.flatMap((g) => g[1]);
			idx = Math.min(idx, Math.max(0, items.length - 1));
			let k = 0;
			$('#pres', el).innerHTML = groups.map(([g, list]) => `<div class="group">${g}</div>` + list.map((it) => `<div class="ac-item ${k === idx ? 'on' : ''}" data-k="${k++}">${it.html}</div>`).join('')).join('') || '<div class="empty">Nichts gefunden.</div>';
			$$('#pres .ac-item', el).forEach((d) => (d.onclick = () => run(+d.dataset.k)));
		};
		const run = (k) => { const it = items[k]; if (!it) return; closeTop(); typeof it.run === 'string' ? go(it.run) : it.run(); };
		$('#pq', el).oninput = () => { idx = 0; draw(); };
		$('#pq', el).onkeydown = (e) => {
			if (e.key === 'ArrowDown') { idx = (idx + 1) % items.length; draw(); e.preventDefault(); }
			else if (e.key === 'ArrowUp') { idx = (idx - 1 + items.length) % items.length; draw(); e.preventDefault(); }
			else if (e.key === 'Enter') { run(idx); e.preventDefault(); }
		};
		draw();
	}

	document.addEventListener('keydown', (e) => {
		if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); if (S.settings.company) openPalette(); return; }
		if (e.key === 'Escape' && layers.length) { e.preventDefault(); closeTop(); return; }
		const typing = /INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName) || document.activeElement?.isContentEditable;
		if (typing || e.metaKey || e.ctrlKey || e.altKey || layers.length || !S.settings.company) return;
		if (e.key === 'n') { e.preventDefault(); go('#/rechnung/neu'); }
		if (e.key === '/') { e.preventDefault(); openPalette(); }
	});

	/* ================================================================ Anmeldung & Start */

	async function boot() {
		applyTheme();
		let sess;
		try { sess = await api('session'); } catch (e) { $('#app').innerHTML = `<div class="auth"><div class="auth-card"><h1>Keine Verbindung</h1><p>${esc(e.message)}</p></div></div>`; return; }
		CSRF = sess.csrf;
		if (!sess.logged_in) return renderAuth(sess.has_password);
		try { await refresh(); } catch (e) { return fail(e); }
		applyUi(S.settings.ui_theme);
		renderShell();
		route();
	}

	function renderAuth(hasPassword) {
		$('#app').innerHTML = `<div class="auth"><form class="auth-card" id="af">
			<div class="logo">${LOGO()}</div>
			<h1>${hasPassword ? 'Anmelden' : 'Willkommen'}</h1>
			<p>${hasPassword ? 'Rechnungen · bitte Passwort eingeben.' : 'Zum Einrichten den Code aus der Datei <b>data/SETUP-CODE.txt</b> auf dem Server eingeben (z. B. per FTP öffnen) und ein Passwort festlegen (mindestens 8 Zeichen).'}</p>
			${hasPassword ? '' : '<label class="field" style="margin-bottom:10px"><span class="sr">Einrichtungscode</span><input type="text" id="code" placeholder="Einrichtungscode" autocomplete="off" autocapitalize="characters" spellcheck="false" class="mono" required></label>'}
			<label class="field"><span class="sr">Passwort</span><input type="password" id="pw" autocomplete="${hasPassword ? 'current-password' : 'new-password'}" placeholder="Passwort" autofocus required></label>
			${hasPassword ? '' : '<label class="field" style="margin-top:10px"><span class="sr">Wiederholen</span><input type="password" id="pw2" autocomplete="new-password" placeholder="Passwort wiederholen" required></label>'}
			<button class="btn primary" type="submit">${hasPassword ? 'Anmelden' : 'Passwort festlegen'}</button>
			<p id="aerr" style="color:var(--bad);margin:12px 0 0;font-weight:600"></p>
		</form></div>`;
		($('#code') || $('#pw')).focus();
		$('#af').onsubmit = async (e) => {
			e.preventDefault();
			const pw = $('#pw').value;
			if (!hasPassword && pw !== $('#pw2').value) { $('#aerr').textContent = 'Die Passwörter stimmen nicht überein.'; return; }
			try {
				await api(hasPassword ? 'login' : 'setup', { password: pw, code: $('#code')?.value || '' });
				boot();
			} catch (er) { $('#aerr').textContent = er.message; $('#pw').select(); }
		};
	}

	window.addEventListener('hashchange', () => route());
	window.addEventListener('beforeunload', (e) => { if (current?.dirty) { e.preventDefault(); e.returnValue = ''; } });
	boot();
})();
