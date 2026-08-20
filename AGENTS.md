# AGENTS.md

## Project overview

This repository contains one Laravel application with two clearly separated business modules:

1. **Salami Inventory**
2. **Flower Inventory**

They share the same authentication, application shell, users, base UI components, and selected infrastructure services, but their business workflows and visible data must stay separated.

The system is a practical V1 for daily operations. Do not expand it into a full ERP unless explicitly requested.

---

## Mandatory stack

Use the stack already installed in the repository and keep compatibility with the current project versions.

Preferred application stack:

- Laravel
- Livewire
- Blade
- Tailwind CSS
- MySQL
- Alpine.js only when needed
- Laravel authentication

Do **not** introduce any of the following unless explicitly requested:

- Filament
- Laravel Nova
- Backpack
- React
- Vue
- Inertia
- SPA architecture
- AdminLTE or other admin templates

Do not upgrade Laravel, Livewire, Tailwind, PHP, Node packages, or unrelated dependencies without a clear requirement.

---

## Core architectural rule

The application is one Laravel project, but the two business modules must remain clearly separated.

Use namespaces/directories similar to:

```text
app/
├── Livewire/
│   ├── Salami/
│   └── Flowers/
├── Services/
│   ├── Salami/
│   ├── Flowers/
│   └── Inventory/
├── Models/
└── Support/
```

Routes must be grouped clearly:

```text
/salami/*
/flowers/*
```

Use route names:

```text
salami.*
flowers.*
```

Do not mix Flower UI logic inside Salami components or vice versa.

Shared logic is allowed only when it is genuinely domain-neutral, such as:

- stock movement recording
- authentication
- base layout components
- shared formatting helpers
- generic pagination/search helpers

---

## Business separation rules

### Salami module

Salami includes:

- products
- suppliers
- stores/customers
- receiving
- current inventory
- sales invoices
- waste
- stock adjustments
- reports

Salami sales are associated with a store/customer.

### Flower module

Flower includes:

- flower products/types
- suppliers
- receiving
- current inventory
- flower exits
- invoices
- waste
- stock adjustments
- reports

**Flower does not have a stores/customers module.**

Do not create:

- flower customers
- flower stores
- shops table for flower
- customer CRUD inside Flower

If a Flower invoice or exit needs a destination/recipient, use an optional plain text field such as:

```text
recipient_name
```

Do not introduce a Flower customer relation unless explicitly requested later.

---

## Inventory invariants

These rules are critical and must never be bypassed.

1. Product quantity must never become negative.
2. Do not directly edit stock quantity from product edit forms.
3. Every stock-changing operation must create a stock movement.
4. Every stock-changing operation must run inside a database transaction.
5. Use `lockForUpdate()` when reading a product row before changing its stock.
6. Re-check stock on the server inside the transaction.
7. Never trust Livewire state as the final source of truth.
8. Do not perform the same stock deduction twice.
9. Confirmed financial/inventory records must not be hard-deleted.
10. Cancellation must create reversal movements and preserve history.

Allowed stock changes:

```text
receipt          +
sale             -
manual_exit      -
waste            -
adjustment_in    +
adjustment_out   -
reversal         +/-
opening          +
```

---

## Receiving calculations

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

Use:

```text
shortage = max(expected_quantity - received_quantity, 0)
surplus = max(received_quantity - expected_quantity, 0)
accepted_quantity = received_quantity - damaged_quantity
balance_after = balance_before + accepted_quantity
```

Validation:

```text
expected_quantity >= 0
received_quantity >= 0
damaged_quantity >= 0
damaged_quantity <= received_quantity
accepted_quantity >= 0
```

### Important receiving rule

Damaged quantity at receiving does **not** enter sellable stock.

Example:

```text
expected = 100
received = 94
damaged = 4
accepted = 90
```

Only `90` is added to inventory.

---

## Salami example

Before receiving:

```text
Salami Type A
balance = 20
```

Receiving:

```text
expected = 100
received = 94
shortage = 6
damaged = 0
accepted = 94
```

Result:

```text
balance_before = 20
balance_after = 114
```

Then sale:

```text
quantity = 10
balance_after = 104
```

Then waste:

```text
quantity = 4
balance_after = 100
```

Expected movement history:

```text
Opening       +20     20
Receipt       +94    114
Sale          -10    104
Waste          -4    100
```

---

## Flower example

Before receiving:

```text
Red Rose
balance = 100
```

Receiving:

```text
expected = 500
received = 480
shortage = 20
damaged = 30
accepted = 450
```

Result:

```text
balance_before = 100
balance_after = 550
```

Then sale:

```text
quantity = 100
balance_after = 450
```

Then later waste:

```text
quantity = 15
balance_after = 435
```

Expected movement history:

```text
Opening       +100    100
Receipt       +450    550
Sale          -100    450
Waste          -15    435
```

---

## Invoice rules

### Salami

A sale invoice is the stock-out operation for a normal sale.

Do not create a separate sale stock-out and then deduct again when the invoice is confirmed.

Salami invoices use a customer/store relation.

### Flower

A Flower sales invoice also deducts stock itself.

Do not deduct stock using Flower Exit and then again through the invoice.

Flower invoice recipient is optional plain text:

```text
recipient_name
```

### Invoice states

Use a simple lifecycle:

```text
draft
confirmed
cancelled
```

When cancelling a confirmed invoice:

- do not delete it
- restore stock
- create reversal stock movements
- save cancellation reason
- save cancelled_by
- save cancelled_at
- prevent repeated cancellation

---

## Money and quantities

Never use `float` for money.

Use decimal columns, for example:

```text
decimal(12, 3)
```

Use a quantity type that supports fractional values if units may require decimals.

Avoid rounding inconsistencies. Keep calculation logic centralized.

---

## Livewire rules

Livewire components are responsible for:

- UI state
- form interaction
- validation feedback
- search/filter/pagination
- calling domain services

Livewire components should **not** contain large stock or invoice workflows directly.

Complex operations belong in Services/Actions.

Prefer small, focused components over one giant component.

---

## Service layer rules

Use services/actions for operations such as:

- receiving stock
- confirming invoices
- cancelling invoices
- registering waste
- stock adjustment
- manual flower exit
- stock movement creation

Services must be deterministic, transactional, and testable.

---

## Database and model rules

Use:

- foreign keys
- indexes on frequently searched columns
- explicit relationships
- eager loading to avoid N+1
- pagination for large tables

Index fields such as:

```text
product_id
supplier_id
customer_id
invoice_number
receipt_number
status
created_at
```

If a shared `stock_movements` table references both Salami and Flower products, use a clean polymorphic relation rather than an ambiguous raw `product_id`.

Do not create a fragile design where identical numeric IDs from different product tables can collide.

---

## Auditability

Stock and financial operations should preserve who performed them.

Where relevant include:

```text
created_by
updated_by
cancelled_by
cancelled_at
cancellation_reason
created_at
updated_at
```

Do not hard-delete confirmed invoices, confirmed receipts, or stock movements.

---

## UI/UX requirements

The UI must be:

- Arabic
- RTL
- professional
- clean
- fast
- responsive
- practical for warehouse employees
- custom Blade/Livewire/Tailwind UI

Do not make it look like a generic admin template.

Keep screens simple and task-oriented.

The user should always be able to see:

- current balance
- entered quantity
- calculated difference
- resulting balance

when performing stock-changing actions.

---

## Navigation

### Salami sidebar

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

### Flower sidebar

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

There is no "المحلات" entry in Flower.

---

## Printing

Use Blade templates for invoice printing.

Requirements:

- RTL
- A4-friendly
- clean printable layout
- invoice number
- date
- recipient/store
- items
- unit
- quantity
- unit price
- line total
- total
- paid
- remaining
- notes/signature area

---

## Testing requirements

Any change to inventory behavior must have tests.

At minimum cover:

- receipt adds accepted quantity only
- shortage calculation
- surplus calculation
- damaged-at-receiving exclusion
- sale stock deduction
- preventing overselling
- waste deduction
- adjustment in/out
- invoice cancellation stock restoration
- rollback if any transactional step fails
- Salami and Flower data separation

Run the smallest relevant test suite after each implementation step, and run the broader suite before declaring a task complete.

---

## Scope boundaries for V1

Do not add unless explicitly requested:

- customs
- international shipping workflow
- GPS
- vehicles
- drivers
- advanced distributors
- payroll
- general ledger
- full accounting
- multi-warehouse
- multi-branch
- advanced barcode/POS
- mobile app
- external API
- AI
- advanced purchase orders
- complex approval workflow

---

## Working style for Codex

Before modifying code:

1. Inspect the existing repository.
2. Identify Laravel/Livewire/Tailwind versions.
3. Read existing conventions and reusable components.
4. Explain the implementation plan briefly.
5. Make the smallest coherent change.
6. Run relevant tests/commands.
7. Fix failures before moving on.

Do not rewrite unrelated code.

Do not rename or reorganize unrelated files just for style.

Do not introduce abstractions with no current need.

When requirements conflict, prioritize:

1. inventory correctness
2. data separation between Salami and Flower
3. auditability
4. simplicity
5. UI polish

If a requirement is unclear, choose the simplest implementation that preserves inventory correctness and does not expand V1 scope.
