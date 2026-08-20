# PROJECT_SPEC.md

# Unified Salami & Flower Inventory System — V1

## 1. Project goal

Build one production-ready Laravel application containing two operationally separate modules:

1. **Salami Inventory System**
2. **Flower Inventory System**

The application must be useful for real daily warehouse work from V1.

It should not be a demo, but it also must not become a large ERP.

The core business question the system must always answer is:

> What was expected, what actually arrived, what was damaged, what entered stock, what left stock, and what remains now?

---

# 2. Product vision

One login.

One application shell.

One user system.

Two clearly separated operational areas.

After login, the user sees a module selector:

```text
اختر النظام

[ إدارة مخزن السلامي ]

[ إدارة مخزون الورد ]
```

When the user enters Salami, they should work only with Salami data and Salami-specific workflows.

When the user enters Flower, they should work only with Flower data and Flower-specific workflows.

---

# 3. Technology

Use:

```text
Laravel
Livewire
Blade
Tailwind CSS
MySQL
Alpine.js only where useful
```

The frontend must be custom.

Do not use Filament or another ready-made admin panel.

---

# 4. High-level route structure

```text
/dashboard

/salami/dashboard
/salami/products
/salami/suppliers
/salami/customers
/salami/receipts
/salami/inventory
/salami/invoices
/salami/waste
/salami/adjustments
/salami/reports

/flowers/dashboard
/flowers/products
/flowers/suppliers
/flowers/receipts
/flowers/inventory
/flowers/exits
/flowers/invoices
/flowers/waste
/flowers/adjustments
/flowers/reports
```

Routes should use:

```text
salami.*
flowers.*
```

---

# 5. Recommended application structure

```text
app/
├── Models/
├── Livewire/
│   ├── Shared/
│   ├── Salami/
│   │   ├── Dashboard/
│   │   ├── Products/
│   │   ├── Suppliers/
│   │   ├── Customers/
│   │   ├── Receipts/
│   │   ├── Inventory/
│   │   ├── Invoices/
│   │   └── Reports/
│   └── Flowers/
│       ├── Dashboard/
│       ├── Products/
│       ├── Suppliers/
│       ├── Receipts/
│       ├── Inventory/
│       ├── Exits/
│       ├── Invoices/
│       └── Reports/
├── Services/
│   ├── Inventory/
│   ├── Salami/
│   └── Flowers/
└── Support/
```

The exact folders may be adjusted to match the existing repository, but Salami and Flower code must remain clearly separated.

---

# 6. Authentication and users

V1 needs a simple role model:

```text
admin
employee
```

## Admin

Can:

- use both modules
- manage products
- manage suppliers
- manage Salami customers/stores
- receive stock
- create invoices
- register waste
- perform stock adjustments
- view reports
- cancel confirmed operations when allowed

## Employee

Can perform normal daily operations.

Do not build an enterprise-level permissions engine in V1.

---

# 7. Shared application shell

Use one shared layout with:

- top navigation
- current logged-in user
- logout
- active module name
- button/link to switch module

Sidebars are module-specific.

---

# 8. Shared inventory concepts

Both modules must support:

```text
expected_quantity
received_quantity
damaged_quantity
shortage
surplus
accepted_quantity
balance_before
balance_after
```

Calculations:

```text
shortage = max(expected_quantity - received_quantity, 0)

surplus = max(received_quantity - expected_quantity, 0)

accepted_quantity = received_quantity - damaged_quantity

balance_after = balance_before + accepted_quantity
```

Example:

```text
expected = 100
received = 94
damaged = 0

shortage = 6
accepted = 94
```

If previous stock is:

```text
20
```

new stock is:

```text
114
```

---

# 9. Inventory movement ledger

The application must maintain a trustworthy stock movement history.

Recommended concept:

```text
stock_movements

id
stockable_type
stockable_id
movement_type
quantity
balance_before
balance_after
reference_type
reference_id
notes
created_by
created_at
```

Use a polymorphic stockable relation if Salami products and Flower products are stored in separate tables.

Movement types:

```text
opening
receipt
sale
manual_exit
waste
adjustment_in
adjustment_out
reversal
```

The exact schema may be improved, but the movement ledger is mandatory.

`current_quantity` may be cached/stored on the product row for fast reads, but every change must also be reflected in the movement ledger.

---

# ======================================================
# SALAMI MODULE
# ======================================================

# 10. Salami module purpose

Salami workflow:

```text
Supplier
   ↓
Receiving
   ↓
Accepted stock
   ↓
Inventory
   ↓
Sale to store/customer
   ↓
Invoice
```

Salami includes stores/customers.

---

# 11. Salami dashboard

Display only operational information that helps daily work:

- number of Salami products
- total current quantity
- quantity received today
- quantity sold today
- receiving shortage today
- waste today
- invoice count today
- sales value today
- low-stock products
- latest 5 receipts
- latest 5 invoices

Avoid excessive charts.

---

# 12. Salami products

Suggested fields:

```text
id
name
code
unit
current_quantity
purchase_price
sale_price
minimum_quantity
notes
is_active
timestamps
```

Example:

```text
name: سلامي نوع A
code: SAL-001
unit: كرتونة
current_quantity: 114
purchase_price: 80
sale_price: 100
minimum_quantity: 10
```

The product edit screen must not allow direct editing of `current_quantity`.

---

# 13. Salami suppliers

Suggested fields:

```text
id
name
company_name nullable
phone nullable
country nullable
notes nullable
is_active
timestamps
```

A supplier should have a visible receipt history.

---

# 14. Salami customers/stores

Salami has stores/customers.

Suggested fields:

```text
id
name
contact_person nullable
phone nullable
area nullable
address nullable
notes nullable
is_active
timestamps
```

Invoices select a customer/store record.

Do not ask the employee to retype store names every time.

---

# 15. Salami receiving

Create a dedicated Livewire workflow:

**إضافة مخزون / استلام بضاعة**

Receipt header:

```text
receipt_number
supplier_id
supplier_invoice_number nullable
receipt_date
notes nullable
status
created_by
```

Receipt items:

```text
product_id
expected_quantity
received_quantity
damaged_quantity
accepted_quantity
purchase_price
notes nullable
```

The user may add one or multiple items in the same receipt.

For each item show live:

```text
الصنف
الوحدة

المتوقع
اللي وصل
النقص
الزيادة
التالف
المضاف للمخزون

الرصيد السابق
الرصيد الجديد
```

Example:

```text
سلامي نوع A
الوحدة: كرتونة

المتوقع: 100
اللي وصل: 94

النقص: 6
الزيادة: 0

التالف: 0
المضاف للمخزون: 94

الرصيد السابق: 20
الرصيد الجديد: 114
```

If:

```text
expected = 100
received = 105
```

show:

```text
shortage = 0
surplus = 5
```

Never show a negative shortage to the user.

---

# 16. Salami receiving validation

Validate:

```text
expected_quantity >= 0
received_quantity >= 0
damaged_quantity >= 0
damaged_quantity <= received_quantity
accepted_quantity >= 0
```

When confirming the receipt:

1. begin DB transaction
2. create receipt
3. create receipt items
4. lock each affected product row
5. read the real current balance
6. add `accepted_quantity` only
7. update product current quantity
8. create stock movement
9. commit

If any step fails, rollback everything.

---

# 17. Salami current inventory

Display:

```text
الصنف
الكود
الوحدة
الكمية الحالية
سعر البيع
الحد الأدنى
الحالة
```

Statuses:

```text
متوفر
مخزون منخفض
نفد
```

Provide:

- search
- filter by stock status
- sorting
- pagination

Clicking a product should allow viewing its full stock history.

---

# 18. Salami invoice workflow

A normal Salami sale should be performed through the invoice.

Do **not** deduct stock once from a separate sale exit screen and again from the invoice.

Invoice header:

```text
invoice_number
customer_id
invoice_date
payment_type
paid_amount
notes nullable
status
created_by
```

Invoice items:

```text
product_id
quantity
unit_price
line_total
```

Example:

```text
المحل: محل الأمل

سلامي نوع A
10 × 100 = 1,000

سلامي نوع B
5 × 90 = 450

الإجمالي = 1,450
```

Before confirmation, show current balance for each selected product.

---

# 19. Salami payment information

Keep V1 simple:

```text
cash
credit
partial
```

Invoice payment status:

```text
paid
partial
unpaid
```

Example:

```text
total = 1450
paid = 1000
remaining = 450
```

This is not a full accounting system.

---

# 20. Salami invoice confirmation

On confirmation:

1. start transaction
2. lock all affected Salami products
3. re-check available quantities
4. reject if any requested quantity is greater than stock
5. create/confirm invoice
6. create invoice items
7. deduct stock
8. create sale movements
9. commit

Do not allow negative stock.

---

# 21. Salami waste

Provide:

**تسجيل تالف**

Fields:

```text
product_id
quantity
reason
waste_date
notes nullable
created_by
```

Show:

```text
الرصيد السابق
التالف
الرصيد الجديد
```

Example:

```text
balance_before = 114
waste = 3
balance_after = 111
```

Prevent waste quantity from exceeding available stock.

---

# 22. Salami stock adjustment

Used for physical inventory corrections.

Example:

```text
system_quantity = 111
actual_quantity = 109
difference = -2
```

Create:

```text
adjustment_out = 2
```

If the actual quantity is higher, create `adjustment_in`.

Never directly overwrite stock without a movement record.

---

# 23. Salami reports

V1 reports:

1. Current inventory
2. Receipts
3. Receiving shortage/surplus
4. Waste
5. Sales
6. Invoices
7. Product movement history

Filters should be practical, especially:

- date range
- supplier
- customer/store
- product

---

# ======================================================
# FLOWER MODULE
# ======================================================

# 24. Flower module purpose

Flower workflow:

```text
Supplier
   ↓
Receiving
   ↓
Arrival damage check
   ↓
Accepted stock
   ↓
Inventory
   ↓
Sale / Exit
   ↓
Invoice when applicable
```

Flower is operationally separate from Salami.

---

# 25. Critical Flower rule

**There is no stores/customers module in Flower V1.**

Do not create Flower customers.

Do not add a "المحلات" page to Flower navigation.

If an invoice or exit needs a recipient, use:

```text
recipient_name nullable
```

as plain optional text.

---

# 26. Flower dashboard

Display:

- number of flower types
- total current quantity
- quantity received today
- accepted quantity today
- receiving shortage today
- damaged-at-arrival today
- waste-after-storage today
- quantity sold/exited today
- sales value today
- latest receipts
- latest invoices

---

# 27. Flower products

Suggested fields:

```text
id
name
code
color nullable
unit
current_quantity
purchase_price
sale_price
minimum_quantity nullable
grade nullable
notes nullable
is_active
timestamps
```

Example:

```text
name: جوري
color: أحمر
code: FL-001
unit: ربطة
current_quantity: 550
purchase_price: 15
sale_price: 22
```

Do not allow direct quantity editing from the product form.

---

# 28. Flower suppliers

Suggested fields:

```text
id
name
company_name nullable
phone nullable
country nullable
notes nullable
is_active
timestamps
```

---

# 29. Flower receiving

Create:

**استلام وإضافة مخزون الورد**

Receipt header:

```text
receipt_number
supplier_id
supplier_invoice_number nullable
receipt_date
notes nullable
status
created_by
```

Receipt items:

```text
flower_product_id
expected_quantity
received_quantity
damaged_quantity
accepted_quantity
purchase_price
notes nullable
```

Display:

```text
نوع الورد
الوحدة

المتوقع
اللي وصل
النقص
الزيادة
التالف عند الوصول
المضاف للمخزون

الرصيد السابق
الرصيد الجديد
```

Example:

```text
جوري أحمر
الوحدة: ربطة

المتوقع: 500
اللي وصل: 480

النقص: 20
الزيادة: 0

التالف عند الوصول: 30
المضاف للمخزون: 450

الرصيد السابق: 100
الرصيد الجديد: 550
```

---

# 30. Flower damaged-at-arrival rule

If:

```text
received = 480
damaged = 30
```

the stock operation should be:

```text
+450
```

Do not do:

```text
+480
-30
```

for the initial receiving process.

The 30 damaged units remain recorded on the receipt for reporting, but they never become sellable stock.

---

# 31. Flower current inventory

Display:

```text
نوع الورد
اللون
الوحدة
الكمية الحالية
دخل اليوم
خرج اليوم
تالف اليوم
سعر البيع
```

Example:

```text
جوري أحمر
الموجود: 435
دخل اليوم: 450
خرج اليوم: 100
تالف اليوم: 15
```

Include search, sorting, filters, and pagination.

---

# 32. Flower stock age

Flower is perishable.

Do not build a complex batch/FIFO engine in V1 unless required by existing business data.

However, preserve receipt timestamps so the system can report when quantities arrived.

At minimum support a report such as:

```text
جوري أحمر

دخل اليوم: 100
دخل أمس: 70
دخل قبل يومين: 20
```

The data model should not prevent adding FIFO/batches later.

---

# 33. Flower waste after storage

Provide:

**تسجيل تالف**

Fields:

```text
flower_product_id
quantity
reason
waste_date
notes nullable
created_by
```

Suggested reasons:

```text
ذبول
كسر
حرارة
تلف
جودة غير مناسبة
أخرى
```

Example:

```text
balance_before = 550
waste = 15
balance_after = 535
```

Prevent waste greater than available stock.

---

# 34. Flower exits

Provide a manual exit workflow only for non-invoice cases:

```text
gift
internal_use
sample
other
```

Fields:

```text
flower_product_id
quantity
exit_date
exit_type
recipient_name nullable
notes nullable
created_by
```

Do not use manual Flower Exit for a normal invoiced sale.

---

# 35. Flower invoices

Flower invoices do not use customer_id.

Suggested header:

```text
invoice_number
recipient_name nullable
invoice_date
payment_type
paid_amount
notes nullable
status
created_by
```

Items:

```text
flower_product_id
quantity
unit_price
line_total
```

Example:

```text
المستلم: اختياري

جوري أحمر
100 × 22 = 2,200

جوري أبيض
50 × 20 = 1,000

الإجمالي = 3,200
```

On confirmation, the invoice itself deducts stock.

---

# 36. Flower stock adjustment

Same concept as Salami.

Example:

```text
system_quantity = 435
actual_quantity = 430
difference = -5
```

Create an adjustment movement instead of silently changing quantity.

---

# 37. Flower reports

V1 reports:

1. Current stock
2. Receipts
3. Receiving shortage/surplus
4. Damaged at arrival
5. Waste after storage
6. Manual exits
7. Sales/invoices
8. Flower movement history
9. Receipts grouped by date / stock-age helper report

---

# ======================================================
# SHARED FINANCIAL / DOCUMENT RULES
# ======================================================

# 38. Invoice calculation

Use:

```text
line_total = quantity * unit_price
subtotal = sum(line_total)
total = subtotal - discount
remaining = total - paid_amount
```

Discount can remain optional/simple in V1.

Do not allow:

```text
paid_amount > total
```

---

# 39. Invoice lifecycle

Use:

```text
draft
confirmed
cancelled
```

Confirmed invoice cancellation must:

1. begin transaction
2. lock affected product rows
3. restore quantities
4. create reversal stock movements
5. set invoice status to cancelled
6. save cancellation reason
7. save cancelled_by
8. save cancelled_at
9. commit

Do not hard-delete confirmed invoices.

Prevent duplicate cancellation.

---

# 40. Receipt lifecycle

Recommended:

```text
draft
confirmed
cancelled
```

Do not hard-delete a confirmed receipt.

If receipt cancellation is implemented, it must safely reverse inventory and must be blocked when reversal would make later stock history inconsistent unless a safe reversal strategy is implemented.

Keep this behavior conservative in V1.

---

# 41. Database safety

Use:

- DB transactions
- `lockForUpdate()`
- foreign keys
- indexes
- server-side validation
- unique invoice/receipt numbers
- audit fields

Do not rely only on frontend checks.

---

# 42. Suggested tables

Final naming may follow existing repository conventions.

Suggested core tables:

```text
users

suppliers

salami_products
salami_customers

salami_receipts
salami_receipt_items

salami_invoices
salami_invoice_items

flower_products

flower_receipts
flower_receipt_items

flower_invoices
flower_invoice_items

flower_exits

stock_movements
stock_wastes
stock_adjustments
```

If a shared waste/adjustment table becomes awkward because products live in different tables, use clean polymorphic relations.

Avoid ambiguous integer foreign keys without type information.

---

# 43. Supplier strategy

Use a shared suppliers table if practical.

A supplier may optionally have a business scope:

```text
salami
flower
both
```

Do not duplicate the same real supplier unnecessarily.

If the existing data model makes separate suppliers safer, explain the tradeoff before changing the design.

---

# 44. Prices and numeric types

Use decimal for money.

Example:

```text
decimal(12,3)
```

Never use `float` for financial values.

Use suitable decimal quantities when the business may use fractional units.

---

# 45. Dashboard design principles

Dashboards are operational, not decorative.

Do not fill them with unnecessary charts.

Prefer:

- KPI cards
- latest records
- low stock alerts
- quick actions

---

# 46. UI requirements

The application must be:

- Arabic
- RTL
- responsive
- professional
- clear
- fast
- custom-designed
- easy for non-technical warehouse staff

Use Blade + Tailwind + Livewire.

Avoid generic admin-template styling.

Use consistent:

- cards
- tables
- filters
- form inputs
- status badges
- confirmation modals
- success/error feedback
- loading states

---

# 47. Salami navigation

```text
الرئيسية

الأصناف

إضافة مخزون
سجل الاستلامات

المخزون الحالي
حركة الأصناف
التالف
تسوية المخزون

الموردون
المحلات

فاتورة جديدة
سجل الفواتير

التقارير
```

---

# 48. Flower navigation

```text
الرئيسية

أنواع الورد

إضافة مخزون
سجل الاستلامات

المخزون الحالي
حركة الورد
التالف
تسوية المخزون

خروج الورد

الموردون

فاتورة جديدة
سجل الفواتير

التقارير
```

No stores/customers entry.

---

# 49. Invoice printing

Create separate print views if branding/layout differs:

```text
resources/views/salami/invoices/print.blade.php
resources/views/flowers/invoices/print.blade.php
```

Requirements:

- RTL
- A4-friendly
- print CSS
- business name
- invoice number
- date
- store/recipient
- item name
- unit
- quantity
- unit price
- line total
- total
- paid
- remaining
- notes
- signature area

---

# 50. Search, filtering, pagination

Use server-side search/filtering in Livewire.

Important lists:

- products
- suppliers
- Salami customers
- receipts
- invoices
- stock movements
- reports

Use pagination.

Avoid loading entire datasets.

---

# 51. Performance

Avoid N+1 queries.

Use eager loading.

Index commonly searched/filterable columns.

Do not recompute expensive dashboard data unnecessarily.

Keep V1 simple and measurable before adding caching.

---

# 52. Testing

Minimum critical tests:

## Shared

- stock cannot go below zero
- every stock change creates a movement
- transaction rollback preserves consistency
- cancellation creates correct reversal
- Salami and Flower data remain isolated

## Salami acceptance test

Initial:

```text
balance = 20
```

Receive:

```text
expected = 100
received = 94
damaged = 0
```

Expected:

```text
shortage = 6
accepted = 94
balance = 114
```

Sell:

```text
10
```

Expected:

```text
balance = 104
```

Waste:

```text
4
```

Expected:

```text
balance = 100
```

Movement history:

```text
Opening       +20     20
Receipt       +94    114
Sale          -10    104
Waste          -4    100
```

## Flower acceptance test

Initial:

```text
balance = 100
```

Receive:

```text
expected = 500
received = 480
damaged = 30
```

Expected:

```text
shortage = 20
accepted = 450
balance = 550
```

Sell:

```text
100
```

Expected:

```text
balance = 450
```

Waste later:

```text
15
```

Expected:

```text
balance = 435
```

Movement history:

```text
Opening       +100    100
Receipt       +450    550
Sale          -100    450
Waste          -15    435
```

---

# 53. Seed/demo data

For local development only, create seed data if useful:

- one admin
- one employee
- sample Salami products
- sample Flower products
- sample suppliers
- sample Salami stores/customers

Never seed Flower customers.

---

# 54. Explicitly out of V1 scope

Do not build unless later requested:

- customs workflow
- international shipment tracking
- vehicles
- drivers
- GPS
- multi-warehouse
- multi-branch
- payroll
- general ledger
- full accounting
- advanced POS
- advanced barcode
- mobile application
- external API
- AI
- advanced purchase orders
- complex approval chains
- complex distributor management

---

# 55. Implementation order

Use this order unless the existing repository makes another order safer:

1. inspect repository and versions
2. read AGENTS.md
3. verify auth
4. design final schema
5. migrations
6. models/relationships
7. shared stock movement service
8. module selector and layouts
9. Salami products
10. Salami suppliers
11. Salami customers
12. Salami receiving
13. Salami inventory
14. Salami waste/adjustments
15. Salami invoices
16. Flower products
17. Flower suppliers
18. Flower receiving
19. Flower inventory
20. Flower waste/adjustments
21. Flower manual exits
22. Flower invoices
23. dashboards
24. reports
25. printing
26. test suite
27. final consistency/security review

Do not implement all phases in one uncontrolled change.

---

# 56. Definition of done

V1 is considered usable only when:

- user can log in
- user can choose Salami or Flower
- module navigation is clearly separated
- Salami can receive stock with expected/received/shortage/damaged calculations
- Flower can receive stock with the same calculations
- accepted quantity updates stock correctly
- sale invoices deduct stock correctly
- Salami invoices use stores/customers
- Flower does not have customers
- waste deducts stock correctly
- adjustments create auditable stock corrections
- stock never goes negative
- invoice cancellation restores stock correctly
- stock movement history explains every balance change
- core reports work
- invoice print view works
- critical tests pass

The primary priorities are:

1. inventory correctness
2. operational usability
3. Salami/Flower separation
4. auditability
5. clean architecture
6. visual polish
