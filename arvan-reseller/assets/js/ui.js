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
	const customerTranslations = {
		'آزمایشی': 'Mock', 'زنده': 'Live', 'فعال': 'Active', 'سالم': 'Healthy', 'در دسترس': 'Available', 'آماده': 'Ready', 'در انتظار': 'Pending', 'در حال ساخت': 'Building', 'در حال پردازش': 'Processing', 'در حال اجرا': 'Running', 'در حال پیگیری': 'Tracking', 'تکمیل‌شده': 'Completed', 'ارسال‌شده': 'Sent', 'صادرشده': 'Issued', 'پرداخت‌شده': 'Paid', 'ناموفق': 'Failed', 'خطا': 'Error', 'لغوشده': 'Cancelled', 'منقضی': 'Expired', 'بازپرداخت‌شده': 'Refunded', 'تعلیق‌شده': 'Suspended', 'خاتمه‌یافته': 'Terminated', 'حذف‌شده': 'Deleted', 'خاموش': 'Powered off', 'مسدود': 'Blocked', 'نامشخص': 'Unknown', 'نیازمند توجه': 'Needs attention', 'اجرا نشده': 'Never run', 'بسته': 'Closed', 'باطل': 'Void', 'پیش‌نویس': 'Draft',
		'داشبورد': 'Dashboard', 'سرویس‌ها': 'Services', 'ساخت سرور': 'Create server', 'ساخت سرور ابری': 'Create cloud server', 'کیف پول': 'Wallet', 'مصرف و صورتحساب': 'Usage & billing', 'مصرف و صورتحساب‌ها': 'Usage & billing', 'صورت‌حساب‌ها': 'Invoices', 'سفارش‌ها': 'Orders', 'اعلان‌ها': 'Notifications', 'حساب مشتری': 'Customer account', 'خروج از حساب': 'Sign out', 'پنل ابری': 'Cloud panel', 'فروش ابری آروان': 'Arvan cloud commerce',
		'پرش به محتوای اصلی': 'Skip to main content', 'بازکردن ناوبری': 'Open navigation', 'بستن حساب': 'Close account', 'بستن حساب مشتری': 'Close customer account', 'بازکردن حساب مشتری': 'Open customer account', 'ناوبری پنل مشتری': 'Customer panel navigation', 'ناوبری موبایل': 'Mobile navigation', 'نمایش رمز': 'Show password', 'پنهان کردن رمز': 'Hide password', 'خوش آمدید،': 'Welcome,',
		'پوسته نمایش': 'Theme', 'زبان نمایش': 'Language', 'روشن': 'Light', 'تیره': 'Dark', 'سرویس جدید': 'New service', 'مرحله قبل': 'Previous', 'مرحله بعد': 'Next', 'ادامه پیکربندی': 'Continue configuration', 'جزئیات': 'Details', 'مشاهده همه': 'View all', 'مشاهده جزئیات': 'View details', 'مشاهده سرویس': 'View service', 'مشاهده صورتحساب': 'View invoices', 'تلاش دوباره': 'Try again', 'تأیید': 'Confirm', 'انصراف': 'Cancel', 'بستن': 'Close',
		'نمای کوتاه از کیف پول و سرویس‌ها': 'Wallet and service overview', 'سرورهای ابری متعلق به حساب شما': 'Cloud servers owned by your account', 'پیکربندی مرحله‌ای با برآورد معتبر سمت سرور': 'Step-by-step configuration with a server-side estimate', 'شارژ آزمایشی و دفترکل تغییرناپذیر': 'Mock top-up and immutable ledger', 'پنجره‌های دقیق مصرف و صورت‌حساب‌ها': 'Usage windows and invoices', 'رهگیری ساخت و شناسه منبع': 'Provisioning and resource tracking', 'هشدارهای موجودی و رویدادهای سرویس': 'Wallet and service events', 'جزئیات سرویس': 'Service details', 'وضعیت واقعی ثبت‌شده در سامانه': 'Recorded service status',
		'موجودی کیف پول': 'Wallet balance', 'موجودی قابل استفاده': 'Available balance', 'موجودی قابل استفاده حساب': 'Available account balance', 'سرویس فعال': 'Active service', 'سرویس‌های فعال': 'Active services', 'سفارش اخیر': 'Recent orders', 'سرویس‌های اخیر': 'Recent services', 'هنوز سرویسی ثبت نشده است.': 'No services have been recorded yet.', 'در حال دریافت خلاصه حساب…': 'Loading account summary…', 'در حال دریافت خلاصه حساب': 'Loading account summary', 'در حال آماده‌سازی پنل ابری...': 'Preparing cloud panel…',
		'در حال آماده‌سازی داشبورد…': 'Preparing dashboard…', 'موجودی کیف پول پایین است': 'Wallet balance is low', 'برای جلوگیری از اعمال سیاست تعلیق، موجودی را بررسی و در حالت آزمایشی شارژ کنید.': 'Review your balance and top up the wallet in Mock mode to avoid suspension.', 'مصرف دوره': 'Period usage', 'اعلان‌های خوانده‌نشده': 'Unread notifications', 'داده در دسترس نیست': 'Data unavailable', 'مصرف اخیر': 'Recent usage', 'هزینه‌های قطعی ثبت‌شده در سامانه': 'Recorded finalized charges', 'تراکنش‌های اخیر': 'Recent transactions', 'هنوز تراکنشی ندارید': 'No transactions yet', 'سرور فعال': 'Active server', 'همه سرویس‌ها': 'All services', 'سرور فعالی ندارید': 'No active servers', 'از پیکربندی مرحله‌ای برای ساخت سرور ابری استفاده کنید.': 'Use the step-by-step configurator to create a cloud server.', 'آخرین اعلان‌ها': 'Latest notifications', 'اعلانی ندارید': 'No notifications', 'هشدارهای موجودی و سرویس اینجا نمایش داده می‌شوند.': 'Wallet and service alerts appear here.',
		'سرور ابری': 'Cloud server', 'سرویس ابری': 'Cloud service', 'شناسه منبع': 'Resource ID', 'وضعیت راه‌دور': 'Remote status', 'نرخ ساعتی': 'Hourly rate', 'آخرین صورتحساب': 'Latest invoice', 'ساخت سرور جدید': 'Create new server', 'هنوز سرور ابری ندارید': 'No cloud servers yet', 'منطقه، سیستم‌عامل و پلن را از کاتالوگ سرویس انتخاب کنید.': 'Choose a region, operating system, and plan from the service catalog.', 'شروع پیکربندی': 'Start configuration', 'بازگشت به سرویس‌ها': 'Back to services', 'کپی شناسه': 'Copy ID', 'هشدار کاهش موجودی': 'Low-balance warning', 'موجودی کیف پول به آستانه هشدار رسیده است.': 'Wallet balance has reached the warning threshold.', 'منطقه / ناحیه': 'Region / zone', 'مصرف و هزینه سرویس': 'Service usage and cost', 'مقادیر قطعی ثبت‌شده برای همین شناسه منبع': 'Finalized values recorded for this resource ID', 'جزئیات مصرف': 'Usage details', 'پیکربندی ثبت‌شده': 'Recorded configuration', 'تصویر': 'Image', 'پلن': 'Plan', 'دیسک ریشه': 'Root disk', 'نشانی IP': 'IP address', 'ارائه نشده': 'Not provided', 'فقط فیلدهای موجود در پاسخ رسمی سرویس نمایش داده می‌شوند.': 'Only fields returned by the service response are shown.', 'چرخه عمر': 'Lifecycle', 'صورتحساب‌ها': 'Invoices', 'صورتحساب‌های حساب مشتری': 'Customer account invoices',
		'سفارش ثبت شد': 'Order submitted', 'موجودی و سفارش بررسی شد': 'Balance and order verified', 'درخواست ساخت ارسال شد': 'Provisioning request sent', 'شناسه سرویس دریافت شد': 'Service ID received', 'سرویس فعال شد': 'Service became active', 'فرایند ناموفق یا نیازمند بازیابی': 'Failed or recovery required', 'در حال پیگیری': 'In progress',
		'شارژ کیف پول': 'Top up wallet', 'آستانه هشدار': 'Warning threshold', 'هشدار خودکار سامانه': 'Automated system warning', 'تراکنش‌ها': 'Transactions', 'دفترکل تغییرناپذیر': 'Immutable ledger', 'پرداخت‌ها': 'Payments', 'مرجع': 'Reference', 'مبلغ': 'Amount', 'وضعیت': 'Status', 'درگاه': 'Provider', 'زمان': 'Time', 'عملیات': 'Actions', 'تأیید پرداخت': 'Confirm payment', 'پرداختی ثبت نشده است': 'No payments recorded', 'تاریخچه تراکنش': 'Transaction history', 'نوع': 'Type', 'شرح': 'Description', 'تراکنشی ثبت نشده است': 'No transactions recorded', 'موجودی در محدوده هشدار است': 'Balance is within the warning range', 'هشدار خودکار سامانه': 'Automated warning',
		'پرداخت آنلاین در حالت فعلی در دسترس نیست.': 'Online payment is not available in the current mode.', 'درگاه پرداخت پیکربندی نشده است': 'Payment gateway is not configured', 'برای جلوگیری از نمایش یک درگاه غیرواقعی، عملیات شارژ در حالت زنده غیرفعال است.': 'Top-up is disabled in Live mode so a fake gateway is never shown.', 'شارژ آزمایشی کیف پول': 'Mock wallet top-up', 'این عملیات کاملاً آزمایشی و بدون تراکنش بانکی واقعی است.': 'This is a fully mocked operation with no real bank transaction.', 'مبلغ‌های پیشنهادی': 'Suggested amounts', 'پرداخت و تأیید آزمایشی': 'Mock payment and confirmation', 'پرداخت از endpoint موجود ساخته و سپس از مسیر تأیید Mock به‌صورت اتمیک به کیف پول افزوده می‌شود.': 'The payment is created through the existing endpoint and atomically credited through Mock confirmation.', 'مبلغ شارژ': 'Top-up amount', 'مبلغ پرداخت': 'Payment amount', 'روش: پرداخت آزمایشی': 'Method: Mock payment', 'تکمیل شارژ آزمایشی': 'Complete Mock top-up', 'در حال ثبت…': 'Submitting…', 'در حال ثبت': 'Submitting', 'شارژ آزمایشی با موفقیت تکمیل شد.': 'Mock top-up completed successfully.', 'تأیید پرداخت آزمایشی': 'Confirm Mock payment', 'کیف پول به‌شکل اتمیک و تکرارپذیر شارژ می‌شود.': 'The wallet is credited atomically and idempotently.', 'هیچ پرداخت بانکی واقعی انجام نمی‌شود': 'No real bank payment is performed', 'تأیید و شارژ': 'Confirm and credit', 'کیف پول با موفقیت شارژ شد.': 'Wallet credited successfully.',
		'در حال دریافت کاتالوگ سرور ابری...': 'Loading cloud server catalog…', 'مراحل ساخت سرور': 'Server creation steps', 'منطقه': 'Region', 'سیستم‌عامل': 'Operating system', 'منابع': 'Resources', 'پیکربندی': 'Configuration', 'بررسی و سفارش': 'Review & order', 'منطقه سرویس': 'Service region', 'منطقه از کاتالوگ سامانه دریافت شده است.': 'Regions are loaded from the service catalog.', 'تصویر و سیستم‌عامل': 'Image and operating system', 'فقط تصویرهای موجود در کاتالوگ نمایش داده می‌شوند.': 'Only catalog images are shown.', 'پلن پردازشی': 'Compute plan', 'قیمت اولیه از کاتالوگ است؛ مبلغ نهایی تنها از برآورد سمت سرور می‌آید.': 'Catalog pricing is indicative; the final amount comes only from the server estimate.', 'تنظیمات پشتیبانی‌شده': 'Supported settings', 'نام و حجم دیسک ریشه مطابق محدودیت سفارش است.': 'Server name and root disk follow order constraints.', 'نام سرور': 'Server name', 'حجم دیسک ریشه (گیگابایت)': 'Root disk size (GB)', 'فعال‌سازی پشتیبان‌گیری': 'Enable backup', 'فعال‌سازی جایگزینی خودکار': 'Enable failover', 'IPv4 در صورت پشتیبانی': 'IPv4 when supported', 'IPv6 در صورت پشتیبانی': 'IPv6 when supported', 'در صورت پشتیبانی': 'when supported', 'بررسی و برآورد سمت سرور': 'Review and server-side estimate', 'برآورد برای ۲۴ ساعت استفاده درخواست می‌شود؛ تخمین ماهانه ساخته نمی‌شود.': 'The estimate covers 24 hours; no browser-side monthly estimate is fabricated.', 'در حال دریافت برآورد معتبر...': 'Loading a valid estimate…', 'برآورد معتبر ۲۴ ساعت': 'Valid 24-hour estimate', 'هزینه پایه': 'Base cost', 'سهم فروشنده': 'Reseller share', 'دریافت‌شده در': 'Received at', 'برداشت پیش‌پرداخت از سمت سرور': 'Server-side prepaid debit', 'مبلغ قطعی ۲۴ ساعت نخست هنگام سفارش از کیف پول کسر می‌شود؛ پس از آن پنجره‌های کامل ساعتی با نرخ ذخیره‌شده صورتحساب می‌شوند.': 'The first finalized 24-hour amount is debited on order; later complete hourly windows use the stored rate.', 'شرایط سرویس پیش‌پرداخت و محدودیت‌های سرور ابری را خوانده‌ام.': 'I have read the prepaid service terms and cloud server limits.', 'موجودی فعلی': 'Current balance', 'کفایت موجودی و برداشت نهایی در backend به‌صورت اتمیک کنترل می‌شود.': 'Balance sufficiency and final debit are enforced atomically by the backend.', '— کفایت موجودی و برداشت نهایی در backend به‌صورت اتمیک کنترل می‌شود.': '— Balance sufficiency and final debit are enforced atomically by the backend.', 'خلاصه سفارش': 'Order summary', 'موقعیت': 'Location', 'قیمت قطعی از سرویس برآورد سمت سرور دریافت می‌شود.': 'Final pricing comes from the server-side estimate service.', 'موجودی برای این برآورد کافی نیست': 'Balance is insufficient for this estimate', 'پیش از ثبت سفارش، کیف پول را شارژ کنید.': 'Top up the wallet before placing the order.', 'برای ادامه، گزینه‌های لازم این مرحله را کامل کنید.': 'Complete the required selections for this step.', 'ثبت سفارش و ساخت سرور': 'Place order and create server', 'پیش از سفارش، شرایط سرویس را تأیید کنید.': 'Accept the service terms before ordering.', 'سفارش به سامانه معتبر ارسال می‌شود.': 'The order is sent to the authoritative backend.', 'عملیات آزمایشی و بدون هزینه خارجی است': 'Mock operation with no external charge', 'عملیات زنده می‌تواند هزینه‌زا باشد؛ فقط با تأیید آگاهانه ادامه دهید': 'Live operation can incur costs; continue only with informed confirmation', 'ساخت سرور آزمایشی': 'Create Mock server', 'تأیید ساخت زنده': 'Confirm Live creation',
		'سفارش جدید': 'New order', 'مرجع سفارش': 'Order reference', 'برآورد ۲۴ساعته': '24-hour estimate', 'پرداخت سفارش': 'Order payment', 'منبع/بازیابی': 'Resource / recovery', 'ثبت': 'Created', 'سفارشی ثبت نشده است': 'No orders recorded', 'رهگیری فرایند ساخت': 'Provisioning tracker', 'رهگیری خودکار متوقف شد؛ برای بررسی دوباره تازه‌سازی کنید.': 'Automatic tracking stopped; refresh to check again.', 'نیازمند بازیابی': 'Recovery required', 'مشاهده سرویس': 'View service',
		'اعلان‌های خوانده‌نشده': 'Unread notifications', 'خوانده‌نشده': 'Unread', 'خوانده‌شده': 'Read', 'خواندم': 'Mark read', 'کانال': 'Channel', 'ارسال': 'Sent', 'خوانده‌شدن': 'Read at', 'کد امن': 'Safe code', 'اعلانی وجود ندارد': 'No notifications', 'هشدارهای صورتحساب و چرخه سرویس اینجا ثبت می‌شوند.': 'Billing and service lifecycle alerts appear here.', 'اعلان سیستمی': 'System notification', 'هشدار موجودی کم': 'Low-balance alert', 'تعلیق سرویس': 'Service suspension', 'خاتمه سرویس': 'Service termination', 'خطای ساخت سرویس': 'Provisioning error',
		'دریافت اطلاعات ممکن نشد': 'Unable to load information', 'در حال دریافت اطلاعات…': 'Loading information…', 'داده‌ای برای نمودار ثبت نشده است.': 'No chart data recorded.', 'نمودار روند': 'Trend chart', 'قدیمی‌تر': 'Earlier', 'اکنون': 'Now', 'پیش از ادامه بررسی کنید': 'Review before continuing', 'عملیات انجام نشد.': 'The operation failed.', 'شناسه کپی شد.': 'ID copied.', 'کپی خودکار ممکن نشد.': 'Automatic copy failed.', 'وقتی داده‌ای ثبت شود، در این بخش نمایش داده می‌شود.': 'Data will appear here when it is recorded.',
		'است.': 'is.', 'آستانه فعلی': 'Current threshold', 'اعلان اخیر': 'Recent notification', 'آماده شد': 'is ready', 'انتخاب‌شده': 'Selected', 'بازه': 'Window', 'بازه مجاز:': 'Allowed range:', 'برداشت پیش‌پرداخت، دریافت شناسه منبع و نگاشت محلی با موفقیت ثبت شد.': 'The prepaid debit, resource ID, and local mapping were recorded successfully.', 'به‌صورت اتمیک به کیف پول افزوده می‌شود.': 'is atomically credited to the wallet.', 'به‌صورت اتمیک کنترل می‌شود.': 'is enforced atomically.', 'پایان': 'End', 'پرداخت از': 'Payment from', 'پرداخت تکمیل شد': 'Payment completed', 'پرداخت ناموفق': 'Payment failed', 'پنجره مصرف': 'Billing window', 'پوشش‌نیافته': 'Uncovered', 'تا': 'to', 'تأیید و ساخت سرور': 'Confirm and create server', 'در حال ساخت…': 'Creating…', 'تنظیمات': 'Settings', 'جدول دسترس‌پذیر مصرف': 'Accessible usage table', 'جمع': 'Total', 'حالت': 'Mode', '· حالت': '· Mode', '+ سهم فروشنده': '+ reseller margin', 'افزایش موجودی': 'Balance credit', 'کاهش موجودی': 'Balance debit', 'اصلاح حساب': 'Account adjustment', 'نشست شما پایان یافته است. به صفحه ورود هدایت می‌شوید.': 'Your session has ended. Redirecting to sign in.', 'در حال آماده‌سازی داشبورد': 'Preparing dashboard', 'در ساعت': 'per hour', 'دریافت‌شده': 'Received', 'رکوردهای ثبت‌شده': 'Recorded entries', 'روند مصرف': 'Usage trend', 'ساخت سرور ابری در حالت': 'Create cloud server in', 'سرور ابری در حالت': 'Cloud server in', 'سرویس ثبت‌شده': 'Registered service', 'سفارش سرور ابری': 'Cloud server order', 'شروع': 'Start', 'صادرشده/': 'Issued /', 'صورتحسابی ثبت نشده است': 'No invoices recorded', 'کسری کیف پول': 'Wallet shortfall', 'کفایت موجودی و برداشت نهایی در': 'Balance sufficiency and final debit in', 'گیگابایت': 'GB', 'گیگابایت دیسک': 'GB disk', 'مرحله': 'Step', 'مقدار': 'Value', 'مگابایت رم': 'MB RAM', 'منبع': 'Resource', 'موجود ساخته و سپس از مسیر تأیید': 'is created and then confirmed through', 'نشانی': 'Address', 'نه برآورد مرورگر': 'not a browser estimate', 'هزینه ثبت‌شده': 'Recorded charge', 'هنوز مصرفی ثبت نشده است': 'No usage recorded yet',
		'معرفی پنل ابری': 'Cloud panel introduction', 'مبتنی بر زیرساخت آروان‌کلاد': 'Powered by ArvanCloud infrastructure', 'دسترسی امن مشتریان': 'Secure customer access', 'مدیریت یکپارچه زیرساخت ابری': 'Unified cloud infrastructure management', 'کیف پول، سفارش‌ها، سرورهای ابری، صورتحساب‌ها و اعلان‌های عملیاتی را با حساب امن وردپرس خود مدیریت کنید.': 'Manage your wallet, orders, cloud servers, invoices, and operational notifications with your secure WordPress account.', 'رمز عبور فقط توسط وردپرس پردازش می‌شود و این افزونه آن را ذخیره نمی‌کند.': 'Your password is processed only by WordPress and is never stored by this plugin.', 'نشست محافظت‌شده': 'Protected session', 'ورود به پنل ابری': 'Sign in to the cloud panel', 'از اطلاعات حساب وردپرس خود استفاده کنید.': 'Use your WordPress account credentials.', 'نام کاربری یا ایمیل': 'Username or email', 'رمز عبور': 'Password', 'نمایش رمز عبور': 'Show password', 'ورود من را حفظ کن': 'Remember me', 'ورود امن': 'Secure sign in', 'رمز عبور را فراموش کرده‌اید؟': 'Forgot your password?', 'ایجاد حساب': 'Create account',
		'خرید و مدیریت سرور ابری با کیف پول پیش‌پرداخت و صورتحساب شفاف.': 'Buy and manage cloud servers with a prepaid wallet and transparent billing.', 'ناوبری فروشگاه': 'Store navigation', 'کیف پول و مصرف': 'Wallet & usage', 'امنیت': 'Security', 'راهنما': 'Guide', 'ورود': 'Sign in', 'پنل مشتری': 'Customer panel', 'زیرساخت ابری بدون پیچیدگی': 'Cloud infrastructure without complexity', 'سرور ابری، سریع، شفاف و قابل مدیریت': 'Fast, transparent, manageable cloud servers', 'برآورد قیمت، ثبت سفارش و مدیریت چرخه سرویس در یک تجربه یکپارچه انجام می‌شود.': 'Estimate pricing, place orders, and manage the service lifecycle in one integrated experience.', 'ورود به پنل مشتری': 'Sign in to customer panel', 'انتخاب منطقه، تصویر و پلن از کاتالوگ سرویس': 'Choose a region, image, and plan from the service catalog', 'برآورد معتبر قیمت پیش از ثبت سفارش': 'Authoritative price estimate before ordering', 'مدیریت کیف پول، مصرف و صورتحساب در یک پنل': 'Manage wallet, usage, and billing in one panel',
		'معرفی محصول سرور ابری': 'Cloud server product overview', 'سرور ابری قابل پیکربندی': 'Configurable cloud server', 'آماده سفارش': 'Ready to order', 'منطقه پیش‌فرض': 'Default region', 'پس از پیکربندی': 'After configuration', 'شیوه پرداخت': 'Payment method', 'کیف پول پیش‌پرداخت': 'Prepaid wallet', 'محاسبه مصرف': 'Usage calculation', 'بازه‌های ساعتی': 'Hourly windows', 'ارز تنظیم‌شده': 'Configured currency', 'قیمت‌گذاری سمت سرور': 'Server-side pricing', 'مبلغ نهایی پس از انتخاب پلن از سامانه دریافت می‌شود؛ مرورگر قیمت را حدس نمی‌زند.': 'The final amount is returned by the service after plan selection; the browser never guesses the price.', 'شروع پیکربندی سرور': 'Start server configuration',
		'اصول تجربه سرویس': 'Service experience principles', 'برآورد معتبر': 'Authoritative estimate', 'قیمت از سرویس سمت سرور': 'Price from the server-side service', 'مالکیت امن': 'Secure ownership', 'هر منبع در حساب مشتری': 'Every resource belongs to the customer account', 'مالی شفاف': 'Transparent billing', 'کیف پول، مصرف و صورتحساب': 'Wallet, usage, and billing', 'تجربه یکپارچه سرویس': 'Integrated service experience', 'هرآنچه برای خرید و مدیریت سرور نیاز دارید': 'Everything you need to buy and manage a server', 'از برآورد تا صورتحساب، همه مراحل با داده معتبر سامانه و در یک رابط روشن انجام می‌شود.': 'From estimate to invoice, every step uses authoritative system data in one clear interface.',
		'پرداخت به‌اندازه مصرف': 'Pay for recorded usage', 'هزینه‌های ثبت‌شده و بازه‌های مصرف را با واحد و ارز واقعی دنبال کنید.': 'Track recorded charges and usage windows with their actual unit and currency.', 'موجودی، تراکنش‌ها و هشدار کاهش اعتبار همیشه در دسترس است.': 'Balance, transactions, and low-credit alerts are always available.', 'کنترل کامل سرویس': 'Complete service control', 'وضعیت ساخت، شناسه منبع، مصرف و چرخه سرویس را یکجا ببینید.': 'View provisioning status, resource ID, usage, and service lifecycle in one place.', 'امنیت حساب': 'Account security', 'نشست وردپرس، کنترل مالکیت و درخواست‌های احرازشده از حساب محافظت می‌کنند.': 'WordPress sessions, ownership checks, and authenticated requests protect the account.',
		'عملیات ابری یکپارچه': 'Integrated cloud operations', 'زیرساخت ابری برای کسب‌وکارهای در حال رشد': 'Cloud infrastructure for growing businesses', 'پیکربندی سرور، برآورد مصرف، کنترل کیف پول و مشاهده چرخه منبع در یک فضای عملیاتی منسجم کنار هم قرار گرفته‌اند.': 'Server configuration, usage estimates, wallet controls, and resource lifecycle visibility come together in one consistent workspace.', 'انتخاب مستقیم از کاتالوگ سرویس': 'Direct selection from the service catalog', 'برآورد معتبر پیش از سفارش': 'Authoritative estimate before ordering', 'مالکیت امن منابع در حساب مشتری': 'Secure resource ownership in the customer account', 'سرویس، مصرف، کیف پول': 'Service, usage, wallet',
		'مسیر سفارش': 'Order journey', 'از انتخاب تا شناسه سرویس، در یک فرایند روشن': 'From selection to service ID in one clear flow', 'کاتالوگ و برآورد از سامانه می‌آیند و وضعیت ساخت تا دریافت شناسه منبع قابل مشاهده است.': 'The catalog and estimate come from the system, while provisioning remains visible through resource ID delivery.', 'انتخاب پیکربندی': 'Choose configuration', 'منطقه، سیستم‌عامل و منابع': 'Region, operating system, and resources', 'بررسی برآورد': 'Review estimate', 'قیمت معتبر و موجودی کیف پول': 'Authoritative price and wallet balance', 'ثبت و ساخت': 'Place order and provision', 'سفارش تکرارپذیر امن و رهگیری وضعیت': 'Safe idempotent ordering and status tracking', 'مدیریت سرویس': 'Manage service', 'شناسه منبع، مصرف و صورتحساب': 'Resource ID, usage, and billing', 'فروش مستقل سرور ابری': 'Independent cloud server commerce',
		'نشست شما پایان یافته است. برای ادامه دوباره وارد شوید.': 'Your session has ended. Sign in again to continue.', 'دسترسی لازم برای این عملیات را ندارید.': 'You do not have permission for this operation.', 'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.': 'Too many requests. Try again shortly.', 'پاسخ سرویس بیش از حد طول کشید. دوباره تلاش کنید.': 'The service response timed out. Try again.', 'ارتباط شبکه برقرار نشد. اتصال خود را بررسی کنید.': 'The network connection failed. Check your connection.', 'سرویس پشتیبان در دسترس نیست. کمی بعد دوباره تلاش کنید.': 'The backend service is unavailable. Try again shortly.', 'موجودی کیف پول برای این عملیات کافی نیست.': 'Wallet balance is insufficient for this operation.', 'اطلاعات برآورد کامل یا معتبر نیست.': 'The estimate is incomplete or invalid.', 'پلن انتخاب‌شده دیگر در دسترس نیست. فهرست را تازه کنید.': 'The selected plan is no longer available. Refresh the catalog.', 'پرداخت مورد نظر پیدا نشد.': 'The requested payment was not found.', 'سرویس مورد نظر پیدا نشد.': 'The requested service was not found.', 'سرور ایجاد شده اما ثبت محلی نیازمند بازیابی مدیر است.': 'The server was created, but the local record requires administrator recovery.', 'تعویض و حذف کلید API را هم‌زمان انتخاب نکنید.': 'API key replacement and deletion cannot be selected together.'
	};
	const originalText = new WeakMap();
	const originalAttributes = new WeakMap();
	const drawerStates = new WeakMap();
	const translationObservers = new WeakMap();
	const mobileDrawerMedia = window.matchMedia('(max-width: 1024px)');

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

	function translateOwnedText(value, language) {
		const source = String(value === null || typeof value === 'undefined' ? '' : value);
		if ((language || storedLanguage()) !== 'en' || !source.trim()) return source;
		const leading = (source.match(/^\s*/) || [''])[0];
		const trailing = (source.match(/\s*$/) || [''])[0];
		const core = source.trim();
		if (/^[۰-۹٠-٩,.]+$/.test(core)) return leading + stableDigits(core) + trailing;
		if (customerTranslations[core]) return leading + customerTranslations[core] + trailing;
		const patterns = [
			[/^([\d,.]+) سرویس ثبت‌شده$/, '$1 registered services'],
			[/^([\d,.]+) پنجره مصرف$/, '$1 billing windows'],
			[/^([\d,.]+) اعلان اخیر$/, '$1 recent notifications'],
			[/^([\d,.]+) گیگابایت$/, '$1 GB'],
			[/^([\d,.]+) گیگابایت دیسک$/, '$1 GB disk'],
			[/^([\d,.]+) مگابایت رم$/, '$1 MB RAM'],
			[/^مرحله ([\d,.]+)$/, 'Step $1'],
			[/^سرور ابری · (.+)$/, 'Cloud server · $1'],
			[/^ساخت سرور ابری در حالت (.+)$/, (match, mode) => 'Create cloud server in ' + translateOwnedText(mode, 'en') + ' mode'],
			[/^سرور ابری در حالت (.+) آماده شد$/, (match, mode) => 'Cloud server is ready in ' + translateOwnedText(mode, 'en') + ' mode'],
			[/^آستانه فعلی (.+) است\.$/, 'Current threshold is $1.'],
			[/^بازه مجاز: (.+) تا (.+)$/, 'Allowed range: $1 to $2'],
			[/^دریافت‌شده در (.+)$/, 'Received at $1'],
			[/^حالت (.+)$/, (match, mode) => translateOwnedText(mode, 'en') + ' mode'],
			[/^در ساعت (.+)$/, '$1 per hour'],
			[/^خوش آمدید، (.+)$/, 'Welcome, $1'],
			[/^عملیات انجام نشد\. با کد پشتیبانی (.+) دوباره تلاش کنید\.$/, 'The operation failed. Support code: $1.']
		];
		for (const pattern of patterns) {
			if (pattern[0].test(core)) return leading + core.replace(pattern[0], pattern[1]) + trailing;
		}
		return source;
	}

	function translateDom(root) {
		const scope = root || document;
		const owner = scope.matches && scope.matches('.arvan-reseller-app') ? scope : (scope.closest && scope.closest('.arvan-reseller-app'));
		if (!owner) {
			if (scope.querySelectorAll) scope.querySelectorAll('.arvan-reseller-app').forEach((app) => translateDom(app));
			return;
		}
		const language = storedLanguage();
		const nodes = [];
		if (scope.matches && scope.matches('[data-ar-i18n]')) nodes.push(scope);
		scope.querySelectorAll('[data-ar-i18n]').forEach((node) => nodes.push(node));
		nodes.forEach((node) => {
			if (node.closest('[data-ar-language-fixed]')) return;
			const translated = t(node.dataset.arI18n, node.textContent);
			if (node.textContent !== translated) node.textContent = translated;
		});
		scope.querySelectorAll('[data-ar-i18n-label]').forEach((node) => {
			if (node.closest('[data-ar-language-fixed]')) return;
			node.setAttribute('aria-label', t(node.dataset.arI18nLabel, node.getAttribute('aria-label')));
		});
		const walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT);
		let textNode = walker.nextNode();
		while (textNode) {
			const parent = textNode.parentElement;
			if (parent && !parent.closest('[data-ar-i18n], [data-ar-no-i18n], [data-ar-language-fixed], code, bdi, script, style')) {
				if (!originalText.has(textNode)) originalText.set(textNode, textNode.nodeValue);
				const original = originalText.get(textNode);
				const translated = language === 'en' ? translateOwnedText(original, 'en') : original;
				if (textNode.nodeValue !== translated) textNode.nodeValue = translated;
			}
			textNode = walker.nextNode();
		}
		const attributeNodes = [];
		if (scope.matches && scope.matches('[aria-label], [title], [placeholder], [data-label]')) attributeNodes.push(scope);
		scope.querySelectorAll('[aria-label], [title], [placeholder], [data-label]').forEach((node) => attributeNodes.push(node));
		attributeNodes.forEach((node) => {
			if (node.closest('[data-ar-no-i18n], [data-ar-language-fixed]')) return;
			if (!originalAttributes.has(node)) originalAttributes.set(node, {});
			const originals = originalAttributes.get(node);
			['aria-label', 'title', 'placeholder', 'data-label'].forEach((attribute) => {
				if (!node.hasAttribute(attribute)) return;
				if (attribute === 'aria-label' && node.hasAttribute('data-ar-i18n-label')) return;
				if (typeof originals[attribute] === 'undefined') originals[attribute] = node.getAttribute(attribute);
				node.setAttribute(attribute, language === 'en' ? translateOwnedText(originals[attribute], 'en') : originals[attribute]);
			});
		});
		scope.querySelectorAll('[data-ar-date]').forEach((node) => {
			const owner = node.closest('.arvan-reseller-app');
			const ownerLanguage = owner && owner.hasAttribute('data-ar-language-fixed') ? owner.dataset.arLanguageFixed : (owner && owner.lang);
			const parsed = new Date(node.dataset.arDate);
			if (!Number.isNaN(parsed.getTime())) node.textContent = formatDate(parsed, ownerLanguage || language);
		});
	}

	function setText(node, source) {
		if (!node) return;
		node.textContent = String(source === null || typeof source === 'undefined' ? '' : source);
		translateDom(node);
	}

	function applyLanguage(language, persist) {
		const value = language === 'en' ? 'en' : 'fa';
		if (persist) {
			try { window.localStorage.setItem(languageStorageKey, value); } catch (error) { /* Preference remains active for this page. */ }
		}
		const apps = Array.from(document.querySelectorAll('.arvan-reseller-app'));
		apps.forEach((app) => {
			if (app.hasAttribute('data-ar-language-fixed')) {
				app.lang = app.dataset.arLanguageFixed || 'fa'; app.dir = 'rtl'; return;
			}
			app.lang = value;
			app.dir = value === 'en' ? 'ltr' : 'rtl';
			app.querySelectorAll('[data-ar-language-value]').forEach((button) => {
				const selected = button.dataset.arLanguageValue === value;
				button.classList.toggle('is-selected', selected);
				button.setAttribute('aria-pressed', String(selected));
			});
			translateDom(app);
		});
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

	const exactMoney = {
		parse(value) {
			if (typeof value === 'bigint') return value;
			const source = stableDigits(value === null || typeof value === 'undefined' ? '' : value).replace(/,/g, '').trim();
			const match = source.match(/^(-?)(\d+)(?:\.(\d{0,4}))?$/);
			if (!match) return null;
			const fraction = (match[3] || '').padEnd(4, '0');
			const minor = (BigInt(match[2]) * 10000n) + BigInt(fraction || '0');
			return match[1] ? -minor : minor;
		},
		format(value) {
			const minor = this.parse(value);
			if (minor === null) return null;
			const negative = minor < 0n; const absolute = negative ? -minor : minor;
			return (negative ? '-' : '') + String(absolute / 10000n) + '.' + String(absolute % 10000n).padStart(4, '0');
		},
		fromMinor(value) {
			const source = stableDigits(value === null || typeof value === 'undefined' ? '' : value).trim();
			if (!/^-?\d+$/.test(source)) return null;
			return this.format(BigInt(source));
		},
		add(left, right) {
			const a = this.parse(left); const b = this.parse(right);
			return a === null || b === null ? null : this.format(a + b);
		},
		sum(values) {
			let total = 0n;
			for (const value of values || []) {
				const minor = this.parse(value);
				if (minor === null) return null;
				total += minor;
			}
			return this.format(total);
		},
		compare(left, right) {
			const a = this.parse(left); const b = this.parse(right);
			if (a === null || b === null) return null;
			return a === b ? 0 : (a < b ? -1 : 1);
		}
	};

	function money(value, currency) {
		const fixed = exactMoney.format(value);
		const amount = fixed === null ? '—' : decimal(fixed, 4);
		if (amount === '—') return '<span class="ar-money ar-money--unavailable">—</span>';
		return '<span class="ar-money"><bdi>' + escape(amount) + '</bdi> <small dir="ltr">' + escape(currency || (window.ArvanResellerRuntime.settings || {}).currency || 'IRR') + '</small></span>';
	}

	function formatDate(parsed, language) {
		const locale = language === 'en' ? 'en-GB' : 'fa-IR-u-nu-latn';
		return new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(parsed);
	}

	function date(value) {
		if (!value) return '—';
		const normalized = typeof value === 'number' ? value : (String(value).includes('T') ? String(value) : String(value).replace(' ', 'T') + 'Z');
		const parsed = new Date(normalized);
		if (Number.isNaN(parsed.getTime())) return escape(value);
		const app = document.querySelector('.arvan-reseller-app');
		const language = app && app.hasAttribute('data-ar-language-fixed') ? app.dataset.arLanguageFixed : (app && app.lang) || storedLanguage();
		return '<time class="ar-date" datetime="' + escape(parsed.toISOString()) + '" data-ar-date="' + escape(parsed.toISOString()) + '">' + escape(formatDate(parsed, language)) + '</time>';
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
		const code = error && error.code;
		return errorMessages[code] || 'عملیات انجام نشد. با کد پشتیبانی ' + escape(code || 'unknown') + ' دوباره تلاش کنید.';
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
		item.innerHTML = icon(tone === 'danger' ? 'warning' : 'check') + '<span>' + escape(translateOwnedText(message)) + '</span>';
		region.appendChild(item);
		window.setTimeout(() => item.remove(), 5200);
	}

	function modal(options) {
		const root = document.querySelector('.arvan-reseller-app .ar-modal-root');
		if (!root) return null;
		const previousFocus = document.activeElement;
		const app = root.closest('.arvan-reseller-app');
		const background = app ? Array.from(app.children).filter((node) => node !== root) : [];
		const previousInert = background.map((node) => node.inert);
		const previousOverflow = document.body.style.overflow;
		background.forEach((node) => { node.inert = true; });
		document.body.style.overflow = 'hidden';
		root.innerHTML = '<div class="ar-modal-backdrop" data-ar-modal-backdrop><section class="ar-modal" role="dialog" aria-modal="true" aria-labelledby="ar-modal-title" tabindex="-1"><div class="ar-modal__head"><div><h2 id="ar-modal-title">' + escape(options.title) + '</h2>' + (options.description ? '<p>' + escape(options.description) + '</p>' : '') + '</div><button class="ar-icon-button" type="button" data-ar-modal-close aria-label="بستن">' + icon('close') + '</button></div><div class="ar-modal__body">' + (options.body || '') + '</div></section></div>';
		const backdrop = root.querySelector('[data-ar-modal-backdrop]');
		const dialog = root.querySelector('.ar-modal');
		translateDom(root);
		const close = () => {
			root.innerHTML = '';
			document.removeEventListener('keydown', keyHandler);
			background.forEach((node, index) => { node.inert = previousInert[index]; });
			document.body.style.overflow = previousOverflow;
			if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
			if (typeof options.onClose === 'function') options.onClose();
		};
		const keyHandler = (event) => {
			if (event.key === 'Escape') close();
			if (event.key === 'Tab') {
				const focusable = Array.from(dialog.querySelectorAll('button, input, select, textarea, a[href]')).filter((node) => !node.disabled);
				if (!focusable.length) { event.preventDefault(); dialog.focus(); return; }
				const first = focusable[0]; const last = focusable[focusable.length - 1];
				if (!dialog.contains(document.activeElement)) { event.preventDefault(); first.focus(); return; }
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

	function drawerState(app) {
		if (!drawerStates.has(app)) drawerStates.set(app, { trigger: null, open: false });
		return drawerStates.get(app);
	}

	function setSidebarOpen(app, open, restoreFocus) {
		const sidebar = app.querySelector('.ar-sidebar');
		const trigger = app.querySelector('[data-ar-action="toggle-sidebar"]');
		const scrim = app.querySelector('.ar-sidebar-scrim');
		const main = app.querySelector('.ar-main');
		const topbarActions = app.querySelector('.ar-topbar__actions');
		const bottomNav = app.querySelector('.ar-bottom-nav');
		if (!sidebar || !trigger) return;
		const state = drawerState(app);
		const mobile = mobileDrawerMedia.matches;
		const shouldOpen = mobile && Boolean(open);
		if (shouldOpen && !state.open) state.trigger = document.activeElement === trigger ? trigger : document.activeElement;
		state.open = shouldOpen;
		app.classList.toggle('is-sidebar-open', shouldOpen);
		trigger.setAttribute('aria-expanded', String(shouldOpen));
		if (scrim) scrim.hidden = !shouldOpen;
		document.body.classList.toggle('ar-mobile-drawer-open', shouldOpen);
		if (main) main.inert = shouldOpen;
		if (topbarActions) topbarActions.inert = shouldOpen;
		if (bottomNav) bottomNav.inert = shouldOpen;
		if (mobile) {
			sidebar.setAttribute('role', 'dialog'); sidebar.setAttribute('aria-modal', 'true'); sidebar.setAttribute('aria-hidden', String(!shouldOpen));
		} else {
			sidebar.removeAttribute('role'); sidebar.removeAttribute('aria-modal'); sidebar.removeAttribute('aria-hidden');
		}
		if (shouldOpen) {
			window.requestAnimationFrame(() => (sidebar.querySelector('[data-ar-action="close-sidebar"], .ar-nav-item, a[href], button') || sidebar).focus());
		} else if (restoreFocus !== false && state.trigger && typeof state.trigger.focus === 'function') {
			state.trigger.focus();
		}
	}

	function closeSidebar(app, restoreFocus) {
		setSidebarOpen(app, false, restoreFocus);
	}

	function wireGlobalActions(app) {
		if (!app) return;
		mountIcons(app);
		wireThemeControls(app);
		wireLanguageControls(app);
		if (!translationObservers.has(app)) {
			const observer = new MutationObserver((records) => {
				const targets = new Set();
				records.forEach((record) => {
					if (record.target && record.target.nodeType === Node.ELEMENT_NODE) targets.add(record.target);
				});
				targets.forEach((target) => translateDom(target));
			});
			observer.observe(app, { childList: true, subtree: true });
			translationObservers.set(app, observer);
		}
		app.addEventListener('click', (event) => {
			const action = event.target.closest('[data-ar-action]');
			if (!action) return;
			if (action.dataset.arAction === 'toggle-sidebar') {
				event.preventDefault();
				setSidebarOpen(app, !drawerState(app).open, true);
			}
			if (action.dataset.arAction === 'close-sidebar' && mobileDrawerMedia.matches) {
				event.preventDefault();
				closeSidebar(app, true);
			}
			if (action.dataset.arAction === 'toggle-password') {
				const input = action.closest('.ar-password-field').querySelector('input');
				input.type = input.type === 'password' ? 'text' : 'password';
				action.setAttribute('aria-label', translateOwnedText(input.type === 'password' ? 'نمایش رمز' : 'پنهان کردن رمز'));
			}
		});
		app.addEventListener('keydown', (event) => {
			const state = drawerState(app); const sidebar = app.querySelector('.ar-sidebar');
			if (!state.open || !mobileDrawerMedia.matches || !sidebar) return;
			if (event.key === 'Escape') { event.preventDefault(); closeSidebar(app, true); return; }
			if (event.key !== 'Tab') return;
			const focusable = Array.from(sidebar.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')).filter((node) => !node.hidden);
			if (!focusable.length) { event.preventDefault(); sidebar.focus(); return; }
			const first = focusable[0]; const last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
			else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
		});
		const syncDrawerViewport = () => setSidebarOpen(app, drawerState(app).open, false);
		if (typeof mobileDrawerMedia.addEventListener === 'function') mobileDrawerMedia.addEventListener('change', syncDrawerViewport);
		else if (typeof mobileDrawerMedia.addListener === 'function') mobileDrawerMedia.addListener(syncDrawerViewport);
		syncDrawerViewport();
		window.addEventListener('arvan:session-expired', () => {
			toast('نشست شما پایان یافته است. به صفحه ورود هدایت می‌شوید.', 'danger');
			window.setTimeout(() => { window.location.href = window.ArvanResellerRuntime.loginUrl; }, 1800);
		}, { once: true });
	}

	window.ArvanUI = { escape, icon, mountIcons, persianDigits, decimal, money, exactMoney, date, statusLabel, status, errorMessage, pageHead, empty, error, loading, toast, modal, confirm, lineChart, t, translateOwnedText, translateDom, setText, applyLanguage, applyTheme, closeSidebar, wireLanguageControls, wireThemeControls, wireGlobalActions };
	document.addEventListener('DOMContentLoaded', () => {
		document.querySelectorAll('.arvan-reseller-app').forEach(wireGlobalActions);
	});
}());
