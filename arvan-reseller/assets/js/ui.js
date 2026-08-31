(function () {
	'use strict';

	const themeStorageKey = 'arvan-reseller-theme';
	const languageStorageKey = 'arvan-reseller-language';
	const themeMedia = window.matchMedia('(prefers-color-scheme: dark)');
	const translations = {
		'preference.theme': { fa: 'پوسته نمایش', en: 'Theme' },
		'preference.light': { fa: 'روشن', en: 'Light' },
		'preference.dark': { fa: 'تیره', en: 'Dark' },
		'preference.language': { fa: 'زبان نمایش', en: 'Language' },
		'nav.dashboard': { fa: 'داشبورد', en: 'Dashboard' },
		'nav.services': { fa: 'سرویس‌ها', en: 'Services' },
		'nav.createServer': { fa: 'ساخت سرور', en: 'Create server' },
		'nav.wallet': { fa: 'کیف پول', en: 'Wallet' },
		'nav.billing': { fa: 'مصرف و صورتحساب‌ها', en: 'Usage & billing' },
		'nav.orders': { fa: 'سفارش‌ها', en: 'Orders' },
		'nav.notifications': { fa: 'اعلان‌ها', en: 'Notifications' },
		'nav.setup': { fa: 'راه‌اندازی', en: 'Setup' },
		'nav.customers': { fa: 'مشتریان', en: 'Customers' },
		'nav.payments': { fa: 'پرداخت‌ها', en: 'Payments' },
		'nav.resources': { fa: 'سرورهای ابری', en: 'Cloud servers' },
		'nav.settlements': { fa: 'تسویه داخلی', en: 'Internal settlement' },
		'nav.health': { fa: 'سلامت سامانه', en: 'System health' },
		'nav.audit': { fa: 'گزارش ممیزی', en: 'Audit log' },
		'nav.settings': { fa: 'تنظیمات', en: 'Settings' },
		'shell.cloudPanel': { fa: 'پنل ابری', en: 'Cloud panel' },
		'shell.operations': { fa: 'عملیات زیرساخت ابری', en: 'Cloud operations' },
		'shell.logout': { fa: 'خروج از حساب', en: 'Sign out' },
		'action.newService': { fa: 'سرویس جدید', en: 'New service' },
		'action.createServer': { fa: 'ساخت سرور', en: 'Create server' },
		'action.topupWallet': { fa: 'شارژ کیف پول', en: 'Top up wallet' },
		'action.continueConfig': { fa: 'ادامه پیکربندی', en: 'Continue' },
		'action.previousStep': { fa: 'مرحله قبل', en: 'Previous' },
		'action.logout': { fa: 'خروج از حساب', en: 'Sign out' },
		'account.title': { fa: 'حساب مشتری', en: 'Customer account' },
		'account.open': { fa: 'بازکردن حساب مشتری', en: 'Open customer account' }
	};

	function storedTheme() {
		try {
			const value = window.localStorage.getItem(themeStorageKey);
			return ['light', 'dark', 'system'].includes(value) ? value : 'system';
		} catch (error) {
			return 'system';
		}
	}

	function resolvedTheme(preference) {
		return preference === 'system' ? (themeMedia.matches ? 'dark' : 'light') : preference;
	}

	function applyTheme(preference, persist) {
		const mode = ['light', 'dark', 'system'].includes(preference) ? preference : 'system';
		const theme = resolvedTheme(mode);
		document.documentElement.dataset.arTheme = theme;
		document.documentElement.dataset.arThemeMode = mode;
		if (persist) {
			try { window.localStorage.setItem(themeStorageKey, mode); } catch (error) { /* Preference remains active for this page. */ }
		}
		document.querySelectorAll('[data-ar-theme-value]').forEach((button) => {
			const selected = button.dataset.arThemeValue === theme;
			button.classList.toggle('is-selected', selected);
			button.setAttribute('aria-pressed', String(selected));
		});
		document.dispatchEvent(new CustomEvent('arvan:theme-change', { detail: { mode: mode, theme: theme } }));
	}

	function wireThemeControls(root) {
		(root || document).querySelectorAll('[data-ar-theme-value]:not([data-ar-theme-mounted])').forEach((button) => {
			button.dataset.arThemeMounted = '1';
			button.addEventListener('click', () => applyTheme(button.dataset.arThemeValue, true));
		});
		applyTheme(storedTheme(), false);
		wireSegmentedKeyboard(root, '[data-ar-theme-toggle]', '[data-ar-theme-value]');
	}

	function storedLanguage() {
		try {
			return window.localStorage.getItem(languageStorageKey) === 'en' ? 'en' : 'fa';
		} catch (error) {
			return 'fa';
		}
	}

	function t(key, fallback) {
		const entry = translations[key];
		return entry && entry[storedLanguage()] ? entry[storedLanguage()] : (fallback || key);
	}

	function translateDom(root) {
		const scope = root || document;
		const nodes = [];
		if (scope.matches && scope.matches('[data-ar-i18n]')) nodes.push(scope);
		scope.querySelectorAll('[data-ar-i18n]').forEach((node) => nodes.push(node));
		nodes.forEach((node) => { node.textContent = t(node.dataset.arI18n, node.textContent); });
		scope.querySelectorAll('[data-ar-i18n-label]').forEach((node) => {
			node.setAttribute('aria-label', t(node.dataset.arI18nLabel, node.getAttribute('aria-label')));
		});
	}

	function applyLanguage(language, persist) {
		const value = language === 'en' ? 'en' : 'fa';
		if (persist) {
			try { window.localStorage.setItem(languageStorageKey, value); } catch (error) { /* Preference remains active for this page. */ }
		}
		document.documentElement.dataset.arLanguage = value;
		document.querySelectorAll('.arvan-reseller-app').forEach((app) => {
			app.lang = value;
			app.dir = value === 'en' ? 'ltr' : 'rtl';
		});
		document.querySelectorAll('[data-ar-language-value]').forEach((button) => {
			const selected = button.dataset.arLanguageValue === value;
			button.classList.toggle('is-selected', selected);
			button.setAttribute('aria-pressed', String(selected));
		});
		translateDom(document);
		document.dispatchEvent(new CustomEvent('arvan:language-change', { detail: { language: value, direction: value === 'en' ? 'ltr' : 'rtl' } }));
	}

	function wireSegmentedKeyboard(root, groupSelector, buttonSelector) {
		(root || document).querySelectorAll(groupSelector + ':not([data-ar-keyboard-mounted])').forEach((group) => {
			group.dataset.arKeyboardMounted = '1';
			group.addEventListener('keydown', (event) => {
				if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
				const buttons = Array.from(group.querySelectorAll(buttonSelector));
				const current = buttons.indexOf(document.activeElement);
				if (current < 0) return;
				event.preventDefault();
				const offset = event.key === 'ArrowRight' ? 1 : -1;
				const next = buttons[(current + offset + buttons.length) % buttons.length];
				next.focus(); next.click();
			});
		});
	}

	function wireLanguageControls(root) {
		(root || document).querySelectorAll('[data-ar-language-value]:not([data-ar-language-mounted])').forEach((button) => {
			button.dataset.arLanguageMounted = '1';
			button.addEventListener('click', () => applyLanguage(button.dataset.arLanguageValue, true));
		});
		applyLanguage(storedLanguage(), false);
		wireSegmentedKeyboard(root, '[data-ar-language-toggle]', '[data-ar-language-value]');
	}

	applyTheme(storedTheme(), false);
	applyLanguage(storedLanguage(), false);
	const syncSystemTheme = () => {
		if (storedTheme() === 'system') applyTheme('system', false);
	};
	if (typeof themeMedia.addEventListener === 'function') themeMedia.addEventListener('change', syncSystemTheme);
	else if (typeof themeMedia.addListener === 'function') themeMedia.addListener(syncSystemTheme);

	const statusLabels = {
		active: 'فعال', frozen: 'مسدود', closed: 'بسته', pending: 'در انتظار', completed: 'تکمیل‌شده',
		failed: 'ناموفق', cancelled: 'لغوشده', expired: 'منقضی', refunded: 'بازپرداخت‌شده',
		provisioning: 'در حال ساخت', provisioned: 'آماده', suspended: 'تعلیق‌شده', terminated: 'خاتمه‌یافته',
		error: 'خطا', draft: 'پیش‌نویس', issued: 'صادرشده', paid: 'پرداخت‌شده', void: 'باطل',
		processing: 'در حال پردازش', sent: 'ارسال‌شده', healthy: 'سالم', degraded: 'نیازمند توجه',
		running: 'در حال اجرا', never_run: 'اجرا نشده', available: 'در دسترس', creating: 'در حال ساخت',
		credit: 'افزایش موجودی', debit: 'کاهش موجودی', topup: 'شارژ کیف پول', adjustment: 'اصلاح حساب',
		build: 'در حال ساخت', building: 'در حال ساخت', shutoff: 'خاموش', powered_off: 'خاموش', deleted: 'حذف‌شده', unknown: 'نامشخص',
		AVAILABLE: 'در دسترس', ACTIVE: 'فعال', BUILD: 'در حال ساخت', BUILDING: 'در حال ساخت', SHUTOFF: 'خاموش', POWERED_OFF: 'خاموش', TERMINATED: 'خاتمه‌یافته', DELETED: 'حذف‌شده', ERROR: 'خطا', UNKNOWN: 'نامشخص'
	};

	const errorMessages = {
		arvan_reseller_unauthorized: 'نشست شما پایان یافته است. برای ادامه دوباره وارد شوید.',
		arvan_reseller_forbidden: 'دسترسی لازم برای این عملیات را ندارید.',
		arvan_reseller_rate_limited: 'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.',
		arvan_reseller_timeout: 'پاسخ سرویس بیش از حد طول کشید. دوباره تلاش کنید.',
		arvan_reseller_network_failure: 'ارتباط شبکه برقرار نشد. اتصال خود را بررسی کنید.',
		arvan_reseller_backend_unavailable: 'سرویس پشتیبان در دسترس نیست. کمی بعد دوباره تلاش کنید.',
		arvan_reseller_insufficient_balance: 'موجودی کیف پول برای این عملیات کافی نیست.',
		arvan_reseller_invalid_estimate: 'اطلاعات برآورد کامل یا معتبر نیست.',
		arvan_reseller_flavor_not_found: 'پلن انتخاب‌شده دیگر در دسترس نیست. فهرست را تازه کنید.',
		arvan_reseller_payment_not_found: 'پرداخت مورد نظر پیدا نشد.',
		arvan_reseller_resource_not_found: 'سرویس مورد نظر پیدا نشد.',
		arvan_reseller_provisioning_recovery_required: 'سرور ایجاد شده اما ثبت محلی نیازمند بازیابی مدیر است.',
		arvan_reseller_conflicting_key_action: 'تعویض و حذف کلید API را هم‌زمان انتخاب نکنید.'
	};

	const icons = {
		grid: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
		cloud: '<path d="M17.5 19H7a5 5 0 0 1-.8-9.94A7 7 0 0 1 19.5 11a4 4 0 0 1-2 8z"/>',
		users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
		server: '<rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01M17 7h1M17 17h1"/>',
		region: '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>',
		cpu: '<rect x="6" y="6" width="12" height="12" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
		memory: '<rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 10v4M11 10v4M15 10v4M19 10v4M6 17v2M18 17v2"/>',
		storage: '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v7c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7"/>',
		image: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/>',
		globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/>',
		lock: '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/>',
		card: '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
		receipt: '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
		chart: '<path d="M3 3v18h18"/><path d="m7 16 4-5 3 3 5-7"/>',
		wallet: '<path d="M20 7V5a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v10a2 2 0 0 1-2 2H5a3 3 0 0 1-3-3V6"/><path d="M16 13h4"/>',
		shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
		list: '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/>',
		settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.09A1.7 1.7 0 0 0 9 19.36a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.64 15 1.7 1.7 0 0 0 3.09 14H3v-4h.09A1.7 1.7 0 0 0 4.64 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.64h.01A1.7 1.7 0 0 0 10 3.09V3h4v.09A1.7 1.7 0 0 0 15 4.64a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.36 9v.01A1.7 1.7 0 0 0 20.91 10H21v4h-.09A1.7 1.7 0 0 0 19.4 15z"/>',
		menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
		bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M13.7 21h-3.4"/>',
		plus: '<path d="M12 5v14M5 12h14"/>',
		spark: '<path d="m12 3-1.4 4.2a5 5 0 0 1-3.2 3.2L3 12l4.4 1.6a5 5 0 0 1 3.2 3.2L12 21l1.4-4.2a5 5 0 0 1 3.2-3.2L21 12l-4.4-1.6a5 5 0 0 1-3.2-3.2L12 3z"/>',
		arrow: '<path d="M19 12H5M12 19l7-7-7-7"/>',
		eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		copy: '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
		refresh: '<path d="M20 11a8 8 0 1 0 1 5M20 4v7h-7"/>',
		check: '<path d="m5 12 4 4L19 6"/>',
		warning: '<path d="M10.3 3.7 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
		info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
		search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
		close: '<path d="m6 6 12 12M18 6 6 18"/>',
		download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
		theme: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/>',
		moon: '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/>'
	};

	function escape(value) {
		return String(value === null || typeof value === 'undefined' ? '' : value)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
	}

	function icon(name) {
		return '<span class="ar-icon" aria-hidden="true"><svg viewBox="0 0 24 24">' + (icons[name] || icons.info) + '</svg></span>';
	}

	function mountIcons(root) {
		(root || document).querySelectorAll('.ar-icon[data-icon]:not([data-mounted])').forEach((node) => {
			node.innerHTML = '<svg viewBox="0 0 24 24">' + (icons[node.dataset.icon] || icons.info) + '</svg>';
			node.dataset.mounted = '1';
		});
	}

	function isMissingValue(value) {
		return value === null || typeof value === 'undefined' || String(value).trim() === '' || /^(?:undefined|null|nan)$/i.test(String(value).trim());
	}

	function stableDigits(value) {
		const persian = '۰۱۲۳۴۵۶۷۸۹';
		const arabic = '٠١٢٣٤٥٦٧٨٩';
		return String(value).replace(/[۰-۹٠-٩]/g, (digit) => {
			const persianIndex = persian.indexOf(digit);
			return String(persianIndex >= 0 ? persianIndex : arabic.indexOf(digit));
		});
	}

	// Kept as a public compatibility name; stable Latin glyphs prevent fallback fonts rendering Persian zero as a diamond.
	function persianDigits(value) {
		return isMissingValue(value) ? '—' : stableDigits(value);
	}

	function decimal(value, maxFraction) {
		if (isMissingValue(value)) return '—';
		const source = stableDigits(value).replace(/,/g, '').trim();
		const match = source.match(/^(-?)(\d+)(?:\.(\d+))?$/);
		if (!match) return '—';
		const grouped = match[2].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		const fraction = (match[3] || '').slice(0, typeof maxFraction === 'number' ? maxFraction : 4).replace(/0+$/, '');
		return persianDigits(match[1] + grouped + (fraction ? '.' + fraction : ''));
	}

	function money(value, currency) {
		const amount = decimal(value, 4);
		if (amount === '—') return '<span class="ar-money ar-money--unavailable">—</span>';
		return '<span class="ar-money"><bdi>' + escape(amount) + '</bdi> <small dir="ltr">' + escape(currency || (window.ArvanResellerRuntime.settings || {}).currency || 'IRR') + '</small></span>';
	}

	function date(value) {
		if (!value) return '—';
		const normalized = typeof value === 'number' ? value : (String(value).includes('T') ? String(value) : String(value).replace(' ', 'T') + 'Z');
		const parsed = new Date(normalized);
		if (Number.isNaN(parsed.getTime())) return escape(value);
		return new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(parsed);
	}

	function statusLabel(value) {
		const raw = String(value || 'unknown');
		return statusLabels[raw] || statusLabels[raw.toLowerCase()] || statusLabels.unknown;
	}

	function status(value) {
		const raw = String(value || 'unknown');
		const success = ['active', 'available', 'completed', 'provisioned', 'paid', 'sent', 'healthy', 'AVAILABLE', 'ACTIVE'];
		const warning = ['pending', 'provisioning', 'processing', 'suspended', 'degraded', 'running', 'never_run', 'creating', 'build', 'building', 'BUILD', 'BUILDING', 'SHUTOFF', 'POWERED_OFF'];
		const danger = ['failed', 'cancelled', 'expired', 'terminated', 'deleted', 'error', 'void', 'TERMINATED', 'DELETED', 'ERROR'];
		const tone = success.includes(raw) ? 'success' : (warning.includes(raw) ? 'warning' : (danger.includes(raw) ? 'danger' : 'info'));
		return '<span class="ar-status ar-status--' + tone + '" title="' + escape(raw) + '">' + escape(statusLabel(raw)) + '</span>';
	}

	function errorMessage(error) {
		return errorMessages[error && error.code] || 'عملیات انجام نشد. با کد پشتیبانی ' + escape(error && error.code ? error.code : 'unknown') + ' دوباره تلاش کنید.';
	}

	function pageHead(title, description, actions) {
		return '<header class="ar-page-head"><div><h1>' + escape(title) + '</h1><p>' + escape(description || '') + '</p></div><div class="ar-page-actions">' + (actions || '') + '</div></header>';
	}

	function empty(title, description, action) {
		return '<div class="ar-empty-state">' + icon('server') + '<h2>' + escape(title) + '</h2><p>' + escape(description || '') + '</p>' + (action || '') + '</div>';
	}

	function error(errorObject, retryAction) {
		return '<div class="ar-error-state">' + icon('warning') + '<h2>دریافت اطلاعات ممکن نشد</h2><p>' + errorMessage(errorObject) + '</p><code dir="ltr">' + escape(errorObject && errorObject.code || 'unknown') + '</code>' + (retryAction ? '<button class="ar-button ar-button--secondary" type="button" data-ar-action="' + escape(retryAction) + '">' + icon('refresh') + 'تلاش دوباره</button>' : '') + '</div>';
	}

	function loading(label) {
		return '<div class="ar-loading-state"><span class="ar-spinner" aria-hidden="true"></span><p>' + escape(label || 'در حال دریافت اطلاعات…') + '</p></div>';
	}

	function toast(message, tone) {
		const region = document.querySelector('.arvan-reseller-app .ar-toast-region');
		if (!region) return;
		const item = document.createElement('div');
		item.className = 'ar-toast ar-toast--' + (tone || 'info');
		item.innerHTML = icon(tone === 'danger' ? 'warning' : 'check') + '<span>' + escape(message) + '</span>';
		region.appendChild(item);
		window.setTimeout(() => item.remove(), 5200);
	}

	function modal(options) {
		const root = document.querySelector('.arvan-reseller-app .ar-modal-root');
		if (!root) return null;
		const previousFocus = document.activeElement;
		root.innerHTML = '<div class="ar-modal-backdrop" data-ar-modal-backdrop><section class="ar-modal" role="dialog" aria-modal="true" aria-labelledby="ar-modal-title"><div class="ar-modal__head"><div><h2 id="ar-modal-title">' + escape(options.title) + '</h2>' + (options.description ? '<p>' + escape(options.description) + '</p>' : '') + '</div><button class="ar-icon-button" type="button" data-ar-modal-close aria-label="بستن">' + icon('close') + '</button></div><div class="ar-modal__body">' + (options.body || '') + '</div></section></div>';
		const backdrop = root.querySelector('[data-ar-modal-backdrop]');
		const dialog = root.querySelector('.ar-modal');
		const close = () => {
			root.innerHTML = '';
			document.removeEventListener('keydown', keyHandler);
			if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
			if (typeof options.onClose === 'function') options.onClose();
		};
		const keyHandler = (event) => {
			if (event.key === 'Escape') close();
			if (event.key === 'Tab') {
				const focusable = Array.from(dialog.querySelectorAll('button, input, select, textarea, a[href]')).filter((node) => !node.disabled);
				if (!focusable.length) return;
				const first = focusable[0]; const last = focusable[focusable.length - 1];
				if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
				if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
			}
		};
		root.querySelector('[data-ar-modal-close]').addEventListener('click', close);
		backdrop.addEventListener('mousedown', (event) => { if (event.target === backdrop) close(); });
		document.addEventListener('keydown', keyHandler);
		window.setTimeout(() => (dialog.querySelector('input, button, select, textarea, a[href]') || dialog).focus(), 0);
		return { root: root, dialog: dialog, close: close };
	}

	function confirm(options) {
		return new Promise((resolve) => {
			let settled = false;
			const instance = modal({
				title: options.title,
				description: options.description,
				body: '<div class="ar-alert ' + (options.danger ? 'ar-alert--danger' : 'ar-alert--warning') + '">' + icon('warning') + '<div><strong>' + escape(options.notice || 'پیش از ادامه بررسی کنید') + '</strong><p>' + escape(options.detail || '') + '</p></div></div><div class="ar-modal__actions"><button type="button" class="ar-button ' + (options.danger ? 'ar-button--danger' : 'ar-button--primary') + '" data-confirm>' + escape(options.confirmLabel || 'تأیید') + '</button><button type="button" class="ar-button ar-button--secondary" data-cancel>انصراف</button></div>',
				onClose: () => { if (!settled) { settled = true; resolve(false); } }
			});
			instance.dialog.querySelector('[data-confirm]').addEventListener('click', () => { settled = true; instance.close(); resolve(true); });
			instance.dialog.querySelector('[data-cancel]').addEventListener('click', () => { settled = true; instance.close(); resolve(false); });
		});
	}

	function lineChart(values) {
		const rows = (values || []).map(Number).filter(Number.isFinite);
		if (!rows.length) return '<div class="ar-empty-state"><p>داده‌ای برای نمودار ثبت نشده است.</p></div>';
		const max = Math.max(...rows, 1); const min = Math.min(...rows, 0); const range = max - min || 1;
		const points = rows.map((value, index) => {
			const x = rows.length === 1 ? 50 : (index / (rows.length - 1)) * 100;
			const y = 92 - ((value - min) / range) * 75;
			return [x, y];
		});
		const polyline = points.map((point) => point.join(',')).join(' ');
		const area = '0,100 ' + polyline + ' 100,100';
		return '<div class="ar-chart" aria-label="نمودار روند"><svg viewBox="0 0 100 100" preserveAspectRatio="none"><defs><linearGradient id="ar-chart-gradient" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1473e6"/><stop offset="1" stop-color="#1473e6" stop-opacity="0"/></linearGradient></defs><g class="ar-chart-grid"><line x1="0" y1="25" x2="100" y2="25"/><line x1="0" y1="50" x2="100" y2="50"/><line x1="0" y1="75" x2="100" y2="75"/></g><polygon class="ar-chart-area" points="' + area + '"/><polyline class="ar-chart-line" points="' + polyline + '" vector-effect="non-scaling-stroke"/><g class="ar-chart-dots">' + points.map((point) => '<circle cx="' + point[0] + '" cy="' + point[1] + '" r="1.6" vector-effect="non-scaling-stroke"/>').join('') + '</g></svg><div class="ar-chart-legend"><span>قدیمی‌تر</span><span>اکنون</span></div></div>';
	}

	function wireGlobalActions(app) {
		if (!app) return;
		mountIcons(app);
		wireThemeControls(app);
		wireLanguageControls(app);
		app.addEventListener('click', (event) => {
			const action = event.target.closest('[data-ar-action]');
			if (!action) return;
			if (action.dataset.arAction === 'toggle-sidebar') {
				const open = !app.classList.contains('is-sidebar-open');
				app.classList.toggle('is-sidebar-open', open);
				action.setAttribute('aria-expanded', String(open));
				const scrim = app.querySelector('.ar-sidebar-scrim'); if (scrim) scrim.hidden = !open;
			}
			if (action.dataset.arAction === 'close-sidebar') {
				app.classList.remove('is-sidebar-open'); action.hidden = true;
			}
			if (action.dataset.arAction === 'toggle-password') {
				const input = action.closest('.ar-password-field').querySelector('input');
				input.type = input.type === 'password' ? 'text' : 'password';
				action.setAttribute('aria-label', input.type === 'password' ? 'نمایش رمز' : 'پنهان کردن رمز');
			}
		});
		window.addEventListener('arvan:session-expired', () => {
			toast('نشست شما پایان یافته است. به صفحه ورود هدایت می‌شوید.', 'danger');
			window.setTimeout(() => { window.location.href = window.ArvanResellerRuntime.loginUrl; }, 1800);
		}, { once: true });
	}

	window.ArvanUI = { escape, icon, mountIcons, persianDigits, decimal, money, date, statusLabel, status, errorMessage, pageHead, empty, error, loading, toast, modal, confirm, lineChart, t, translateDom, applyLanguage, applyTheme, wireLanguageControls, wireThemeControls, wireGlobalActions };
	document.addEventListener('DOMContentLoaded', () => {
		document.querySelectorAll('.arvan-reseller-app').forEach(wireGlobalActions);
	});
}());
