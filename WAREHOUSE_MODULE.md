# فاز ۵ - انبارداری (Warehouse Module)

## نمای کلی

ماژول انبارداری یک سیستم جامع برای مدیریت موجودی و انبارهاست که با سیستم حسابداری یکپارچه است.

## ویژگی‌های اصلی

### ۱. مدیریت انبارها
- تعریف چند انبار
- انتخاب انبار پیش‌فرض
- موقعیت‌های انبار

**کلاس:** `WebinoCRM_Accounting_Warehouse`

```php
// جدول: webinocrm_accounting_warehouses
- id (Primary Key)
- name (نام انبار)
- code (کد انبار)
- description (توضیحات)
- location (موقعیت)
- is_default (آیا پیش‌فرض است)
- is_active (فعال/غیرفعال)
- created_at, updated_at
```

---

### ۲. رسید کالا (Inbound)
تسجیل ورود کالا به انبار از خریدها یا بازگشت‌ها.

**کلاس:** `WebinoCRM_Accounting_Warehouse_Inbound`

```php
// جدول: webinocrm_accounting_warehouse_inbound
- id
- warehouse_id (انبار)
- inbound_no (شماره رسید - REC-00001)
- inbound_date (تاریخ رسید)
- reference_type (purchase_invoice, purchase_return, transfer, adjustment)
- reference_id (شناسه سند مرجع)
- status (draft, received, posted)
- created_by, created_at, updated_at

// جدول: webinocrm_accounting_warehouse_inbound_items
- id
- inbound_id
- product_id (کالا)
- quantity_received (تعداد دریافت‌شده)
- unit_id (واحد اندازه‌گیری)
- unit_price (قیمت واحد)
- location (مکان انبار)
```

**استفاده:**
```php
$inbound_id = WebinoCRM_Accounting_Warehouse_Inbound::create([
    'warehouse_id' => 1,
    'inbound_date' => '1403-06-15',
    'reference_type' => 'purchase_invoice',
    'reference_id' => $invoice_id,
]);

// افزودن کالاها
WebinoCRM_Accounting_Warehouse_Inbound::add_item($inbound_id, [
    'product_id' => 5,
    'quantity_received' => 10,
    'unit_id' => 1,
    'unit_price' => 50000,
]);

// ثبت رسید (ایجاد تراکنش‌ها)
WebinoCRM_Accounting_Warehouse_Inbound::post($inbound_id);
```

---

### ۳. حواله کالا (Outbound)
صدور کالا از انبار برای فروش یا انتقال.

**کلاس:** `WebinoCRM_Accounting_Warehouse_Outbound`

```php
// جدول: webinocrm_accounting_warehouse_outbound
- id
- warehouse_id (انبار مبدا)
- outbound_no (شماره حواله - OUT-00001)
- outbound_date (تاریخ حواله)
- reference_type (sales_invoice, sales_return, transfer, adjustment)
- reference_id (شناسه سند مرجع)
- destination_warehouse_id (انبار مقصد برای انتقال)
- status (draft, shipped, posted)
- created_by, created_at, updated_at

// جدول: webinocrm_accounting_warehouse_outbound_items
- id
- outbound_id
- product_id (کالا)
- quantity_shipped (تعداد صادر‌شده)
- unit_id (واحد اندازه‌گیری)
- unit_price (قیمت واحد)
- location (مکان انبار)
```

**استفاده:**
```php
$outbound_id = WebinoCRM_Accounting_Warehouse_Outbound::create([
    'warehouse_id' => 1,
    'outbound_date' => '1403-06-16',
    'reference_type' => 'sales_invoice',
    'reference_id' => $invoice_id,
]);

// افزودن کالاها (خودکار چک موجودی)
WebinoCRM_Accounting_Warehouse_Outbound::add_item($outbound_id, [
    'product_id' => 5,
    'quantity_shipped' => 8,
    'unit_id' => 1,
]);

// ثبت حواله
WebinoCRM_Accounting_Warehouse_Outbound::post($outbound_id);
```

---

### ۴. تراکنش‌های موجودی (Transactions)
ثبت تمام حرکات موجودی (ورود/خروج).

**کلاس:** `WebinoCRM_Accounting_Warehouse_Transaction`

```php
// جدول: webinocrm_accounting_warehouse_transactions
- id
- warehouse_id (انبار)
- product_id (کالا)
- transaction_type (inbound, outbound)
- quantity (مقدار)
- unit_id (واحد)
- document_type (goods_receipt, invoice, transfer, adjustment, audit)
- document_id (شناسه سند)
- reference_no (شماره مرجع)
- transaction_date (تاریخ تراکنش)
- created_by
```

**استفاده:**
```php
$transaction_id = WebinoCRM_Accounting_Warehouse_Transaction::create([
    'warehouse_id' => 1,
    'product_id' => 5,
    'transaction_type' => 'inbound',
    'quantity' => 10,
    'unit_id' => 1,
    'document_type' => 'goods_receipt',
    'document_id' => $inbound_id,
]);
```

---

### ۵. موجودی فعلی (Stock)
ردیابی موجودی درحال‌حاضر هر کالا در هر انبار.

**کلاس:** `WebinoCRM_Accounting_Warehouse_Stock`

```php
// جدول: webinocrm_accounting_warehouse_stock
- id
- warehouse_id (انبار)
- product_id (کالا)
- quantity (تعداد فعلی)
- unit_id (واحد)
- valuation_method (fifo, lifo, average)
- updated_at (آخرین به‌روزرسانی)
```

**استفاده:**
```php
// دریافت موجودی کالا
$stock = WebinoCRM_Accounting_Warehouse_Stock::get(1, 5); // warehouse_id=1, product_id=5
echo $stock->quantity; // 42

// لیست موجودی انبار
$stocks = WebinoCRM_Accounting_Warehouse_Stock::get_all(1, [
    'only_active' => true,
    'only_counted' => true,
    'limit' => 50,
]);

// گزارش ارزش موجودی
$report = WebinoCRM_Accounting_Warehouse_Stock::get_stock_value_report(1);
echo $report['total_value']; // ارزش کل موجودی

// گزارش حرکت کالا
$movement = WebinoCRM_Accounting_Warehouse_Stock::get_movement_report(1, 5, [
    'from_date' => '1403-01-01',
    'to_date' => '1403-06-30',
]);
// موارد با موجودی کم‌تر از نقطه سفارش
$low_items = WebinoCRM_Accounting_Warehouse_Stock::get_low_stock_items(1);
```

---

### ۶. انبارگردانی (Audit)
شمارش فیزیکی موجودی و هماهنگ‌سازی با سیستم.

**کلاس:** `WebinoCRM_Accounting_Warehouse_Audit`

```php
// جدول: webinocrm_accounting_warehouse_audits
- id
- warehouse_id (انبار)
- audit_no (شماره انبارگردانی - AUD-00001)
- audit_date (تاریخ شمارش)
- fiscal_year_id (سال مالی)
- status (draft, in_progress, completed, posted)
- valuation_method (fifo, lifo, average - روش ارزیابی)
- total_discrepancy_amount (کل اختلاف ارزشی)
- created_by, posted_by, posted_at

// جدول: webinocrm_accounting_warehouse_audit_items
- id
- audit_id
- product_id (کالا)
- unit_id
- system_quantity (موجودی سیستمی)
- physical_quantity (موجودی فیزیکی)
- variance_quantity (اختلاف مقدار)
- variance_amount (اختلاف ارزش)
- variance_type (surplus/shortage - مازاد/کمبود)
```

**استفاده:**
```php
// ایجاد انبارگردانی
$audit_id = WebinoCRM_Accounting_Warehouse_Audit::create([
    'warehouse_id' => 1,
    'fiscal_year_id' => $fiscal_year_id,
    'audit_date' => '1403-06-30',
    'valuation_method' => 'fifo',
]);

// بارگذاری موجودی سیستمی
$count = WebinoCRM_Accounting_Warehouse_Audit::initialize_items($audit_id, 1);

// ثبت شمارش فیزیکی
WebinoCRM_Accounting_Warehouse_Audit::record_item_count($audit_id, [
    'product_id' => 5,
    'physical_quantity' => 38, // شمارش شده
    'unit_price' => 50000,
]); // سیستم خودکار اختلاف را محاسبه می‌کند

// تکمیل انبارگردانی
WebinoCRM_Accounting_Warehouse_Audit::complete($audit_id);

// ثبت انبارگردانی (ایجاد سند حسابداری برای اختلافات)
WebinoCRM_Accounting_Warehouse_Audit::post($audit_id);
```

---

## یکپارچگی با فاکتور

**کلاس:** `WebinoCRM_Warehouse_Invoice_Integration`

زمانی که فاکتور فروش تأیید می‌شود، خودکار:
1. یک حواله (Outbound) ایجاد می‌شود
2. کالاهای فاکتور به آن افزوده می‌شوند
3. حواله خودکار ثبت می‌شود

```php
// hooks:
add_action('webinocrm_invoice_confirmed', function($invoice_id) {
    // حواله خودکار
});
```

---

## AJAX Handlers

جمیع عملیات از طریق AJAX قابل دسترسی هستند:

### وارهاوس
```
webino_get_warehouses
webino_create_warehouse
webino_update_warehouse
webino_delete_warehouse
```

### رسید (Inbound)
```
webino_create_inbound
webino_get_inbound
webino_add_inbound_item
webino_post_inbound
```

### حواله (Outbound)
```
webino_create_outbound
webino_get_outbound
webino_add_outbound_item
webino_post_outbound
```

### انبارگردانی (Audit)
```
webino_create_audit
webino_get_audit
webino_initialize_audit_items
webino_record_audit_item
webino_complete_audit
webino_post_audit
```

### گزارش
```
webino_get_warehouse_stock
webino_get_stock_report
webino_get_movement_report
webino_get_low_stock_items
```

**مثال استفاده:**
```javascript
jQuery.ajax({
    url: webinocrm_ajax_obj.ajax_url,
    type: 'POST',
    data: {
        action: 'webino_create_warehouse',
        nonce: webinocrm_ajax_obj.nonce,
        data: JSON.stringify({
            name: 'انبار اصلی',
            code: 'WH-001',
            location: 'طبقه ۲',
            is_default: 1,
        })
    },
    success: function(response) {
        if (response.success) {
            console.log('انبار ایجاد شد:', response.data.warehouse_id);
        }
    }
});
```

---

## نکات مهم

### کنترل موجودی
- هنگام افزودن کالا به حواله، سیستم خودکار موجودی را بررسی می‌کند
- اگر موجودی ناکافی باشد، خطا برمی‌گرداند

### نمبرینگ خودکار
- رسیدها: `REC-00001`, `REC-00002`, ...
- حواله‌ها: `OUT-00001`, `OUT-00002`, ...
- انبارگردانی‌ها: `AUD-00001`, `AUD-00002`, ...

### ایجاد سند حسابداری
- هنگام ثبت انبارگردانی سند خودکار برای اختلافات موجودی ایجاد می‌شود
- حساب‌های مرتبط در تنظیمات حسابداری قابل تعریف هستند

### استعلام‌های بهینه
- تمام جدول‌ها indexed هستند برای عملکرد بهتر
- گزارش‌ها می‌توانند روی پنجره‌های زمانی بزرگ اجرا شوند

---

## مراحل بعدی

پس از پایان‌یافتن فاز ۵ (انبارداری)، فازهای زیر پیش‌رو هستند:

- **فاز ۶:** گزارشات و چاپ پیشرفته
- **فاز ۷:** سامانه مودیان و امور مالیاتی
- **فاز ۸:** پروژه، کاربران، داشبورد، حقوق
- **فاز ۹:** API، چندارزی، پیامک، فرم‌ساز، بارکد
- **فاز ۱۰:** رفع نقص و بهینه‌سازی
