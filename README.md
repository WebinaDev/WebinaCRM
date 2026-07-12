# WebinoCRM

## معرفی
`webinocrm` هسته CRM و Marketplace در معماری سه‌ریپویی وبینا است. این پلاگین مدیریت کامل CRM، سرویس‌های عملیاتی، مدیریت انتشار ماژول‌ها و آپدیت هسته Dashboard/CRM را ارائه می‌دهد.

## مسئولیت در معماری سه‌ریپویی
- مالک کاتالوگ Marketplace و release metadata
- ساخت/انتشار پکیج ماژول‌ها و ارائه download token
- مدیریت entitlement/license برای دامنه‌ها
- ارائه API برای نصب ماژول روی Dashboard
- ارائه Core Update برای:
  - `webino-dashboard`
  - `webinocrm`

## امکانات اصلی
- CRM کامل: لید، مشتری، پروژه، قرارداد، فاکتور، تیکت، وظایف، گزارش‌ها
- ابزارهای همکاری: chat، documents، time-tracking
- مدیریت Marketplace: دسته‌بندی، ماژول، ریلیز، سفارش، Gitea settings
- سرویس‌های پیامکی/مدیرپیامک و عملیات مرتبط
- REST API کامل با نگاشت service/action
- Core updater امن (lock + backup + rollback + validation)

## معماری و اجزای کلیدی
- entry plugin: `webinocrm.php`
- bootstrap اصلی: `includes/class-webinocrm.php`
- REST registry/routes:
  - `includes/rest/class-rest-registry.php`
  - `includes/rest/class-rest-routes.php`
  - `includes/rest/controllers/class-rest-crm-controllers.php`
- service loader:
  - `includes/services/class-services-loader.php`
- marketplace:
  - `includes/class-marketplace-manager.php`
  - `includes/class-marketplace-api.php`
  - `includes/services/class-marketplace-service.php`
- core updater:
  - `includes/class-webinocrm-core-updater.php`
  - `includes/services/class-core-update-service.php`

## مسیرهای REST مهم
- Marketplace (customer-facing catalog):
  - `GET /wp-json/webinocrm/v1/marketplace/catalog/categories?domain=...`
  - `GET /wp-json/webinocrm/v1/marketplace/catalog/modules?domain=...`
  - `POST /wp-json/webinocrm/v1/marketplace/purchase/init`
  - `POST /wp-json/webinocrm/v1/marketplace/purchase/verify`
  - `POST /wp-json/webinocrm/v1/marketplace/download-token`
  - `GET /wp-json/webinocrm/v1/marketplace/download?token=...`
- Core package:
  - `GET /wp-json/webinocrm/v1/marketplace/core/check`
  - `POST /wp-json/webinocrm/v1/marketplace/core/download-token`
- CRM core update:
  - `GET /wp-json/webinocrm/v1/core/update-status`
  - `POST /wp-json/webinocrm/v1/core/update`

## Service Actions مهم
- `webinocrm_check_marketplace_release`
- `check_crm_core_update`
- `run_crm_core_update`
- `webinocrm_check_crm_core_update`
- `webinocrm_run_crm_core_update`

## جریان Marketplace Release
1. تعریف ماژول و repo (Gitea) در CRM
2. ثبت release draft و آپلود/resolve ZIP
3. اجرای check قرارداد ZIP
4. publish release
5. مصرف release از Dashboard با download-token

## جریان Core Update (CRM)
1. check وضعیت نسخه
2. صدور token و دانلود package
3. validate package (`webinocrm.php`, `includes/`, `client/`)
4. backup و اعمال update
5. rollback خودکار در خطا

## پیش‌نیازها
- WordPress + PHP سازگار
- دسترسی outbound به Gitea
- تنظیم صحیح `gitea_base_url`, `gitea_org`, `gitea_api_token`
- لایسنس معتبر برای دامنه

## اسناد تکمیلی
- `docs/module-release-pipeline.md`
- `docs/gitea-marketplace.md`
- `docs/rest-api.md`
- `docs/WEBINODASHBOARD-SPLIT.md`

## عیب‌یابی سریع
- اگر publish release خطا می‌دهد، ابتدا `webinocrm_check_marketplace_release` را اجرا کنید.
- اگر download token صادر نمی‌شود، license domain و entitlement را بررسی کنید.
- اگر core update شکست می‌خورد، writable بودن مسیر پلاگین و فایل backup را بررسی کنید.

## نکات امنیتی
- API tokenهای Gitea فقط در CRM نگه‌داری شوند.
- download tokenها کوتاه‌عمر باشند.
- endpointهای مدیریتی با `manage_options` و مسیر permission map محافظت شوند.
