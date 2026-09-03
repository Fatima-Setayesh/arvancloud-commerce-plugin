(function () {
	'use strict';

	const app = document.querySelector('.arvan-reseller-admin');
	if (!app) return;

	const content = app.querySelector('#ar-admin-content');
	const page = app.dataset.arPage || 'dashboard';
	const api = window.ArvanResellerAPI;
	const ui = window.ArvanUI;
	const runtime = window.ArvanResellerRuntime;
	const state = { data: {}, wizardStep: 0, search: '', filter: '', collectionPage: 1, focusCollection: false, focusSearch: false };

	const pageMeta = {
		dashboard: ['داشبورد عملیات', 'نمای یکپارچه از مشتریان، کیف پول‌ها، سرورها و سلامت سامانه'],
		setup: ['راه‌اندازی فروشنده', 'پیکربندی گام‌به‌گام تجربه فروش و اتصال امن'],
		customers: ['مشتریان', 'مالکیت سرویس‌ها و وضعیت کیف پول مشتریان'],
		payments: ['پرداخت‌ها', 'چرخه پرداخت آزمایشی و بازپرداخت کنترل‌شده'],
		orders: ['سفارش‌ها', 'رهگیری ساخت سرور و نگاشت شناسه منبع'],
		resources: ['سرورهای ابری', 'وضعیت عملیاتی، همگام‌سازی و چرخه عمر منابع'],
		usage: ['مصرف و صورتحساب', 'اثر مصرف ثبت‌شده بر کیف پول و سهم فروشنده'],
		settlements: ['تسویه داخلی / شبیه‌سازی‌شده', 'گزارش حسابداری داخلی؛ بدون ادعای پرداخت خارجی'],
		health: ['سلامت سامانه', 'Cron، همگام‌سازی مصرف و بازیابی سفارش‌ها'],
		audit: ['گزارش ممیزی', 'رویدادهای مدیریتی و امنیتی بدون داده حساس'],
		settings: ['تنظیمات محصول', 'سازمان، اتصال، قیمت‌گذاری و سیاست‌های چرخه عمر']
	};

	function setContent(html) {
		content.innerHTML = html;
		ui.mountIcons(content);
		content.setAttribute('aria-busy', String(Boolean(content.querySelector('.ar-loading-state'))));
		window.requestAnimationFrame(() => {
			if (state.focusCollection) {
				const status = content.querySelector('[data-ar-pagination-status]');
				if (status) { status.focus({ preventScroll: true }); state.focusCollection = false; }
			}
			if (state.focusSearch) {
				const search = content.querySelector('[data-ar-search]');
				if (search) { search.focus({ preventScroll: true }); search.setSelectionRange(search.value.length, search.value.length); state.focusSearch = false; }
			}
		});
	}

	function metric(label, value, icon, meta) {
		const missing = value === null || typeof value === 'undefined' || value === '' || (typeof value === 'number' && !Number.isFinite(value)) || /^(?:undefined|null|nan)$/i.test(String(value).trim());
		const displayValue = missing ? '—' : value;
		return '<article class="ar-card ar-metric"><span class="ar-metric__icon">' + ui.icon(icon) + '</span><span class="ar-metric__label">' + ui.escape(label) + '</span><strong class="ar-metric__value">' + displayValue + '</strong><span class="ar-metric__meta">' + ui.escape(meta || 'براساس داده فعلی') + '</span></article>';
	}

	function table(headers, rows, emptyText) {
		if (!rows.length) return ui.empty(emptyText || 'داده‌ای ثبت نشده است', 'با شروع فعالیت، رکوردها در این بخش نمایش داده می‌شوند.');
		return '<div class="ar-table-wrap"><table class="ar-table ar-responsive-table"><thead><tr>' + headers.map((header) => '<th scope="col">' + ui.escape(header) + '</th>').join('') + '</tr></thead><tbody>' + rows.map((row) => '<tr>' + row.map((cell, index) => '<td data-label="' + ui.escape(headers[index]) + '">' + cell + '</td>').join('') + '</tr>').join('') + '</tbody></table></div>';
	}

	function pagination(collection) {
		return '<nav class="ar-pagination" aria-label="صفحه‌بندی فهرست"><button class="ar-button ar-button--secondary ar-button--small" type="button" data-ar-page="' + (collection.page - 1) + '"' + (collection.page <= 1 ? ' disabled' : '') + '>قبلی</button><span data-ar-pagination-status tabindex="-1">صفحه <strong>' + ui.persianDigits(collection.page) + '</strong></span><button class="ar-button ar-button--secondary ar-button--small" type="button" data-ar-page="' + (collection.page + 1) + '"' + (!collection.hasMore ? ' disabled' : '') + '>بعدی</button></nav>';
	}

	function collectionTable(headers, collection, rows, emptyText) {
		return table(headers, rows, emptyText) + pagination(collection);
	}

	function collectionQuery(extra) {
		return Object.assign({ limit: 25, page: state.collectionPage, search: state.search }, extra || {});
	}

	function value(result, fallback) {
		return result && result.status === 'fulfilled' ? result.value : fallback;
	}

	function chartNumber(decimal) {
		const number = Number(String(decimal || '0').replace(/,/g, ''));
		return Number.isFinite(number) ? number : 0;
	}

	function minorToDecimal(minor) {
		return ui.exactMoney.fromMinor(minor);
	}

	function currencyAmount(rows, currency) {
		const match = (rows || []).find((row) => row.currency === currency);
		return match ? match.amount : '0.0000';
	}

	function moneyAtOrBelow(value, threshold) {
		const comparison = ui.exactMoney.compare(value, threshold);
		return comparison !== null && comparison <= 0;
	}

	function toolbar(options) {
		return '<div class="ar-filter-bar"><label class="ar-field"><span class="screen-reader-text">جست‌وجو</span><input class="ar-input" type="search" data-ar-search placeholder="' + ui.escape(options.placeholder || 'جست‌وجو…') + '" value="' + ui.escape(state.search) + '" /></label>' + (options.filter || '') + '<button class="ar-button ar-button--secondary" type="button" data-ar-action="refresh-page">' + ui.icon('refresh') + 'تازه‌سازی</button></div>';
	}

	async function renderDashboard() {
		setContent(ui.loading('در حال تجمیع شاخص‌های عملیاتی…'));
		const results = await Promise.allSettled([
			api.get('admin/overview'), api.get('admin/orders', { query: { limit: 6 } }), api.get('admin/usage', { query: { limit: 20 } }),
			api.get('admin/audit-logs', { query: { limit: 8 } }), api.get('admin/settlements', { query: { limit: 10 } })
		]);
		const overview = value(results[0], null); const orders = value(results[1], []); const usage = value(results[2], []);
		const audit = value(results[3], []); const settlements = value(results[4], []);
		const partial = results.some((result) => result.status === 'rejected');
		const active = overview ? overview.active_resource_count : 0; const suspended = overview ? overview.suspended_resource_count : 0;
		const resourceErrors = overview ? overview.failed_resource_count : 0; const resourceTotal = active + suspended + resourceErrors || 1;
		const activeEnd = (active / resourceTotal * 100).toFixed(2);
		const suspendedEnd = ((active + suspended) / resourceTotal * 100).toFixed(2);
		const errorEnd = ((active + suspended + resourceErrors) / resourceTotal * 100).toFixed(2);
		const walletTotal = overview ? currencyAmount(overview.wallet_balances, runtime.settings.currency) : null;
		const currentCost = overview ? currencyAmount(overview.current_billed_amounts, runtime.settings.currency) : null;
		const lineValues = usage.slice().reverse().map((row) => chartNumber(row.total_charge));
		const latestOrders = orders.slice(0, 6).map((order) => [
			'<code dir="ltr">' + ui.escape(order.order_reference) + '</code>', ui.escape('#' + order.customer_id),
			ui.status(order.status), '<code dir="ltr">' + ui.escape(order.resource_id || '—') + '</code>', ui.date(order.created_at)
		]);
		const activity = audit.map((row) => '<li><span class="ar-timeline__marker">' + ui.icon('check') + '</span><div><strong>' + ui.escape(row.event_type) + '</strong><small>' + ui.escape(row.object_type + ' · ' + (row.object_id || 'system')) + ' — ' + ui.date(row.created_at) + '</small></div></li>').join('');
		const currencies = overview ? Array.from(new Set([...(overview.wallet_balances || []).map((row) => row.currency), ...(overview.current_billed_amounts || []).map((row) => row.currency)])).sort() : [];
		const exactRows = currencies.map((currency) => [ui.escape(currency), ui.money(currencyAmount(overview.wallet_balances, currency), currency), ui.money(currencyAmount(overview.current_billed_amounts, currency), currency)]);
		const cron = overview && overview.cron || {};
		setContent(
			ui.pageHead(pageMeta.dashboard[0], pageMeta.dashboard[1], '<button class="ar-button ar-button--secondary" type="button" data-ar-action="refresh-page">' + ui.icon('refresh') + 'تازه‌سازی</button><a class="ar-button" href="' + ui.escape(runtime.adminUrl.replace('page=arvan-reseller', 'page=arvan-reseller-setup')) + '">' + ui.icon('spark') + 'راه‌اندازی</a>') +
			(partial ? '<div class="ar-alert ar-alert--warning">' + ui.icon('warning') + '<div><strong>نمایش داده جزئی</strong><p>بعضی شاخص‌ها دریافت نشدند؛ بخش‌های در دسترس با داده واقعی نمایش داده شده‌اند.</p></div></div>' : '') +
			'<section class="ar-grid ar-grid--metrics">' + metric('مشتریان تجاری', overview ? ui.persianDigits(overview.commerce_customer_count) : '—', 'users', 'مشارکت‌کنندگان کیف پول، پرداخت، سفارش یا سرویس') + metric('سرورهای فعال', overview ? ui.persianDigits(active) : '—', 'server', overview ? ui.persianDigits(suspended) + ' تعلیق‌شده · ' + ui.persianDigits(resourceErrors) + ' خطا' : 'داده در دسترس نیست') + metric('مجموع کیف پول', ui.money(walletTotal, runtime.settings.currency), 'wallet', 'جمع دقیق ارز انتخاب‌شده') + metric('مبلغ صورتحساب‌شده', ui.money(currentCost, runtime.settings.currency), 'chart', 'جمع دقیق رکوردهای مصرف') + metric('پرداخت‌های در انتظار', overview ? ui.persianDigits(overview.pending_payment_count) : '—', 'receipt') + metric('هشدارهای تطبیق', overview ? ui.persianDigits(overview.reconciliation_warning_count) : '—', 'warning') + '</section>' +
			'<article class="ar-card ar-card--flush" style="margin-top:16px"><div class="ar-card-head" style="padding:20px 20px 0"><div><h2>جمع‌های دقیق به تفکیک ارز</h2><p>مقادیر از فیلدهای صحیح minor-unit سمت سرور محاسبه شده‌اند.</p></div></div>' + table(['ارز', 'موجودی مجازی', 'مبلغ صورتحساب‌شده'], exactRows, 'هنوز مبلغی ثبت نشده است') + '</article>' +
			'<section class="ar-layout-main"><div class="ar-stack"><article class="ar-card"><div class="ar-card-head"><div><h2>روند هزینه ثبت‌شده</h2><p>نمودار فقط شکل ۲۰ رکورد اخیر است؛ جمع‌های دقیق در جدول بالا قرار دارند.</p></div><span class="ar-status ar-status--info">' + ui.persianDigits(usage.length) + ' رکورد اخیر</span></div>' + ui.lineChart(lineValues) + '</article><article class="ar-card ar-card--flush"><div class="ar-card-head" style="padding:20px 20px 0"><h2>سفارش‌های اخیر</h2><a href="' + ui.escape(runtime.adminUrl.replace('page=arvan-reseller', 'page=arvan-reseller-orders')) + '">مشاهده همه</a></div>' + table(['شناسه سفارش', 'مشتری', 'وضعیت', 'شناسه منبع', 'زمان'], latestOrders, 'هنوز سفارشی ثبت نشده است') + '</article></div>' +
			'<div class="ar-stack"><article class="ar-card"><div class="ar-card-head"><h2>توزیع وضعیت سرویس</h2></div><div class="ar-donut-wrap"><div class="ar-donut" style="--ar-donut-active:' + activeEnd + '%;--ar-donut-suspended:' + suspendedEnd + '%;--ar-donut-error:' + errorEnd + '%"><span class="ar-donut__value"><strong>' + ui.persianDigits(active + suspended + resourceErrors) + '</strong>وضعیت‌های عملیاتی</span></div><div class="ar-legend"><span><i style="--legend-color:var(--ar-color-success)"></i>فعال ' + ui.persianDigits(active) + '</span><span><i style="--legend-color:var(--ar-color-warning)"></i>تعلیق ' + ui.persianDigits(suspended) + '</span><span><i style="--legend-color:var(--ar-color-danger)"></i>خطا ' + ui.persianDigits(resourceErrors) + '</span></div></div></article>' +
			'<article class="ar-card"><div class="ar-card-head"><h2>سلامت عملیات</h2>' + ui.status(cron.status || 'never_run') + '</div><div class="ar-ops-strip"><div class="ar-ops-item">' + ui.icon('chart') + '<div><strong>' + ui.persianDigits(cron.processed || 0) + '</strong><small>پردازش Cron</small></div></div><div class="ar-ops-item">' + ui.icon('warning') + '<div><strong>' + ui.persianDigits(cron.failed || 0) + '</strong><small>خطای اخیر</small></div></div><div class="ar-ops-item">' + ui.icon('receipt') + '<div><strong>' + ui.persianDigits(settlements.length) + '</strong><small>تسویه‌های اخیر</small></div></div></div>' + (overview && overview.reconciliation_warning_count ? '<div class="ar-alert ar-alert--warning" style="margin-top:16px">' + ui.icon('warning') + '<div><strong>' + ui.persianDigits(overview.reconciliation_warning_count) + ' هشدار تطبیق</strong><p>سفارش مبهم، خطای همگام‌سازی یا مصرف پوشش‌نیافته را بررسی کنید.</p></div></div>' : '') + '</article>' +
			'<article class="ar-card"><div class="ar-card-head"><h2>فعالیت اخیر</h2></div>' + (activity ? '<ol class="ar-timeline">' + activity + '</ol>' : ui.empty('رویدادی ثبت نشده است', 'فعالیت‌های امن مدیریتی اینجا دیده می‌شوند.')) + '</article></div></section>'
		);
	}

	async function renderCustomers() {
		setContent(ui.loading());
		try {
			const collection = await api.getCollection('admin/customers', { query: collectionQuery() });
			const rows = collection.items.map((customer) => {
				const wallet = customer.wallet;
				const low = wallet && moneyAtOrBelow(wallet.balance, wallet.threshold);
				return ['<strong>' + ui.escape(customer.display_name) + '</strong><br><small>' + ui.escape(customer.email) + '</small>', '<code dir="ltr">#' + ui.escape(customer.id) + '</code>', wallet ? ui.money(wallet.balance, wallet.currency) : '—', ui.persianDigits(customer.resource_count), low ? ui.status('suspended').replace('تعلیق‌شده', 'موجودی کم') : ui.status(wallet ? wallet.status : 'pending'), ui.date(customer.registered_at)];
			});
			setContent(ui.pageHead(pageMeta.customers[0], pageMeta.customers[1]) + '<article class="ar-card">' + toolbar({ placeholder: 'نام، ایمیل یا شناسه مشتری' }) + collectionTable(['مشتری', 'شناسه', 'کیف پول', 'سرویس‌ها', 'وضعیت', 'عضویت'], collection, rows, 'مشتری‌ای یافت نشد') + '</article>');
		} catch (error) { setContent(ui.pageHead(pageMeta.customers[0], pageMeta.customers[1]) + ui.error(error, 'refresh-page')); }
	}

	async function renderPayments() {
		setContent(ui.loading());
		try {
			const query = collectionQuery(); if (state.filter) query.status = state.filter;
			const collection = await api.getCollection('admin/payments', { query });
			const filter = '<select class="ar-input" data-ar-filter aria-label="فیلتر وضعیت"><option value="">همه وضعیت‌ها</option>' + ['pending', 'completed', 'failed', 'cancelled', 'expired', 'refunded'].map((status) => '<option value="' + status + '"' + (state.filter === status ? ' selected' : '') + '>' + ui.status(status).replace(/<[^>]+>/g, '') + '</option>').join('') + '</select>';
			const rows = collection.items.map((payment) => ['<code dir="ltr">' + ui.escape(payment.payment_reference) + '</code>', '<code dir="ltr">#' + ui.escape(payment.id) + '</code>', ui.money(payment.amount, payment.currency), ui.status(payment.status), ui.escape(payment.provider === 'mock' ? 'آزمایشی' : payment.provider), ui.date(payment.created_at), payment.status === 'completed' ? '<button class="ar-button ar-button--secondary ar-button--small" type="button" data-ar-refund="' + ui.escape(payment.payment_reference) + '">بازپرداخت</button>' : '—']);
			setContent(ui.pageHead(pageMeta.payments[0], pageMeta.payments[1], '<span class="ar-env ar-env--' + ui.escape(runtime.settings.mode) + '"><span></span>' + (runtime.settings.mode === 'mock' ? 'آزمایشی' : 'زنده') + '</span>') + '<article class="ar-card">' + toolbar({ placeholder: 'مرجع پرداخت یا مشتری', filter }) + collectionTable(['مرجع', 'شناسه', 'مبلغ', 'وضعیت', 'درگاه', 'زمان', 'عملیات'], collection, rows, 'پرداختی یافت نشد') + '</article>');
		} catch (error) { setContent(ui.pageHead(pageMeta.payments[0], pageMeta.payments[1]) + ui.error(error, 'refresh-page')); }
	}

	async function renderOrders() {
		setContent(ui.loading());
		try {
			const collection = await api.getCollection('admin/orders', { query: collectionQuery() });
			const rows = collection.items.map((order) => {
				const config = order.configuration || {};
				const recovery = order.failure_code === 'manual_review_missing_resource_id' ? '<span class="ar-status ar-status--warning">نیازمند بررسی انسانی</span><br><code dir="ltr">' + ui.escape(order.failure_code) + '</code>' : (order.recovery_required ? ui.status('failed').replace('ناموفق', 'نیازمند بازیابی') : (order.failure_code ? '<code dir="ltr">' + ui.escape(order.failure_code) + '</code>' : '—'));
				return ['<code dir="ltr">' + ui.escape(order.order_reference) + '</code>', '<code dir="ltr">#' + ui.escape(order.customer_id) + '</code>', '<strong>' + ui.escape(config.name || 'سرور ابری') + '</strong><br><small><code dir="ltr">' + ui.escape(config.flavorId || '—') + '</code></small>', ui.status(order.status), '<code dir="ltr">' + ui.escape(order.resource_id || '—') + '</code>', ui.escape(order.payment && order.payment.status || '—'), recovery, ui.date(order.created_at)];
			});
			setContent(ui.pageHead(pageMeta.orders[0], pageMeta.orders[1]) + '<article class="ar-card">' + toolbar({ placeholder: 'مرجع، مشتری یا شناسه منبع' }) + collectionTable(['سفارش', 'مشتری', 'پیکربندی', 'وضعیت', 'شناسه منبع', 'پرداخت سفارش', 'بازیابی/خطا', 'ثبت'], collection, rows, 'سفارشی یافت نشد') + '</article>');
		} catch (error) { setContent(ui.pageHead(pageMeta.orders[0], pageMeta.orders[1]) + ui.error(error, 'refresh-page')); }
	}

	async function renderResources() {
		setContent(ui.loading());
		try {
			const collection = await api.getCollection('admin/resources', { query: collectionQuery() });
			const rows = collection.items.map((resource) => ['<strong>' + ui.escape(resource.name || 'سرور ابری') + '</strong><br><code dir="ltr">' + ui.escape(resource.resource_id) + '</code>', '<code dir="ltr">#' + ui.escape(resource.customer_id) + '</code>', '<code dir="ltr">#' + ui.escape(resource.order_id || '—') + '</code>', ui.escape(resource.region), ui.status(resource.status), ui.status(resource.remote_status), ui.money(resource.hourly_price, resource.currency), ui.date(resource.last_synced_at)]);
			setContent(ui.pageHead(pageMeta.resources[0], pageMeta.resources[1], '<button class="ar-button" type="button" data-ar-action="run-reconciliation">' + ui.icon('refresh') + 'بازیابی نگاشت‌ها</button>') + '<article class="ar-card">' + toolbar({ placeholder: 'شناسه منبع، مشتری، منطقه یا وضعیت' }) + collectionTable(['سرویس', 'مشتری', 'سفارش', 'منطقه', 'وضعیت محلی', 'وضعیت راه‌دور', 'نرخ ساعتی', 'آخرین همگام‌سازی'], collection, rows, 'سروری یافت نشد') + '</article>');
		} catch (error) { setContent(ui.pageHead(pageMeta.resources[0], pageMeta.resources[1]) + ui.error(error, 'refresh-page')); }
	}

	async function renderUsage() {
		setContent(ui.loading());
		try {
			const collection = await api.getCollection('admin/usage', { query: collectionQuery() });
			const usage = collection.items;
			const total = ui.exactMoney.sum(usage.map((row) => row.total_charge));
			const uncovered = ui.exactMoney.sum(usage.map((row) => row.uncovered));
			const rows = usage.map((row) => ['<code dir="ltr">' + ui.escape(row.resource_id) + '</code>', '<code dir="ltr">#' + ui.escape(row.customer_id) + '</code>', ui.decimal(row.usage_amount) + ' ' + ui.escape(row.unit), ui.money(row.base_cost, row.currency), ui.money(row.reseller_share, row.currency), ui.money(row.charged, row.currency), ui.money(row.uncovered, row.currency), ui.date(row.usage_end)]);
			setContent(ui.pageHead(pageMeta.usage[0], pageMeta.usage[1]) + '<section class="ar-grid ar-grid--metrics">' + metric('هزینه این صفحه', ui.money(total, runtime.settings.currency), 'chart') + metric('پوشش‌نیافته این صفحه', ui.money(uncovered, runtime.settings.currency), 'warning') + metric('رکوردهای این صفحه', ui.persianDigits(usage.length), 'list') + metric('واحد محاسبه', 'ساعت', 'receipt', 'براساس محدودیت رسمی رابط سرویس') + '</section><article class="ar-card" style="margin-top:16px">' + toolbar({ placeholder: 'شناسه منبع یا مشتری' }) + collectionTable(['منبع', 'مشتری', 'مصرف', 'هزینه پایه', 'سهم فروشنده', 'دریافت‌شده', 'پوشش‌نیافته', 'پایان بازه'], collection, rows, 'مصرفی ثبت نشده است') + '</article>');
		} catch (error) { setContent(ui.pageHead(pageMeta.usage[0], pageMeta.usage[1]) + ui.error(error, 'refresh-page')); }
	}

	async function renderSettlements() {
		setContent(ui.loading());
		try {
			const collection = await api.getCollection('admin/settlements', { query: { limit: 25, page: state.collectionPage } });
			const tableRows = collection.items.map((row) => ['<code dir="ltr">' + ui.escape(row.settlement_reference || row.reference) + '</code>', ui.status(row.status), ui.date(row.period_start), ui.date(row.period_end), ui.money(minorToDecimal(row.base_cost_minor), row.currency), ui.money(minorToDecimal(row.customer_charge_minor), row.currency), ui.money(minorToDecimal(row.reseller_share_minor), row.currency)]);
			setContent(ui.pageHead(pageMeta.settlements[0], pageMeta.settlements[1]) + '<div class="ar-alert ar-alert--warning">' + ui.icon('info') + '<div><strong>حسابداری داخلی و شبیه‌سازی‌شده</strong><p>این رکوردها خلاصه حسابداری داخلی‌اند و نشان‌دهنده پرداخت یا تسویه خارجی آروان‌کلاد نیستند.</p></div></div><article class="ar-card" style="margin-top:16px">' + collectionTable(['مرجع', 'وضعیت', 'شروع دوره', 'پایان دوره', 'هزینه پایه', 'دریافت مشتری', 'سهم فروشنده'], collection, tableRows, 'تسویه داخلی ثبت نشده است') + '</article>');
		} catch (error) { setContent(ui.pageHead(pageMeta.settlements[0], pageMeta.settlements[1]) + ui.error(error, 'refresh-page')); }
	}

	async function renderHealth() {
		setContent(ui.loading());
		try {
			const health = await api.get('admin/health'); const cron = health.cron || {};
			const schedules = health.schedules || {};
			setContent(ui.pageHead(pageMeta.health[0], pageMeta.health[1], '<button class="ar-button ar-button--secondary" type="button" data-ar-action="run-reconciliation">' + ui.icon('refresh') + 'اجرای بازیابی</button><button class="ar-button" type="button" data-ar-action="run-cron">' + ui.icon('chart') + 'اجرای Cron</button>') +
			'<section class="ar-grid ar-grid--metrics">' + metric('وضعیت Cron', ui.status(cron.status || 'never_run'), 'shield', cron.last_success_at ? 'آخرین موفق: ' + ui.date(cron.last_success_at) : 'هنوز اجرای موفق ثبت نشده') + metric('پردازش‌شده', ui.persianDigits(cron.processed || 0), 'chart', 'Cursor: ' + ui.persianDigits(cron.cursor || 0)) + metric('ناموفق', ui.persianDigits(cron.failed || 0), 'warning', cron.last_failure_at ? ui.date(cron.last_failure_at) : 'بدون خطای ثبت‌شده') + metric('نسخه پایگاه داده', '<code dir="ltr">' + ui.escape(health.database_version || '—') + '</code>', 'list', 'مهاجرت فعال') + '</section>' +
		'<section class="ar-layout-main"><article class="ar-card"><div class="ar-card-head"><h2>زمان‌بندی‌ها</h2><span class="ar-env ar-env--' + ui.escape(health.mode) + '"><span></span>' + (health.mode === 'live' ? 'زنده' : 'آزمایشی') + '</span></div><dl class="ar-summary-list"><div><dt>همگام‌سازی مصرف</dt><dd>' + (schedules.usage ? ui.date(Number(schedules.usage) * 1000) : 'زمان‌بندی نشده') + '</dd></div><div><dt>بازیابی منابع</dt><dd>' + (schedules.reconciliation ? ui.date(Number(schedules.reconciliation) * 1000) : 'زمان‌بندی نشده') + '</dd></div><div><dt>تسویه داخلی</dt><dd>' + (schedules.settlement ? ui.date(Number(schedules.settlement) * 1000) : 'زمان‌بندی نشده') + '</dd></div></dl></article><article class="ar-card"><div class="ar-card-head"><h2>محدودیت عملیاتی</h2></div><div class="ar-alert ar-alert--warning">' + ui.icon('warning') + '<div><strong>زمان‌بندی وردپرس وابسته به ترافیک است</strong><p>در محیط تولید، اجرای زمان‌بندی‌شده وردپرس باید پایش شود. خاموش‌کردن زمان‌بندی داخلی تنها پس از تأیید زمان‌بند بیرونی امن است.</p></div></div></article></section>');
		} catch (error) { setContent(ui.pageHead(pageMeta.health[0], pageMeta.health[1]) + ui.error(error, 'refresh-page')); }
	}

	async function renderAudit() {
		setContent(ui.loading());
		try {
			const collection = await api.getCollection('admin/audit-logs', { query: collectionQuery() });
			const tableRows = collection.items.map((row) => ['<code dir="ltr">#' + ui.escape(row.id) + '</code>', '<code dir="ltr">' + ui.escape(row.event_type) + '</code>', ui.escape(row.object_type), '<code dir="ltr">' + ui.escape(row.object_id || '—') + '</code>', ui.escape(row.actor_user_id ? '#' + row.actor_user_id : 'system'), '<code dir="ltr">' + ui.escape(row.request_id || '—') + '</code>', ui.date(row.created_at)]);
			setContent(ui.pageHead(pageMeta.audit[0], pageMeta.audit[1]) + '<article class="ar-card">' + toolbar({ placeholder: 'رویداد، هدف، بازیگر یا Request ID' }) + collectionTable(['ردیف', 'رویداد', 'نوع هدف', 'هدف', 'بازیگر', 'Request ID', 'زمان'], collection, tableRows, 'رویدادی یافت نشد') + '</article>');
		} catch (error) { setContent(ui.pageHead(pageMeta.audit[0], pageMeta.audit[1]) + ui.error(error, 'refresh-page')); }
	}

	function settingField(id, label, value, type, help, attrs) {
		return '<div class="ar-field"><label for="ar-setting-' + id + '">' + ui.escape(label) + '</label>' + (type === 'textarea' ? '<textarea id="ar-setting-' + id + '" name="' + id + '" ' + (attrs || '') + '>' + ui.escape(value || '') + '</textarea>' : '<input id="ar-setting-' + id + '" name="' + id + '" type="' + (type || 'text') + '" value="' + ui.escape(value === null || typeof value === 'undefined' ? '' : value) + '" ' + (attrs || '') + ' />') + (help ? '<small>' + ui.escape(help) + '</small>' : '') + '</div>';
	}

	function editableDecimal(value) {
		const decimal = String(value === null || typeof value === 'undefined' ? '' : value);
		if (!/^-?\d+(?:\.\d+)?$/.test(decimal)) return decimal;
		return decimal.replace(/(\.\d*?[1-9])0+$/, '$1').replace(/\.0+$/, '');
	}

	async function renderSettings() {
		setContent(ui.loading('در حال دریافت تنظیمات امن…'));
		try {
			const settings = state.settingsDraft || state.data.settings || await api.get('admin/settings');
			state.data.settings = settings;
			const steps = ['شروع', 'سازمان', 'نشان و تماس', 'حالت اجرا', 'اتصال API', 'سهم فروشنده', 'کیف پول', 'سیاست سرویس', 'فروشگاه', 'آمادگی'];
			const stepNav = steps.map((label, index) => '<button class="ar-wizard-step' + (index === state.wizardStep ? ' is-active' : '') + (index < state.wizardStep ? ' is-complete' : '') + '" type="button" data-wizard-step="' + index + '"><span>' + ui.persianDigits(index + 1) + '</span>' + ui.escape(label) + '</button>').join('');
			const panels = [
				'<section><span class="ar-eyebrow">تجارت ابری آروان</span><h2>کنسول فروش سرور ابری را آماده کنید</h2><p>این راهنما تنظیمات واقعی سمت سرور را ذخیره می‌کند. ابتدا حالت آزمایشی را کامل کنید و حالت زنده را تنها پس از ثبت امن کاربر ماشینی فعال کنید.</p><div class="ar-alert ar-alert--success">' + ui.icon('shield') + '<div><strong>منطق معتبر سرویس از رابط جدا است</strong><p>کیف پول، دفترکل، سفارش، صورتحساب و امنیت بدون بازنویسی باقی می‌مانند.</p></div></div></section>',
				'<section><h2>سازمان فروشنده</h2><p>نام و معرفی‌ای که در تجربه مشتری دیده می‌شود.</p><div class="ar-form-grid">' + settingField('company_name', 'نام سازمان', settings.company_name) + settingField('company_about', 'درباره سازمان', settings.company_about, 'textarea') + '</div></section>',
				'<section><h2>نشان و راه ارتباطی</h2><div class="ar-form-grid">' + settingField('company_logo_url', 'نشانی نشان سازمان', settings.company_logo_url, 'url', 'نشانی HTTPS رسانه وردپرس یا نشان دارای مجوز') + settingField('logo_attachment_id', 'شناسه رسانه وردپرس', settings.logo_attachment_id, 'number', '', 'min="0"') + settingField('company_contact_info', 'اطلاعات تماس', settings.company_contact_info, 'textarea') + '</div></section>',
				'<section><h2>حالت اجرای سرویس</h2><div class="ar-mode-cards"><label class="ar-select-card"><input type="radio" name="mode" value="mock"' + (settings.mode !== 'live' ? ' checked' : '') + '><strong>آزمایشی — پیشنهاد برای دمو</strong><small>قطعی، بدون شبکه و بدون هزینه واقعی</small></label><label class="ar-select-card"><input type="radio" name="mode" value="live"' + (settings.mode === 'live' ? ' checked' : '') + '><strong>زنده — نیازمند کلید امن</strong><small>هیچ بازگشت پنهانی به حالت آزمایشی ندارد</small></label></div><div class="ar-form-grid" style="margin-top:20px">' + settingField('region', 'منطقه پیش‌فرض', settings.region, 'text', '', 'dir="ltr" pattern="[a-z0-9][a-z0-9-]{1,39}"') + settingField('availability_zone', 'ناحیه دسترس‌پذیری', settings.availability_zone, 'text', '', 'dir="ltr"') + '</div></section>',
				'<section><h2>Machine User API</h2><div class="ar-alert">' + ui.icon('shield') + '<div><strong>کلید هرگز بازخوانی نمی‌شود</strong><p>فقط وضعیت ذخیره‌شدن نمایش داده می‌شود. برای نگه‌داشتن مقدار فعلی، فیلد را خالی بگذارید.</p></div></div><div class="ar-form-grid" style="margin-top:20px"><div class="ar-field"><label for="ar-api-key">کلید API جدید</label><div class="ar-password-field"><input id="ar-api-key" name="api_key" type="password" autocomplete="new-password" value="' + ui.escape(settings.api_key || '') + '"><button class="ar-icon-button" type="button" data-ar-action="toggle-password" aria-label="نمایش کلید">' + ui.icon('eye') + '</button></div><small>' + (settings.api_key_configured ? 'کلید رمزگذاری‌شده موجود است.' : 'هنوز کلیدی ذخیره نشده است.') + '</small></div><div class="ar-field"><span class="ar-label">وضعیت اتصال</span>' + (settings.api_key_configured ? ui.status('active') : ui.status('pending')) + '<button class="ar-button ar-button--secondary" type="button" data-ar-action="test-connection">آزمون اتصال خواندنی</button></div></div><label class="ar-check" style="margin-top:18px"><input type="checkbox" name="delete_api_key" value="1"' + (settings.delete_api_key ? ' checked' : '') + '><span>لغو و حذف کلید رمزگذاری‌شده فعلی (روی provisioning اثر می‌گذارد)</span></label></section>',
				'<section><h2>قیمت‌گذاری فروشنده</h2><div class="ar-form-grid">' + settingField('reseller_share_percent', 'سهم فروشنده (درصد)', editableDecimal(settings.reseller_share_percent), 'number', 'حداکثر معتبر سمت سرور برابر ۲۰٪ است.', 'min="0" max="20" step="0.0001"') + settingField('currency', 'کد ارز', settings.currency, 'text', 'براساس قرارداد فعلی، مقدار پیش‌فرض IRR است.', 'dir="ltr" maxlength="3" pattern="[A-Za-z]{3}"') + '</div></section>',
				'<section><h2>کیف پول پیش‌پرداخت</h2><div class="ar-form-grid">' + settingField('default_wallet_threshold', 'آستانه هشدار موجودی', editableDecimal(settings.default_wallet_threshold), 'text', '', 'inputmode="decimal" dir="ltr"') + settingField('minimum_topup', 'حداقل شارژ', editableDecimal(settings.minimum_topup), 'text', '', 'inputmode="decimal" dir="ltr"') + settingField('maximum_topup', 'حداکثر شارژ', editableDecimal(settings.maximum_topup), 'text', '', 'inputmode="decimal" dir="ltr"') + '</div><label class="ar-check"><input type="checkbox" name="notification_enabled" value="1"' + (Number(settings.notification_enabled) ? ' checked' : '') + '><span>ارسال هشدار ایمیلی موجودی کم</span></label></section>',
				'<section><h2>تعلیق و خاتمه</h2><div class="ar-form-grid"><div class="ar-field"><label for="ar-suspend-policy">سیاست تعلیق</label><select id="ar-suspend-policy" name="suspend_policy"><option value="zero_balance"' + (settings.suspend_policy === 'zero_balance' ? ' selected' : '') + '>در موجودی صفر</option><option value="disabled"' + (settings.suspend_policy === 'disabled' ? ' selected' : '') + '>غیرفعال</option></select></div><div class="ar-field"><label for="ar-termination-policy">سیاست خاتمه</label><select id="ar-termination-policy" name="termination_policy"><option value="disabled"' + (settings.termination_policy === 'disabled' ? ' selected' : '') + '>غیرفعال</option><option value="immediate"' + (settings.termination_policy === 'immediate' ? ' selected' : '') + '>فوری</option><option value="grace"' + (settings.termination_policy === 'grace' ? ' selected' : '') + '>مهلت‌دار</option></select></div>' + settingField('termination_grace_hours', 'مهلت خاتمه (ساعت)', settings.termination_grace_hours, 'number', '', 'min="1" max="8760"') + '</div><div class="ar-alert ar-alert--warning">' + ui.icon('warning') + '<div><strong>خاموش‌کردن تضمین توقف هزینه خارجی نیست</strong><p>رابط تنها رفتار خاموش‌سازی پشتیبانی‌شده سمت سرور را نمایش می‌دهد.</p></div></div></section>',
				'<section><h2>صفحه‌های فروشگاه و پرتال</h2><p>دو صفحه مستقل از پوسته با کدهای کوتاه محصول ساخته می‌شوند. اجرای دوباره صفحه تکراری ایجاد نمی‌کند.</p><div class="ar-card ar-code-panel"><code dir="ltr">[arvan_reseller_store]</code><br><code dir="ltr">[arvan_reseller_portal]</code><div style="margin-top:18px"><button class="ar-button ar-button--secondary" type="button" data-ar-action="create-pages">ساخت/بررسی صفحه‌ها</button></div></div></section>',
				'<section><h2>آمادگی راه‌اندازی</h2><div class="ar-grid ar-grid--2"><div class="ar-alert ar-alert--success">' + ui.icon('check') + '<div><strong>حالت آزمایشی</strong><p>برای دمو بدون کلید خارجی آماده است.</p></div></div><div class="ar-alert ' + (settings.api_key_configured ? 'ar-alert--success' : 'ar-alert--warning') + '">' + ui.icon(settings.api_key_configured ? 'check' : 'warning') + '<div><strong>کلید حالت زنده</strong><p>' + (settings.api_key_configured ? 'به‌صورت رمزگذاری‌شده ذخیره شده است.' : 'برای حالت زنده هنوز نیازمند اقدام انسانی است.') + '</p></div></div></div><p style="margin-top:20px">تنظیمات را ذخیره کنید؛ آزمون اتصال زنده فقط خواندنی است و هیچ سروری ایجاد نمی‌کند.</p></section>'
			];
			const actions = '<div class="ar-wizard-actions"><button class="ar-button ar-button--secondary" type="button" data-ar-action="wizard-prev"' + (state.wizardStep === 0 ? ' disabled' : '') + '>مرحله قبل</button>' + (state.wizardStep < 9 ? '<button class="ar-button" type="button" data-ar-action="wizard-next">مرحله بعد</button>' : '<button class="ar-button" type="submit">ذخیره تنظیمات</button>') + '</div>';
			setContent(ui.pageHead(pageMeta[page][0], pageMeta[page][1], '<span class="ar-env ar-env--' + ui.escape(settings.mode) + '"><span></span>' + (settings.mode === 'live' ? 'زنده' : 'آزمایشی') + '</span>') + '<form id="ar-settings-form" class="ar-card ar-wizard-card"><div class="ar-wizard"><nav class="ar-wizard-nav" aria-label="مراحل راه‌اندازی">' + stepNav + '</nav><div class="ar-wizard-panel">' + panels.map((panel, index) => panel.replace('<section', '<section class="' + (index === state.wizardStep ? 'is-active' : '') + '"')).join('') + actions + '</div></div></form>');
		} catch (error) { setContent(ui.pageHead(pageMeta[page][0], pageMeta[page][1]) + ui.error(error, 'refresh-page')); }
	}

	async function saveSettings(form) {
		const data = Object.fromEntries(new FormData(form).entries());
		data.notification_enabled = Boolean(form.querySelector('[name="notification_enabled"]:checked'));
		data.delete_api_key = Boolean(form.querySelector('[name="delete_api_key"]:checked'));
		data.logo_attachment_id = Number(data.logo_attachment_id || 0);
		data.termination_grace_hours = Number(data.termination_grace_hours || 72);
		if (!data.api_key) delete data.api_key;
		if (!data.delete_api_key) delete data.delete_api_key;
		setButtonBusy(form.querySelector('[type="submit"]'), true, 'در حال ذخیره…');
		try { await api.patch('admin/settings', data); state.settingsDraft = null; state.data.settings = null; ui.toast('تنظیمات امن با موفقیت ذخیره شد.', 'success'); await renderSettings(); }
		catch (error) { ui.toast(ui.errorMessage(error), 'danger'); setButtonBusy(form.querySelector('[type="submit"]'), false); }
	}

	function setButtonBusy(button, busy, label) {
		if (!button) return;
		if (busy) { button.dataset.label = button.innerHTML; button.disabled = true; button.textContent = label || 'در حال انجام…'; }
		else { button.disabled = false; if (button.dataset.label) button.innerHTML = button.dataset.label; }
	}

	async function runAction(path, button, successMessage) {
		setButtonBusy(button, true);
		try { await api.post(path, {}); ui.toast(successMessage, 'success'); await renderPage(); }
		catch (error) { ui.toast(ui.errorMessage(error), 'danger'); setButtonBusy(button, false); }
	}

	async function renderPage() {
		const handlers = { dashboard: renderDashboard, setup: renderSettings, settings: renderSettings, customers: renderCustomers, payments: renderPayments, orders: renderOrders, resources: renderResources, usage: renderUsage, settlements: renderSettlements, health: renderHealth, audit: renderAudit };
		await (handlers[page] || renderDashboard)();
	}

	let searchTimer;
	content.addEventListener('input', (event) => {
		if (!event.target.matches('[data-ar-search]')) return;
		window.clearTimeout(searchTimer); state.search = event.target.value; state.collectionPage = 1; state.focusSearch = true;
		searchTimer = window.setTimeout(() => renderPage(), 260);
	});
	content.addEventListener('change', (event) => {
		if (event.target.matches('[data-ar-filter]')) { state.filter = event.target.value; state.collectionPage = 1; renderPage(); }
	});
	content.addEventListener('submit', (event) => {
		if (event.target.id === 'ar-settings-form') { event.preventDefault(); saveSettings(event.target); }
	});
	content.addEventListener('click', async (event) => {
		const pageButton = event.target.closest('[data-ar-page]');
		if (pageButton && !pageButton.disabled) { state.collectionPage = Math.max(1, Number(pageButton.dataset.arPage) || 1); state.focusCollection = true; await renderPage(); return; }
		const refund = event.target.closest('[data-ar-refund]');
		if (refund) {
			const accepted = await ui.confirm({ title: 'بازپرداخت پرداخت Mock', description: 'این عملیات دفترکل کیف پول را تغییر می‌دهد.', notice: 'مرجع پرداخت را بررسی کنید', detail: refund.dataset.arRefund, confirmLabel: 'تأیید بازپرداخت', danger: true });
			if (accepted) await runAction('admin/payments/' + encodeURIComponent(refund.dataset.arRefund) + '/refund', refund, 'بازپرداخت با موفقیت ثبت شد.');
			return;
		}
		const action = event.target.closest('[data-ar-action]'); if (!action) return;
		if (action.dataset.arAction === 'refresh-page') renderPage();
		if (action.dataset.arAction === 'wizard-next') { captureSettingsDraft(); state.wizardStep = Math.min(9, state.wizardStep + 1); renderSettings(); }
		if (action.dataset.arAction === 'wizard-prev') { captureSettingsDraft(); state.wizardStep = Math.max(0, state.wizardStep - 1); renderSettings(); }
		if (action.dataset.arAction === 'create-pages') {
			const form = app.querySelector('.ar-page-setup-form'); if (form) form.submit();
		}
		if (action.dataset.arAction === 'test-connection') {
			const accepted = await ui.confirm({ title: 'آزمون خواندنی اتصال', description: 'فقط درخواست فهرست/اتصال backend اجرا می‌شود.', notice: 'هیچ سروری ساخته یا حذف نمی‌شود', detail: 'حالت فعلی: ' + runtime.settings.mode, confirmLabel: 'اجرای آزمون' });
			if (accepted) await runAction('admin/connection-test', action, 'آزمون اتصال با موفقیت انجام شد.');
		}
		if (action.dataset.arAction === 'run-cron') {
			const accepted = await ui.confirm({ title: 'اجرای همگام‌سازی مصرف', description: 'این کار ممکن است صورتحساب و کیف پول را براساس مصرف ثبت‌شده تغییر دهد.', notice: 'عملیات idempotent backend اجرا می‌شود', confirmLabel: 'اجرای Cron' });
			if (accepted) await runAction('admin/cron/run', action, 'Cron با خروجی امن تکمیل شد.');
		}
		if (action.dataset.arAction === 'run-reconciliation') {
			const accepted = await ui.confirm({ title: 'اجرای بازیابی منابع', description: 'نگاشت سفارش‌های نیازمند بازیابی بررسی می‌شود.', notice: 'منبع جدیدی ایجاد نمی‌شود', confirmLabel: 'اجرای بازیابی' });
			if (accepted) await runAction('admin/reconciliation/run', action, 'بازیابی منابع تکمیل شد.');
		}
	});
	content.addEventListener('click', (event) => {
		const step = event.target.closest('[data-wizard-step]');
		if (step) { captureSettingsDraft(); state.wizardStep = Number(step.dataset.wizardStep); renderSettings(); }
	});

	function captureSettingsDraft() {
		const form = content.querySelector('#ar-settings-form');
		if (!form) return;
		const draft = Object.assign({}, state.data.settings || {}, Object.fromEntries(new FormData(form).entries()));
		draft.notification_enabled = form.querySelector('[name="notification_enabled"]:checked') ? 1 : 0;
		draft.delete_api_key = Boolean(form.querySelector('[name="delete_api_key"]:checked'));
		state.settingsDraft = draft;
	}

	document.querySelector('#ar-page-context').textContent = pageMeta[page] ? pageMeta[page][0] : pageMeta.dashboard[0];
	renderPage();
}());
