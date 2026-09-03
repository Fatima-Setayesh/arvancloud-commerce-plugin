(function () {
	'use strict';

	const app = document.querySelector('.ar-customer-portal');
	if (!app) return;

	const content = app.querySelector('#ar-customer-content');
	const api = window.ArvanResellerAPI;
	const ui = window.ArvanUI;
	const runtime = window.ArvanResellerRuntime;
	const state = {
		route: 'dashboard',
		resourceId: null,
		catalog: null,
		configStep: 0,
		config: { region: runtime.settings.region || '', availabilityZone: runtime.settings.availabilityZone || '', image: null, flavor: null, rootVolumeSizeGigaBytes: 25, name: '', enableBackup: false, enableFailOver: false, enableIpv4: true, enableIpv6: false },
		estimate: null,
		estimateAt: null,
		pollTimer: null,
		pollAttempts: 0,
		orderOperation: 'cloud-server-order-' + Date.now(),
		accountOpen: false,
		accountBackground: [],
		bodyOverflow: ''
	};

	const routeMeta = {
		dashboard: ['nav.dashboard', 'داشبورد', 'نمای کوتاه از کیف پول و سرویس‌ها'], services: ['nav.services', 'سرویس‌ها', 'سرورهای ابری متعلق به حساب شما'],
		'create-server': ['nav.createServer', 'ساخت سرور ابری', 'پیکربندی مرحله‌ای با برآورد معتبر سمت سرور'], wallet: ['nav.wallet', 'کیف پول', 'شارژ آزمایشی و دفترکل تغییرناپذیر'],
		billing: ['nav.billing', 'مصرف و صورتحساب', 'پنجره‌های دقیق مصرف و صورت‌حساب‌ها'], orders: ['nav.orders', 'سفارش‌ها', 'رهگیری ساخت و شناسه منبع'],
		notifications: ['nav.notifications', 'اعلان‌ها', 'هشدارهای موجودی و رویدادهای سرویس'], resource: ['nav.services', 'جزئیات سرویس', 'وضعیت واقعی ثبت‌شده در سامانه']
	};

	function currentMeta() { const meta = routeMeta[state.route] || routeMeta.dashboard; return [meta[1], meta[2]]; }
	function setContent(html) { content.innerHTML = html; ui.mountIcons(content); ui.translateDom(content); }
	function numeric(value) { const number = Number(String(value || '0').replace(/,/g, '')); return Number.isFinite(number) ? number : 0; }
	function modeLabel(mode) { return mode === 'live' ? 'زنده' : 'آزمایشی'; }
	function title() {
		const meta = currentMeta();
		const titleNode = app.querySelector('#ar-customer-page-title');
		if (titleNode) {
			titleNode.dataset.arI18n = (routeMeta[state.route] || routeMeta.dashboard)[0];
			titleNode.textContent = meta[0];
			ui.translateDom(titleNode);
		}
		document.title = ui.translateOwnedText(meta[0]) + ' — ' + ui.translateOwnedText('فروش ابری آروان');
	}
	function pageHead(actions) { const meta = currentMeta(); return ui.pageHead(meta[0], meta[1], actions); }

	function metric(label, value, icon, meta, route) {
		const missing = value === null || typeof value === 'undefined' || value === '' || (typeof value === 'number' && !Number.isFinite(value)) || /^(?:undefined|null|nan)$/i.test(String(value).trim());
		const displayValue = missing ? '—' : value;
		const opening = route ? '<button type="button" class="ar-card ar-metric ar-dashboard-card ar-dashboard-link" data-ar-route="' + ui.escape(route) + '" aria-label="' + ui.escape(label) + '">' : '<article class="ar-card ar-metric">';
		return opening + '<span class="ar-metric__icon">' + ui.icon(icon) + '</span><span class="ar-metric__label">' + ui.escape(label) + '</span><strong class="ar-metric__value">' + displayValue + '</strong><span class="ar-metric__meta">' + ui.escape(meta || '') + '</span>' + (route ? '</button>' : '</article>');
	}

	function table(headers, rows, emptyText) {
		if (!rows.length) return ui.empty(emptyText, 'وقتی داده‌ای ثبت شود، در این بخش نمایش داده می‌شود.');
		return '<div class="ar-table-wrap"><table class="ar-table ar-responsive-table"><thead><tr>' + headers.map((header) => '<th scope="col">' + ui.escape(header) + '</th>').join('') + '</tr></thead><tbody>' + rows.map((row) => '<tr>' + row.map((cell, index) => '<td data-label="' + ui.escape(headers[index]) + '">' + cell + '</td>').join('') + '</tr>').join('') + '</tbody></table></div>';
	}

	function resourceCard(resource) {
		return '<article class="ar-card ar-resource-card"><div class="ar-resource-card__head"><div style="display:flex;gap:12px"><span class="ar-resource-card__icon">' + ui.icon('server') + '</span><div><span class="ar-eyebrow">سرور ابری · ' + ui.escape(resource.region || '—') + '</span><h2 style="margin:0;font-size:17px">' + ui.escape(resource.name || 'سرور ابری') + '</h2></div></div>' + ui.status(resource.status) + '</div><div class="ar-resource-card__meta"><div><span>شناسه منبع</span><strong><code dir="ltr">' + ui.escape(resource.resource_id) + '</code></strong></div><div><span>وضعیت راه‌دور</span><strong>' + ui.status(resource.remote_status) + '</strong></div><div><span>نرخ ساعتی</span><strong>' + ui.money(resource.hourly_price, resource.currency) + '</strong></div><div><span>آخرین صورتحساب</span><strong>' + ui.date(resource.last_billed_at) + '</strong></div></div><button class="ar-button ar-button--secondary" type="button" data-resource-id="' + ui.escape(resource.id) + '">مشاهده جزئیات</button></article>';
	}

	function closeAccount(restoreFocus = true) {
		const drawer = app.querySelector('[data-ar-account-drawer]');
		const scrim = app.querySelector('.ar-account-scrim');
		const trigger = app.querySelector('[data-ar-action="toggle-account"]');
		if (!drawer || !state.accountOpen) return;
		state.accountOpen = false; drawer.hidden = true; scrim.hidden = true;
		state.accountBackground.forEach((entry) => { entry.node.inert = entry.inert; });
		state.accountBackground = [];
		document.body.style.overflow = state.bodyOverflow;
		trigger.setAttribute('aria-expanded', 'false');
		if (restoreFocus) trigger.focus();
	}

	async function openAccount() {
		const drawer = app.querySelector('[data-ar-account-drawer]');
		const scrim = app.querySelector('.ar-account-scrim');
		const trigger = app.querySelector('[data-ar-action="toggle-account"]');
		const accountContent = app.querySelector('[data-ar-account-content]');
		if (!drawer || state.accountOpen) return;
		state.accountBackground = Array.from(app.children)
			.filter((node) => node !== drawer && node !== scrim)
			.map((node) => ({ node: node, inert: node.inert }));
		state.accountBackground.forEach((entry) => { entry.node.inert = true; });
		state.bodyOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';
		state.accountOpen = true; drawer.hidden = false; scrim.hidden = false;
		trigger.setAttribute('aria-expanded', 'true'); drawer.focus();
		accountContent.innerHTML = ui.loading('در حال دریافت خلاصه حساب…');
		ui.translateDom(accountContent);
		try {
			const results = await Promise.allSettled([api.get('wallet'), api.get('resources'), api.get('orders', { query: { limit: 3 } })]);
			const walletData = results[0].status === 'fulfilled' ? results[0].value : null;
			const resources = results[1].status === 'fulfilled' ? results[1].value : [];
			const ordersData = results[2].status === 'fulfilled' ? results[2].value : [];
			const active = resources.filter((resource) => ['active', 'provisioned'].includes(resource.status));
			const recent = resources.slice(0, 3);
			accountContent.innerHTML = '<section class="ar-account-summary"><div><small>موجودی کیف پول</small><strong>' + ui.money(walletData && walletData.balance, walletData && walletData.currency) + '</strong></div><div><small>سرویس فعال</small><strong>' + ui.persianDigits(active.length) + '</strong></div><div><small>سفارش اخیر</small><strong>' + ui.persianDigits(ordersData.length) + '</strong></div></section><section class="ar-account-recent"><div class="ar-card-head"><h3>سرویس‌های اخیر</h3><button class="ar-button ar-button--secondary ar-button--small" type="button" data-ar-route="services">مشاهده همه</button></div>' + (recent.length ? recent.map((resource) => '<button type="button" data-resource-id="' + ui.escape(resource.id) + '"><span class="ar-icon" data-icon="server" aria-hidden="true"></span><span><strong>' + ui.escape(resource.name || 'سرور ابری') + '</strong><small><code dir="ltr">' + ui.escape(resource.resource_id || '—') + '</code></small></span>' + ui.status(resource.status) + '</button>').join('') : '<p class="ar-account-recent__empty">هنوز سرویسی ثبت نشده است.</p>') + '</section>';
			ui.mountIcons(accountContent); ui.translateDom(accountContent);
		} catch (error) {
			accountContent.innerHTML = ui.error(error);
			ui.mountIcons(accountContent); ui.translateDom(accountContent);
		}
	}

	async function dashboard() {
		setContent(ui.loading('در حال آماده‌سازی داشبورد…'));
		const results = await Promise.allSettled([api.get('wallet'), api.get('resources'), api.get('notifications', { query: { limit: 5 } }), api.get('wallet/transactions', { query: { limit: 6 } }), api.get('usage', { query: { limit: 20 } })]);
		const get = (index, fallback) => results[index].status === 'fulfilled' ? results[index].value : fallback;
		const wallet = get(0, null); const resources = get(1, []); const notifications = get(2, []); const transactions = get(3, []); const usage = get(4, []);
		const available = results.map((result) => result.status === 'fulfilled');
		if (!wallet && results.every((result) => result.status === 'rejected')) { setContent(pageHead() + ui.error(results[0].reason, 'retry-route')); return; }
		const active = resources.filter((resource) => ['active', 'provisioned'].includes(resource.status));
		const low = wallet && numeric(wallet.balance) <= numeric(wallet.threshold);
		const costs = usage.slice().reverse().map((row) => numeric(row.total_charge));
		const unread = notifications.filter((item) => !item.is_read).length;
		const usageTotal = available[4] ? usage.reduce((sum, row) => sum + numeric(row.total_charge), 0).toFixed(4) : null;
		const transactionRows = transactions.map((row) => [ui.status(row.type === 'credit' || String(row.amount || '').charAt(0) !== '-' ? 'completed' : 'issued').replace(/تکمیل‌شده|صادرشده/, ui.escape(ui.statusLabel(row.type))), ui.money(row.amount, row.currency), ui.escape(row.description || row.reference_type), ui.date(row.created_at)]);
		setContent(pageHead('<button class="ar-button ar-button--secondary" type="button" data-ar-route="wallet">' + ui.icon('wallet') + '<span data-ar-i18n="action.topupWallet">شارژ کیف پول</span></button><button class="ar-button" type="button" data-ar-route="create-server">' + ui.icon('plus') + '<span data-ar-i18n="action.createServer">ساخت سرور</span></button>') +
			(low ? '<div class="ar-alert ar-alert--warning">' + ui.icon('warning') + '<div><strong>موجودی کیف پول پایین است</strong><p>برای جلوگیری از اعمال سیاست تعلیق، موجودی را بررسی و در حالت آزمایشی شارژ کنید.</p></div><button class="ar-button ar-button--secondary ar-button--small" type="button" data-ar-route="wallet">شارژ کیف پول</button></div>' : '') +
			'<section class="ar-grid ar-grid--metrics ar-customer-metrics" style="margin-top:16px"><article class="ar-card ar-metric ar-dashboard-card ar-dashboard-wallet"><button class="ar-dashboard-wallet__target" type="button" data-ar-route="wallet" aria-label="کیف پول"><span class="ar-metric__icon">' + ui.icon('wallet') + '</span><span class="ar-metric__label">موجودی کیف پول</span><strong class="ar-metric__value">' + ui.money(wallet && wallet.balance, wallet && wallet.currency) + '</strong><span class="ar-metric__meta">' + (wallet ? ui.status(wallet.status) : '—') + '</span></button><button class="ar-button ar-button--small" type="button" data-ar-action="open-topup">' + ui.icon('plus') + '<span data-ar-i18n="action.topupWallet">شارژ کیف پول</span></button></article>' + metric('سرویس‌های فعال', available[1] ? ui.persianDigits(active.length) : '—', 'server', available[1] ? ui.persianDigits(resources.length) + ' سرویس ثبت‌شده' : 'داده در دسترس نیست', 'services') + metric('مصرف دوره', ui.money(usageTotal, runtime.settings.currency), 'chart', available[4] ? ui.persianDigits(usage.length) + ' پنجره مصرف' : 'داده در دسترس نیست', 'billing') + metric('اعلان‌های خوانده‌نشده', available[2] ? ui.persianDigits(unread) : '—', 'bell', available[2] ? ui.persianDigits(notifications.length) + ' اعلان اخیر' : 'داده در دسترس نیست', 'notifications') + '</section>' +
			'<section class="ar-layout-main"><div class="ar-stack"><article class="ar-card"><div class="ar-card-head"><div><h2>مصرف اخیر</h2><p>هزینه‌های قطعی ثبت‌شده در سامانه</p></div><button class="ar-button ar-button--secondary ar-button--small" type="button" data-ar-route="billing">جزئیات</button></div>' + ui.lineChart(costs) + '</article><article class="ar-card ar-card--flush"><div class="ar-card-head" style="padding:20px 20px 0"><h2>تراکنش‌های اخیر</h2></div>' + table(['نوع', 'مبلغ', 'شرح', 'زمان'], transactionRows, 'هنوز تراکنشی ندارید') + '</article></div><div class="ar-stack"><article class="ar-card"><div class="ar-card-head"><h2>سرور فعال</h2><button class="ar-button ar-button--secondary ar-button--small" type="button" data-ar-route="services">همه سرویس‌ها</button></div>' + (active[0] ? resourceCard(active[0]) : ui.empty('سرور فعالی ندارید', 'از پیکربندی مرحله‌ای برای ساخت سرور ابری استفاده کنید.', '<button class="ar-button" type="button" data-ar-route="create-server">ساخت سرور</button>')) + '</article><article class="ar-card"><div class="ar-card-head"><h2>آخرین اعلان‌ها</h2></div>' + (notifications.length ? '<ol class="ar-timeline">' + notifications.map((item) => '<li class="' + (item.status === 'sent' ? 'is-complete' : 'is-current') + '"><span class="ar-timeline__marker">' + ui.icon(item.status === 'sent' ? 'check' : 'bell') + '</span><div><strong>' + notificationLabel(item.type) + '</strong><small>' + ui.date(item.created_at) + '</small></div></li>').join('') + '</ol>' : ui.empty('اعلانی ندارید', 'هشدارهای موجودی و سرویس اینجا نمایش داده می‌شوند.')) + '</article></div></section>');
	}

	async function services() {
		setContent(ui.loading());
		try {
			const resources = await api.get('resources');
			setContent(pageHead('<button class="ar-button" type="button" data-ar-route="create-server">' + ui.icon('plus') + 'ساخت سرور جدید</button>') + (resources.length ? '<section class="ar-grid ar-grid--3">' + resources.map(resourceCard).join('') + '</section>' : ui.empty('هنوز سرور ابری ندارید', 'منطقه، سیستم‌عامل و پلن را از کاتالوگ سرویس انتخاب کنید.', '<button class="ar-button" type="button" data-ar-route="create-server">شروع پیکربندی</button>')));
		} catch (error) { setContent(pageHead() + ui.error(error, 'retry-route')); }
	}

	async function resourceDetail() {
		setContent(ui.loading());
		try {
			const [resource, usage, invoices, walletData] = await Promise.all([api.get('resources/' + encodeURIComponent(state.resourceId)), api.get('usage', { query: { limit: 100 } }), api.get('invoices', { query: { limit: 100 } }), api.get('wallet')]);
			const ownUsage = usage.filter((row) => String(row.resource_id) === String(resource.resource_id));
			const low = numeric(walletData.balance) <= numeric(walletData.threshold);
			const usageValues = ownUsage.slice().reverse().map((row) => numeric(row.total_charge));
			setContent(pageHead('<button class="ar-button ar-button--secondary" type="button" data-ar-route="services">بازگشت به سرویس‌ها</button>') +
				'<article class="ar-card ar-resource-hero"><div class="ar-resource-hero__identity"><span class="ar-resource-hero__icon">' + ui.icon('server') + '</span><div><span class="ar-eyebrow">سرور ابری · ' + ui.escape(resource.region || '—') + '</span><h2>' + ui.escape(resource.name || 'سرویس ابری') + '</h2><div class="ar-resource-id"><code dir="ltr">' + ui.escape(resource.resource_id) + '</code><button class="ar-icon-button" type="button" data-copy="' + ui.escape(resource.resource_id) + '" aria-label="کپی شناسه">' + ui.icon('copy') + '</button></div></div></div>' + ui.status(resource.status) + '</article>' +
				(low ? '<div class="ar-alert ar-alert--warning">' + ui.icon('warning') + '<div><strong>هشدار کاهش موجودی</strong><p>موجودی کیف پول به آستانه هشدار رسیده است.</p></div><button class="ar-button ar-button--secondary ar-button--small" type="button" data-ar-route="wallet">شارژ کیف پول</button></div>' : '') +
				'<section class="ar-resource-strip"><article><span class="ar-icon" data-icon="region"></span><small>منطقه / ناحیه</small><strong><code dir="ltr">' + ui.escape(resource.availability_zone || resource.region || '—') + '</code></strong></article><article><span class="ar-icon" data-icon="server"></span><small>وضعیت راه‌دور</small><strong>' + ui.status(resource.remote_status) + '</strong></article><article><span class="ar-icon" data-icon="chart"></span><small>نرخ ساعتی</small><strong>' + ui.money(resource.hourly_price, resource.currency) + '</strong></article><article><span class="ar-icon" data-icon="receipt"></span><small>آخرین صورتحساب</small><strong>' + ui.date(resource.last_billed_at) + '</strong></article></section>' +
				'<section class="ar-layout-main"><div class="ar-stack"><article class="ar-card"><div class="ar-card-head"><div><h2>مصرف و هزینه سرویس</h2><p>مقادیر قطعی ثبت‌شده برای همین شناسه منبع</p></div><button class="ar-button ar-button--secondary ar-button--small" type="button" data-ar-route="billing">جزئیات مصرف</button></div>' + ui.lineChart(usageValues) + '</article><article class="ar-card"><div class="ar-card-head"><h2>پیکربندی ثبت‌شده</h2></div><dl class="ar-summary-list"><div><dt>تصویر</dt><dd>' + ui.escape(resource.image && (resource.image.name || resource.image.id) || 'ارائه نشده') + '</dd></div><div><dt>پلن</dt><dd>' + ui.escape(resource.flavor && (resource.flavor.name || resource.flavor.id) || 'ارائه نشده') + '</dd></div><div><dt>دیسک ریشه</dt><dd>' + (resource.root_volume_size_gb ? ui.persianDigits(resource.root_volume_size_gb) + ' گیگابایت' : 'ارائه نشده') + '</dd></div><div><dt>نشانی IP</dt><dd><code dir="ltr">' + ui.escape((resource.ip_addresses || []).join(' · ') || 'ارائه نشده') + '</code></dd></div></dl><small>فقط فیلدهای موجود در پاسخ رسمی سرویس نمایش داده می‌شوند.</small></article></div><aside class="ar-stack"><article class="ar-card ar-resource-wallet"><div class="ar-card-head"><h2>کیف پول</h2>' + ui.status(walletData.status) + '</div><strong class="ar-metric__value">' + ui.money(walletData.balance, walletData.currency) + '</strong><small>موجودی قابل استفاده حساب</small><button class="ar-button ar-button--block" type="button" data-ar-action="open-topup">شارژ کیف پول</button></article><article class="ar-card"><div class="ar-card-head"><h2>چرخه عمر</h2></div>' + provisioningTimeline(resource.status, resource.resource_id) + '</article><article class="ar-card"><div class="ar-card-head"><h2>صورتحساب‌ها</h2></div><strong class="ar-metric__value">' + ui.persianDigits(invoices.length) + '</strong><small>صورتحساب‌های حساب مشتری</small><button class="ar-button ar-button--secondary ar-button--block" type="button" data-ar-route="billing" style="margin-top:16px">مشاهده صورتحساب</button></article></aside></section>');
		} catch (error) { setContent(pageHead() + ui.error(error, 'retry-route')); }
	}

	function provisioningTimeline(status, resourceId) {
		const failed = ['failed', 'error'].includes(status); const ready = ['active', 'provisioned', 'suspended', 'terminated'].includes(status);
		const steps = [['سفارش ثبت شد', true], ['موجودی و سفارش بررسی شد', true], ['درخواست ساخت ارسال شد', true], ['شناسه سرویس دریافت شد', Boolean(resourceId)], ['سرویس فعال شد', ready]];
		const firstPending = steps.findIndex((step) => !step[1]);
		return '<ol class="ar-timeline ar-provision-timeline">' + steps.map((step, index) => '<li class="' + (step[1] ? 'is-complete' : (!failed && index === firstPending ? 'is-current' : (failed && index === firstPending ? 'is-failed' : ''))) + '"><span class="ar-timeline__marker">' + ui.icon(step[1] ? 'check' : (failed && index === firstPending ? 'warning' : (index === firstPending ? 'refresh' : 'server'))) + '</span><div><strong>' + step[0] + '</strong><small>' + (failed && index === firstPending ? 'فرایند ناموفق یا نیازمند بازیابی' : (step[1] ? 'تکمیل‌شده' : (index === firstPending ? 'در حال پیگیری' : 'در انتظار'))) + '</small></div></li>').join('') + '</ol>';
	}

	async function wallet() {
		setContent(ui.loading());
		try {
			const [walletData, payments, transactions] = await Promise.all([api.get('wallet'), api.get('payments', { query: { limit: 50 } }), api.get('wallet/transactions', { query: { limit: 100 } })]);
			const low = numeric(walletData.balance) <= numeric(walletData.threshold);
			const paymentRows = payments.map((payment) => ['<code dir="ltr">' + ui.escape(payment.payment_reference) + '</code>', ui.money(payment.amount, payment.currency), ui.status(payment.status), ui.escape(payment.provider === 'mock' ? 'آزمایشی' : payment.provider), ui.date(payment.created_at), payment.status === 'pending' && payment.provider === 'mock' ? '<button class="ar-button ar-button--small" type="button" data-confirm-payment="' + ui.escape(payment.payment_reference) + '">تأیید پرداخت</button>' : '—']);
			const transactionRows = transactions.map((row) => [ui.escape(ui.statusLabel(row.type)), ui.money(row.amount, row.currency), ui.escape(row.reference_type), ui.escape(row.description || '—'), ui.date(row.created_at)]);
			setContent(pageHead('<button class="ar-button" type="button" data-ar-action="open-topup">' + ui.icon('plus') + 'شارژ کیف پول</button>') + (low ? '<div class="ar-alert ar-alert--warning">' + ui.icon('warning') + '<div><strong>موجودی در محدوده هشدار است</strong><p>آستانه فعلی ' + ui.money(walletData.threshold, walletData.currency) + ' است.</p></div></div>' : '') + '<section class="ar-grid ar-grid--3" style="margin-top:16px"><article class="ar-card ar-wallet-hero"><small>موجودی قابل استفاده</small><strong class="ar-wallet-balance">' + ui.money(walletData.balance, walletData.currency) + '</strong><span>' + ui.status(walletData.status) + '</span></article>' + metric('آستانه هشدار', ui.money(walletData.threshold, walletData.currency), 'warning', 'هشدار خودکار سامانه') + metric('تراکنش‌ها', ui.persianDigits(transactions.length), 'receipt', 'دفترکل تغییرناپذیر') + '</section><article class="ar-card ar-card--flush" style="margin-top:16px"><div class="ar-card-head" style="padding:20px 20px 0"><h2>پرداخت‌ها</h2><span class="ar-env ar-env--' + ui.escape(runtime.settings.mode) + '"><span></span>' + modeLabel(runtime.settings.mode) + '</span></div>' + table(['مرجع', 'مبلغ', 'وضعیت', 'درگاه', 'زمان', 'عملیات'], paymentRows, 'پرداختی ثبت نشده است') + '</article><article class="ar-card ar-card--flush" style="margin-top:16px"><div class="ar-card-head" style="padding:20px 20px 0"><h2>تاریخچه تراکنش</h2></div>' + table(['نوع', 'مبلغ', 'مرجع', 'شرح', 'زمان'], transactionRows, 'تراکنشی ثبت نشده است') + '</article>');
		} catch (error) { setContent(pageHead() + ui.error(error, 'retry-route')); }
	}

	function openTopup() {
		if (runtime.settings.mode !== 'mock') {
			ui.modal({ title: 'شارژ کیف پول', description: 'پرداخت آنلاین در حالت فعلی در دسترس نیست.', body: '<div class="ar-alert ar-alert--warning">' + ui.icon('warning') + '<div><strong>درگاه پرداخت پیکربندی نشده است</strong><p>برای جلوگیری از نمایش یک درگاه غیرواقعی، عملیات شارژ در حالت زنده غیرفعال است.</p></div></div>' });
			return;
		}
		const operation = 'customer-payment-create-' + Date.now();
		const key = api.operationKey(operation);
		const minimum = numeric(runtime.settings.minimumTopup); const maximum = numeric(runtime.settings.maximumTopup);
		const presets = [500000, 1000000, 2000000, 5000000].filter((amount) => amount >= minimum && amount <= maximum);
		const presetMarkup = presets.length ? '<div class="ar-topup-presets" aria-label="مبلغ‌های پیشنهادی">' + presets.map((amount) => '<button type="button" data-topup-preset="' + amount + '">' + ui.decimal(amount) + '</button>').join('') + '</div>' : '';
		const initialAmount = runtime.settings.minimumTopup || '1.0000';
		const modal = ui.modal({ title: 'شارژ آزمایشی کیف پول', description: 'این عملیات کاملاً آزمایشی و بدون تراکنش بانکی واقعی است.', body: '<form id="ar-topup-form" class="ar-form"><div class="ar-alert ar-alert--success">' + ui.icon('info') + '<div><span class="ar-env ar-env--mock"><span></span>آزمایشی</span><strong>پرداخت و تأیید آزمایشی</strong><p>پرداخت از endpoint موجود ساخته و سپس از مسیر تأیید Mock به‌صورت اتمیک به کیف پول افزوده می‌شود.</p></div></div><div class="ar-field"><label for="ar-topup-amount">مبلغ شارژ</label>' + presetMarkup + '<input id="ar-topup-amount" name="amount" type="text" inputmode="decimal" dir="ltr" required value="' + ui.escape(initialAmount) + '"><small>بازه مجاز: ' + ui.escape(runtime.settings.minimumTopup) + ' تا ' + ui.escape(runtime.settings.maximumTopup) + ' ' + ui.escape(runtime.settings.currency) + '</small></div><div class="ar-topup-preview"><span>مبلغ پرداخت</span><strong data-topup-preview>' + ui.money(initialAmount, runtime.settings.currency) + '</strong><small>روش: پرداخت آزمایشی</small></div><div class="ar-modal__actions"><button class="ar-button ar-button--block" type="submit">تکمیل شارژ آزمایشی</button></div></form>' });
		const amountInput = modal.dialog.querySelector('#ar-topup-amount'); const preview = modal.dialog.querySelector('[data-topup-preview]');
		modal.dialog.addEventListener('click', (event) => { const preset = event.target.closest('[data-topup-preset]'); if (!preset) return; amountInput.value = preset.dataset.topupPreset; preview.innerHTML = ui.money(amountInput.value, runtime.settings.currency); amountInput.focus(); });
		amountInput.addEventListener('input', () => { preview.innerHTML = ui.money(amountInput.value || '0', runtime.settings.currency); });
		modal.dialog.querySelector('#ar-topup-form').addEventListener('submit', async (event) => {
			event.preventDefault(); const button = event.target.querySelector('button[type="submit"]'); button.disabled = true; ui.setText(button, 'در حال ثبت…');
			try {
				const payment = await api.post('payments', { amount: event.target.amount.value }, { idempotencyKey: key, safeRetry: true });
				await api.post('payments/' + encodeURIComponent(payment.payment_reference) + '/confirm', {});
				api.completeOperation(operation); modal.close(); ui.toast('شارژ آزمایشی با موفقیت تکمیل شد.', 'success'); await wallet();
			} catch (error) { ui.toast(ui.errorMessage(error), 'danger'); button.disabled = false; ui.setText(button, 'تکمیل شارژ آزمایشی'); }
		});
	}

	async function confirmPayment(reference, button) {
		const accepted = await ui.confirm({ title: 'تأیید پرداخت آزمایشی', description: 'کیف پول به‌شکل اتمیک و تکرارپذیر شارژ می‌شود.', notice: 'هیچ پرداخت بانکی واقعی انجام نمی‌شود', detail: reference, confirmLabel: 'تأیید و شارژ', accent: true });
		if (!accepted) return;
		button.disabled = true;
		try { await api.post('payments/' + encodeURIComponent(reference) + '/confirm', {}); ui.toast('کیف پول با موفقیت شارژ شد.', 'success'); await wallet(); }
		catch (error) { ui.toast(ui.errorMessage(error), 'danger'); button.disabled = false; }
	}

	async function loadCatalog() {
		if (state.catalog) return state.catalog;
		const regionsResponse = await api.get('catalog/regions');
		const regions = Array.isArray(regionsResponse.data) ? regionsResponse.data : [];
		if (!state.config.region && regions[0]) state.config.region = regions[0].name || regions[0].id || '';
		const [imagesResponse, flavorsResponse, walletData] = await Promise.all([api.get('catalog/images', { query: { region: state.config.region } }), api.get('catalog/flavors', { query: { region: state.config.region } }), api.get('wallet')]);
		state.catalog = { regions, images: imagesResponse.data || [], flavors: flavorsResponse.data || [], mode: imagesResponse.mode || runtime.settings.mode, wallet: walletData };
		return state.catalog;
	}

	async function configurator() {
		setContent(ui.loading('در حال دریافت کاتالوگ سرور ابری...'));
		try {
			const catalog = await loadCatalog();
			const steps = ['منطقه', 'سیستم‌عامل', 'پلن', 'تنظیمات', 'بررسی و سفارش'];
			const stepper = '<ol class="ar-stepper" aria-label="مراحل ساخت سرور">' + steps.map((label, index) => '<li class="ar-stepper__item ' + (index < state.configStep ? 'is-complete' : (index === state.configStep ? 'is-current' : '')) + '"' + (index === state.configStep ? ' aria-current="step"' : '') + '><span class="ar-stepper__number" aria-label="مرحله ' + ui.persianDigits(index + 1) + '">' + (index < state.configStep ? ui.icon('check') : ui.persianDigits(index + 1)) + '</span><span class="ar-stepper__label">' + label + '</span></li>').join('') + '</ol>';
			const panels = [regionPanel(catalog), imagePanel(catalog), flavorPanel(catalog), optionsPanel(), reviewPanel(catalog)];
			const canNext = validateConfigStep(state.configStep);
			const actions = '<div class="ar-wizard-actions ar-config-actions"><button class="ar-button ar-button--secondary" type="button" data-ar-action="config-prev"' + (state.configStep === 0 ? ' disabled' : '') + '><span data-ar-i18n="action.previousStep">مرحله قبل</span></button>' + (state.configStep < 4 ? '<button class="ar-button" type="button" data-ar-action="config-next"' + (canNext ? '' : ' disabled') + '><span data-ar-i18n="action.continueConfig">ادامه پیکربندی</span></button>' : '<button class="ar-button" type="button" data-ar-action="create-order"' + (state.estimate ? '' : ' disabled') + '>' + ui.icon('server') + 'ثبت سفارش و ساخت سرور</button>') + '</div>';
			setContent(pageHead('<span class="ar-env ar-env--' + ui.escape(catalog.mode) + '"><span></span>' + modeLabel(catalog.mode) + '</span>') + '<div class="ar-configurator"><section class="ar-card ar-config-main">' + stepper + '<div class="ar-config-panel">' + panels[state.configStep] + '</div>' + actions + '</section>' + summaryPanel(catalog) + '</div>');
			if (state.configStep === 4 && state.config.flavor && !state.estimate) fetchEstimate();
		} catch (error) { setContent(pageHead() + ui.error(error, 'retry-route')); }
	}

	function optionCard(type, key, titleText, details, selected) {
		const icons = { region: 'region', image: 'image', flavor: 'cpu' };
		return '<button class="ar-option-card' + (selected ? ' is-selected' : '') + '" type="button" data-config-type="' + type + '" data-config-key="' + ui.escape(key) + '" aria-pressed="' + String(selected) + '"><span class="ar-option-card__icon">' + ui.icon(icons[type] || 'server') + '</span><span class="ar-option-card__body"><strong>' + ui.escape(titleText) + '</strong>' + details.filter(Boolean).map((detail) => '<small dir="auto">' + ui.escape(detail) + '</small>').join('') + '</span>' + (selected ? '<span class="ar-option-card__selected">' + ui.icon('check') + '<span>انتخاب‌شده</span></span>' : '') + '</button>';
	}

	function regionPanel(catalog) { return '<div class="ar-card-head"><div><h2>منطقه سرویس</h2><p>منطقه از کاتالوگ سامانه دریافت شده است.</p></div></div><div class="ar-option-grid">' + catalog.regions.map((region) => { const key = region.name || region.id; return optionCard('region', key, key, [region.status ? ui.statusLabel(region.status) : ''], state.config.region === key); }).join('') + '</div>'; }
	function imagePanel(catalog) { return '<div class="ar-card-head"><div><h2>تصویر و سیستم‌عامل</h2><p>فقط تصویرهای موجود در کاتالوگ نمایش داده می‌شوند.</p></div></div><div class="ar-option-grid">' + catalog.images.map((image) => optionCard('image', image.id, image.name || image.id, [image.os || '', image.id], state.config.image && state.config.image.id === image.id)).join('') + '</div>'; }
	function flavorPanel(catalog) { return '<div class="ar-card-head"><div><h2>پلن پردازشی</h2><p>قیمت اولیه از کاتالوگ است؛ مبلغ نهایی تنها از برآورد سمت سرور می‌آید.</p></div></div><div class="ar-option-grid">' + catalog.flavors.map((flavor) => optionCard('flavor', flavor.id, flavor.name || flavor.id, [ui.decimal(flavor.cpuCount) + ' vCPU · ' + ui.decimal(flavor.memoryMegaBytes) + ' مگابایت رم', ui.decimal(flavor.diskGigaBytes) + ' گیگابایت دیسک', ui.decimal(flavor.pricePerHour) + ' در ساعت'], state.config.flavor && state.config.flavor.id === flavor.id)).join('') + '</div>'; }
	function optionsPanel() { return '<div class="ar-card-head"><div><h2>تنظیمات پشتیبانی‌شده</h2><p>نام و حجم دیسک ریشه مطابق محدودیت سفارش است.</p></div></div><div class="ar-form-grid"><div class="ar-field"><label for="ar-server-name">نام سرور</label><input id="ar-server-name" type="text" maxlength="100" dir="ltr" value="' + ui.escape(state.config.name) + '" data-config-input="name" placeholder="my-cloud-server" required></div><div class="ar-field"><label for="ar-root-volume">حجم دیسک ریشه (گیگابایت)</label><input id="ar-root-volume" type="number" min="1" max="10000" value="' + ui.escape(state.config.rootVolumeSizeGigaBytes) + '" data-config-input="rootVolumeSizeGigaBytes"></div></div><div class="ar-grid ar-grid--2" style="margin-top:20px"><label class="ar-check"><input type="checkbox" data-config-option="enableBackup"' + (state.config.enableBackup ? ' checked' : '') + '><span>فعال‌سازی پشتیبان‌گیری</span></label><label class="ar-check"><input type="checkbox" data-config-option="enableFailOver"' + (state.config.enableFailOver ? ' checked' : '') + '><span>فعال‌سازی جایگزینی خودکار</span></label><label class="ar-check"><input type="checkbox" data-config-option="enableIpv4"' + (state.config.enableIpv4 ? ' checked' : '') + '><span>IPv4 در صورت پشتیبانی</span></label><label class="ar-check"><input type="checkbox" data-config-option="enableIpv6"' + (state.config.enableIpv6 ? ' checked' : '') + '><span>IPv6 در صورت پشتیبانی</span></label></div>'; }
	function reviewPanel(catalog) { return '<div class="ar-card-head"><div><h2>بررسی و برآورد سمت سرور</h2><p>برآورد برای ۲۴ ساعت استفاده درخواست می‌شود؛ تخمین ماهانه ساخته نمی‌شود.</p></div></div>' + (state.estimate ? '<div class="ar-price-box"><small>برآورد معتبر ۲۴ ساعت</small><strong>' + ui.money(state.estimate.total_charge, state.estimate.currency) + '</strong><span>هزینه پایه ' + ui.money(state.estimate.base_cost, state.estimate.currency) + ' + سهم فروشنده ' + ui.money(state.estimate.reseller_share, state.estimate.currency) + '</span><small>دریافت‌شده در ' + ui.date(state.estimateAt) + ' · حالت ' + modeLabel(state.estimate.mode) + '</small></div>' : ui.loading('در حال دریافت برآورد معتبر...')) + '<div class="ar-alert ar-alert--warning">' + ui.icon('info') + '<div><strong>برداشت پیش‌پرداخت از سمت سرور</strong><p>مبلغ قطعی ۲۴ ساعت نخست هنگام سفارش از کیف پول کسر می‌شود؛ پس از آن پنجره‌های کامل ساعتی با نرخ ذخیره‌شده صورتحساب می‌شوند.</p></div></div><label class="ar-check" style="margin-top:18px"><input type="checkbox" data-terms required><span>شرایط سرویس پیش‌پرداخت و محدودیت‌های سرور ابری را خوانده‌ام.</span></label><div class="ar-alert" style="margin-top:16px">' + ui.icon('wallet') + '<div><strong>موجودی فعلی</strong><p>' + ui.money(catalog.wallet.balance, catalog.wallet.currency) + ' — کفایت موجودی و برداشت نهایی در backend به‌صورت اتمیک کنترل می‌شود.</p></div></div>'; }

	function summaryPanel(catalog) {
		const insufficient = state.estimate && numeric(catalog.wallet.balance) < numeric(state.estimate.total_charge);
		const disk = Number.isFinite(Number(state.config.rootVolumeSizeGigaBytes)) && Number(state.config.rootVolumeSizeGigaBytes) > 0 ? ui.persianDigits(state.config.rootVolumeSizeGigaBytes) + ' گیگابایت' : '—';
		return '<aside class="ar-card ar-config-summary"><div class="ar-card-head"><div><span class="ar-eyebrow">سفارش سرور ابری</span><h2>خلاصه سفارش</h2></div>' + ui.status('pending') + '</div><dl class="ar-summary-list"><div><dt>موقعیت</dt><dd><code dir="ltr">' + ui.escape(state.config.region || '—') + '</code></dd></div><div><dt>سیستم‌عامل</dt><dd>' + ui.escape(state.config.image && state.config.image.name || '—') + '</dd></div><div><dt>منابع</dt><dd>' + ui.escape(state.config.flavor && state.config.flavor.name || '—') + '</dd></div><div><dt>دیسک ریشه</dt><dd>' + disk + '</dd></div><div><dt>نام سرور</dt><dd><code dir="ltr">' + ui.escape(state.config.name || '—') + '</code></dd></div></dl><div class="ar-summary-wallet"><span class="ar-icon" data-icon="wallet" aria-hidden="true"></span><div><small>موجودی کیف پول</small><strong>' + ui.money(catalog.wallet.balance, catalog.wallet.currency) + '</strong></div></div>' + (state.estimate ? '<div class="ar-price-box"><small>برآورد معتبر ۲۴ ساعت</small><strong>' + ui.money(state.estimate.total_charge, state.estimate.currency) + '</strong></div>' : '') + (insufficient ? '<div class="ar-alert ar-alert--warning">' + ui.icon('warning') + '<div><strong>موجودی برای این برآورد کافی نیست</strong><p>پیش از ثبت سفارش، کیف پول را شارژ کنید.</p></div></div>' : '') + '<small class="ar-config-summary__note">قیمت قطعی از سرویس برآورد سمت سرور دریافت می‌شود.</small></aside>';
	}

	function validateConfigStep(step) {
		if (step === 0) return Boolean(state.config.region); if (step === 1) return Boolean(state.config.image); if (step === 2) return Boolean(state.config.flavor);
		if (step === 3) return Boolean(state.config.name.trim()) && Number(state.config.rootVolumeSizeGigaBytes) >= 1 && Number(state.config.rootVolumeSizeGigaBytes) <= 10000;
		return Boolean(state.estimate);
	}

	async function fetchEstimate() {
		try {
			state.estimate = await api.post('catalog/estimate', { region: state.config.region, flavor_id: state.config.flavor.id, usage_hours: '24.0000' }); state.estimateAt = Date.now(); await configurator();
		} catch (error) { setContent(pageHead() + ui.error(error, 'retry-route')); }
	}

	async function createOrder(button) {
		const terms = content.querySelector('[data-terms]'); if (!terms || !terms.checked) { ui.toast('پیش از سفارش، شرایط سرویس را تأیید کنید.', 'danger'); terms && terms.focus(); return; }
		const accepted = await ui.confirm({ title: 'ساخت سرور ابری در حالت ' + modeLabel(runtime.settings.mode), description: 'سفارش به سامانه معتبر ارسال می‌شود.', notice: runtime.settings.mode === 'mock' ? 'عملیات آزمایشی و بدون هزینه خارجی است' : 'عملیات زنده می‌تواند هزینه‌زا باشد؛ فقط با تأیید آگاهانه ادامه دهید', detail: state.config.region + ' · ' + state.config.flavor.id + ' · ' + state.config.image.id, confirmLabel: runtime.settings.mode === 'mock' ? 'ساخت سرور آزمایشی' : 'تأیید ساخت زنده', accent: true });
		if (!accepted) return;
		const operation = state.orderOperation; const key = api.operationKey(operation); button.disabled = true; ui.setText(button, 'در حال ساخت…');
		const payload = { region: state.config.region, availabilityZone: state.config.availabilityZone, flavorId: state.config.flavor.id, imageId: state.config.image.id, name: state.config.name.trim(), rootVolumeSizeGigaBytes: Number(state.config.rootVolumeSizeGigaBytes), enableBackup: state.config.enableBackup, enableFailOver: state.config.enableFailOver, enableIpv4: state.config.enableIpv4, enableIpv6: state.config.enableIpv6 };
		try {
			const order = await api.post('orders', payload, { idempotencyKey: key, safeRetry: true }); api.completeOperation(operation); state.orderOperation = 'cloud-server-order-' + Date.now(); state.catalog = null; state.estimate = null;
			setContent(pageHead('<button class="ar-button" type="button" data-ar-route="services">مشاهده سرویس</button>') + '<section class="ar-provision-banner">' + ui.icon('server') + '<div><span class="ar-status ar-status--success">سفارش ثبت شد</span><h2>سرور ابری در حالت ' + modeLabel(runtime.settings.mode) + ' آماده شد</h2><p>برداشت پیش‌پرداخت، دریافت شناسه منبع و نگاشت محلی با موفقیت ثبت شد.</p><code dir="ltr">' + ui.escape(order.resource_id || 'در انتظار') + '</code></div></section><article class="ar-card" style="margin-top:16px"><h2>رهگیری فرایند ساخت</h2>' + provisioningTimeline(order.status, order.resource_id) + '</article>');
		} catch (error) {
			if (error && error.code === 'arvan_reseller_insufficient_balance') {
				api.completeOperation(operation);
				state.orderOperation = 'cloud-server-order-' + Date.now();
			}
			ui.toast(ui.errorMessage(error), 'danger'); button.disabled = false; ui.setText(button, 'تأیید و ساخت سرور');
		}
	}

	async function orders() {
		setContent(ui.loading());
		try {
			const ordersData = await api.get('orders', { query: { limit: 100 } });
			const rows = ordersData.map((order) => ['<code dir="ltr">' + ui.escape(order.order_reference) + '</code>', '<strong>' + ui.escape(order.configuration && order.configuration.name || 'سرور ابری') + '</strong><br><small><code dir="ltr">' + ui.escape(order.configuration && order.configuration.flavorId || '—') + '</code></small>', ui.status(order.status), order.quote && order.quote.total_charge ? ui.money(order.quote.total_charge, order.quote.currency) : '—', order.payment && order.payment.status ? ui.status(order.payment.status) : '—', order.recovery_required ? ui.status('failed').replace('ناموفق', 'نیازمند بازیابی') : (order.failure_code ? '<code dir="ltr">' + ui.escape(order.failure_code) + '</code>' : '<code dir="ltr">' + ui.escape(order.resource_id || '—') + '</code>'), ui.date(order.created_at)]);
			setContent(pageHead('<button class="ar-button" type="button" data-ar-route="create-server">سفارش جدید</button>') + '<article class="ar-card ar-card--flush">' + table(['مرجع سفارش', 'پیکربندی', 'وضعیت', 'برآورد ۲۴ساعته', 'پرداخت سفارش', 'منبع/بازیابی', 'ثبت'], rows, 'سفارشی ثبت نشده است') + '</article>');
			const pending = ordersData.some((order) => ['pending', 'provisioning'].includes(order.status)); if (pending) schedulePoll(); else state.pollAttempts = 0;
		} catch (error) { setContent(pageHead() + ui.error(error, 'retry-route')); }
	}

	function schedulePoll() {
		window.clearTimeout(state.pollTimer); if (document.hidden || state.route !== 'orders') return;
		if (state.pollAttempts >= 15) { ui.toast('رهگیری خودکار متوقف شد؛ برای بررسی دوباره تازه‌سازی کنید.', 'danger'); return; }
		state.pollAttempts += 1;
		state.pollTimer = window.setTimeout(() => { if (!document.hidden && state.route === 'orders') orders(); }, Number(runtime.pollInterval || 8000));
	}

	async function billing() {
		setContent(ui.loading());
		try {
			const [usage, invoices] = await Promise.all([api.get('usage', { query: { limit: 100 } }), api.get('invoices', { query: { limit: 100 } })]);
			const total = usage.reduce((sum, row) => sum + numeric(row.total_charge), 0).toFixed(4); const uncovered = usage.reduce((sum, row) => sum + numeric(row.uncovered), 0).toFixed(4);
			const usageRows = usage.map((row) => ['<code dir="ltr">' + ui.escape(row.resource_id) + '</code>', ui.decimal(row.usage_amount) + ' ' + ui.escape(row.unit), ui.money(row.base_cost, row.currency), ui.money(row.reseller_share, row.currency), ui.money(row.charged, row.currency), ui.money(row.uncovered, row.currency), ui.date(row.usage_start) + '<br>' + ui.date(row.usage_end)]);
			const invoiceRows = invoices.map((invoice) => ['<code dir="ltr">' + ui.escape(invoice.invoice_reference) + '</code>', ui.date(invoice.period_start), ui.date(invoice.period_end), ui.money(invoice.total, invoice.currency), ui.status(invoice.status)]);
			setContent(pageHead() + '<section class="ar-grid ar-grid--3">' + metric('هزینه ثبت‌شده', ui.money(total, runtime.settings.currency), 'chart', 'نه برآورد مرورگر') + metric('پوشش‌نیافته', ui.money(uncovered, runtime.settings.currency), 'warning', 'کسری کیف پول') + metric('صورت‌حساب‌ها', ui.persianDigits(invoices.length), 'receipt', 'رکوردهای ثبت‌شده') + '</section><article class="ar-card" style="margin-top:16px"><div class="ar-card-head"><h2>روند مصرف</h2></div>' + ui.lineChart(usage.slice().reverse().map((row) => numeric(row.total_charge))) + '</article><article class="ar-card ar-card--flush" style="margin-top:16px"><div class="ar-card-head" style="padding:20px 20px 0"><h2>جدول دسترس‌پذیر مصرف</h2></div>' + table(['منبع', 'مقدار', 'هزینه پایه', 'سهم فروشنده', 'دریافت‌شده', 'پوشش‌نیافته', 'بازه'], usageRows, 'هنوز مصرفی ثبت نشده است') + '</article><article class="ar-card ar-card--flush" style="margin-top:16px"><div class="ar-card-head" style="padding:20px 20px 0"><h2>صورتحساب‌ها</h2></div>' + table(['مرجع', 'شروع', 'پایان', 'جمع', 'وضعیت'], invoiceRows, 'صورتحسابی ثبت نشده است') + '</article>');
		} catch (error) { setContent(pageHead() + ui.error(error, 'retry-route')); }
	}

	function notificationLabel(type) {
		const labels = { low_balance: 'هشدار موجودی کم', suspension: 'تعلیق سرویس', termination: 'خاتمه سرویس', provisioning_failed: 'خطای ساخت سرویس', payment_completed: 'پرداخت تکمیل شد', payment_failed: 'پرداخت ناموفق' };
		return ui.escape(labels[type] || String(type || 'اعلان سیستمی').replace(/_/g, ' '));
	}

	async function notifications() {
		setContent(ui.loading());
		try {
			const items = await api.get('notifications', { query: { limit: 100 } });
			const unread = items.filter((item) => !item.is_read).length;
			setContent(pageHead('<span class="ar-status ar-status--info">' + ui.persianDigits(unread) + ' خوانده‌نشده</span>') + '<section class="ar-stack">' + (items.length ? items.map((item) => '<article class="ar-card"' + (!item.is_read ? ' style="border-color:var(--ar-color-primary)"' : '') + '><div class="ar-card-head"><div style="display:flex;gap:12px"><span class="ar-metric__icon">' + ui.icon(item.status === 'failed' ? 'warning' : 'bell') + '</span><div><h2>' + notificationLabel(item.type) + '</h2><p>' + ui.date(item.created_at) + '</p></div></div><div style="display:flex;gap:8px;align-items:center">' + (!item.is_read ? '<button class="ar-button ar-button--secondary ar-button--small" type="button" data-notification-read="' + ui.escape(item.id) + '">خواندم</button>' : '<span class="ar-status ar-status--success">خوانده‌شده</span>') + ui.status(item.status) + '</div></div><dl class="ar-summary-list"><div><dt>کانال</dt><dd>' + ui.escape(item.channel) + '</dd></div><div><dt>ارسال</dt><dd>' + ui.date(item.sent_at) + '</dd></div>' + (item.read_at ? '<div><dt>خوانده‌شدن</dt><dd>' + ui.date(item.read_at) + '</dd></div>' : '') + (item.error_code ? '<div><dt>کد امن</dt><dd><code dir="ltr">' + ui.escape(item.error_code) + '</code></dd></div>' : '') + '</dl></article>').join('') : ui.empty('اعلانی وجود ندارد', 'هشدارهای صورتحساب و چرخه سرویس اینجا ثبت می‌شوند.')) + '</section>');
		} catch (error) { setContent(pageHead() + ui.error(error, 'retry-route')); }
	}

	async function go(route, resourceId) {
		closeAccount(false);
		window.clearTimeout(state.pollTimer); state.route = route; state.resourceId = resourceId || state.resourceId; app.dataset.arRoute = route; title();
		app.querySelectorAll('[data-ar-route]').forEach((node) => { const active = node.dataset.arRoute === route; node.classList.toggle('is-active', active); if (active) node.setAttribute('aria-current', 'page'); else node.removeAttribute('aria-current'); });
		ui.closeSidebar(app, false);
		const handlers = { dashboard, services, 'create-server': configurator, wallet, billing, orders, notifications, resource: resourceDetail };
		await (handlers[route] || dashboard)(); content.focus({ preventScroll: true });
	}

	content.addEventListener('input', (event) => {
		if (event.target.matches('[data-config-input]')) { const key = event.target.dataset.configInput; state.config[key] = key === 'rootVolumeSizeGigaBytes' ? Number(event.target.value) : event.target.value; state.estimate = null; state.orderOperation = 'cloud-server-order-' + Date.now(); const next = content.querySelector('[data-ar-action="config-next"]'); if (next) next.disabled = !validateConfigStep(state.configStep); }
	});
	content.addEventListener('change', (event) => {
		if (event.target.matches('[data-config-option]')) { state.config[event.target.dataset.configOption] = event.target.checked; state.estimate = null; state.orderOperation = 'cloud-server-order-' + Date.now(); }
	});
	app.addEventListener('click', async (event) => {
		const accountAction = event.target.closest('[data-ar-action="toggle-account"], [data-ar-action="close-account"]');
		if (accountAction) {
			event.preventDefault();
			if (accountAction.dataset.arAction === 'toggle-account' && !state.accountOpen) await openAccount();
			else closeAccount();
			return;
		}
		const routeButton = event.target.closest('button[data-ar-route], a[data-ar-route]'); if (routeButton) { event.preventDefault(); await go(routeButton.dataset.arRoute); return; }
		const resource = event.target.closest('[data-resource-id]'); if (resource) { await go('resource', resource.dataset.resourceId); return; }
		const copy = event.target.closest('[data-copy]'); if (copy) { try { await navigator.clipboard.writeText(copy.dataset.copy); ui.toast('شناسه کپی شد.', 'success'); } catch (error) { ui.toast('کپی خودکار ممکن نشد.', 'danger'); } return; }
		const payment = event.target.closest('[data-confirm-payment]'); if (payment) { await confirmPayment(payment.dataset.confirmPayment, payment); return; }
		const notification = event.target.closest('[data-notification-read]');
		if (notification) { notification.disabled = true; try { await api.post('notifications/' + encodeURIComponent(notification.dataset.notificationRead) + '/read', {}); await notifications(); } catch (error) { notification.disabled = false; ui.toast(ui.errorMessage(error), 'danger'); } return; }
		const action = event.target.closest('[data-ar-action]');
		if (action) {
			event.preventDefault();
			if (action.dataset.arAction === 'retry-route') { await go(state.route, state.resourceId); return; }
			if (action.dataset.arAction === 'open-topup') { openTopup(); return; }
			if (action.dataset.arAction === 'config-next') {
				if (!validateConfigStep(state.configStep)) { ui.toast('برای ادامه، گزینه‌های لازم این مرحله را کامل کنید.', 'danger'); return; }
				state.configStep = Math.min(4, state.configStep + 1); await configurator(); return;
			}
			if (action.dataset.arAction === 'config-prev') { state.configStep = Math.max(0, state.configStep - 1); await configurator(); return; }
			if (action.dataset.arAction === 'create-order') { await createOrder(action); return; }
		}
		const selection = event.target.closest('[data-config-type]');
		if (selection) {
			const catalog = state.catalog; const type = selection.dataset.configType; const key = selection.dataset.configKey;
			if (type === 'region') { state.config.region = key; state.config.image = null; state.config.flavor = null; state.catalog = null; state.estimate = null; state.orderOperation = 'cloud-server-order-' + Date.now(); await configurator(); return; }
			if (type === 'image') state.config.image = catalog.images.find((item) => String(item.id) === key) || null;
			if (type === 'flavor') state.config.flavor = catalog.flavors.find((item) => String(item.id) === key) || null;
			state.estimate = null; state.orderOperation = 'cloud-server-order-' + Date.now(); await configurator(); return;
		}
	});

	document.addEventListener('visibilitychange', () => { if (document.hidden) window.clearTimeout(state.pollTimer); else if (state.route === 'orders') schedulePoll(); });
	document.addEventListener('arvan:language-change', () => {
		title();
		const meta = currentMeta();
		const head = content.querySelector('.ar-page-head');
		if (head) {
			const heading = head.querySelector('h1'); const description = head.querySelector('p');
			if (heading) heading.textContent = meta[0];
			if (description) description.textContent = meta[1];
			ui.translateDom(head);
		}
	});
	document.addEventListener('keydown', (event) => {
		if (!state.accountOpen) return;
		if (event.key === 'Escape') { closeAccount(); return; }
		if (event.key !== 'Tab') return;
		const drawer = app.querySelector('[data-ar-account-drawer]');
		const focusable = Array.from(drawer.querySelectorAll('button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'));
		if (!focusable.length) { event.preventDefault(); drawer.focus(); return; }
		const first = focusable[0]; const last = focusable[focusable.length - 1];
		if (!drawer.contains(document.activeElement)) { event.preventDefault(); first.focus(); return; }
		if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
		else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
	});
	const requestedRoute = new URLSearchParams(window.location.search).get('ar-route');
	const publicRoutes = ['dashboard', 'services', 'create-server', 'wallet', 'billing', 'orders', 'notifications'];
	go(publicRoutes.includes(requestedRoute) ? requestedRoute : 'dashboard');
}());
