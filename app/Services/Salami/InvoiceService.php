<?php

namespace App\Services\Salami;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Exceptions\Inventory\StockMutationException;
use App\Exceptions\Salami\InvoiceException;
use App\Models\SalamiCustomer;
use App\Models\SalamiInvoice;
use App\Models\SalamiInvoiceItem;
use App\Models\SalamiProduct;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Support\InvoiceAmounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * @param  array{customer_id: int|string, invoice_date: string, payment_type: string, discount_amount?: string|null, paid_amount?: string|null, notes?: string|null}  $attributes
     * @param  list<array<string, mixed>>  $items
     */
    public function createDraft(array $attributes, array $items, User $createdBy): SalamiInvoice
    {
        return DB::transaction(function () use ($attributes, $items, $createdBy): SalamiInvoice {
            $customer = $this->activeCustomer($attributes['customer_id']);
            $prepared = $this->prepareItems($items, $attributes);
            $invoice = SalamiInvoice::query()->create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'customer_id' => $customer->getKey(),
                'invoice_date' => $attributes['invoice_date'],
                'payment_type' => $attributes['payment_type'],
                ...$this->amountAttributes($prepared['amounts']),
                'status' => InvoiceStatus::Draft,
                'notes' => $this->nullableText($attributes['notes'] ?? null),
                'created_by' => $createdBy->getKey(),
            ]);

            $this->createItems($invoice, $prepared['items']);

            return $invoice->fresh(['customer', 'items.product']);
        });
    }

    /**
     * @param  array{customer_id: int|string, invoice_date: string, payment_type: string, discount_amount?: string|null, paid_amount?: string|null, notes?: string|null}  $attributes
     * @param  list<array<string, mixed>>  $items
     */
    public function updateDraft(SalamiInvoice $invoice, array $attributes, array $items, User $updatedBy): SalamiInvoice
    {
        return DB::transaction(function () use ($invoice, $attributes, $items, $updatedBy): SalamiInvoice {
            $lockedInvoice = $this->lockDraft($invoice);
            $customer = $this->activeCustomer($attributes['customer_id']);
            $prepared = $this->prepareItems($items, $attributes);

            $lockedInvoice->update([
                'customer_id' => $customer->getKey(),
                'invoice_date' => $attributes['invoice_date'],
                'payment_type' => $attributes['payment_type'],
                ...$this->amountAttributes($prepared['amounts']),
                'notes' => $this->nullableText($attributes['notes'] ?? null),
                'updated_by' => $updatedBy->getKey(),
            ]);
            $lockedInvoice->items()->delete();
            $this->createItems($lockedInvoice, $prepared['items']);

            return $lockedInvoice->fresh(['customer', 'items.product']);
        });
    }

    public function deleteDraft(SalamiInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $lockedInvoice = $this->lockDraft($invoice);
            $lockedInvoice->items()->delete();
            $lockedInvoice->delete();
        });
    }

    public function confirm(SalamiInvoice $invoice, User $confirmedBy): SalamiInvoice
    {
        return DB::transaction(function () use ($invoice, $confirmedBy): SalamiInvoice {
            $lockedInvoice = SalamiInvoice::query()
                ->with('customer')
                ->lockForUpdate()
                ->find($invoice->getKey());

            if (! $lockedInvoice instanceof SalamiInvoice) {
                throw new InvoiceException('الفاتورة لم تعد موجودة.');
            }

            if ($lockedInvoice->status !== InvoiceStatus::Draft) {
                throw new InvoiceException('يمكن اعتماد الفواتير المسودة فقط.');
            }

            $this->assertActiveCustomer($lockedInvoice->customer);
            $items = SalamiInvoiceItem::query()
                ->with('product')
                ->where('invoice_id', $lockedInvoice->getKey())
                ->orderBy('product_id')
                ->get();

            if ($items->isEmpty()) {
                throw new InvoiceException('أضف صنفًا واحدًا على الأقل قبل اعتماد الفاتورة.');
            }

            if ($items->pluck('product_id')->unique()->count() !== $items->count()) {
                throw new InvoiceException('لا يمكن تكرار الصنف في الفاتورة نفسها.');
            }

            $amounts = InvoiceAmounts::calculate(
                $items->map(fn (SalamiInvoiceItem $item): array => [
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ])->all(),
                $lockedInvoice->discount_amount,
                $lockedInvoice->paid_amount,
                $lockedInvoice->payment_type,
            );

            foreach ($items as $index => $item) {
                $product = $item->product;

                if (! $product instanceof SalamiProduct || ! $product->is_active) {
                    throw new InvoiceException('كل بند في الفاتورة يجب أن يشير إلى صنف سلامي نشط.');
                }

                $calculatedItem = $amounts['items'][$index];
                $item->update([
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'quantity' => $calculatedItem['quantity'],
                    'unit_price' => $calculatedItem['unit_price'],
                    'line_total' => $calculatedItem['line_total'],
                ]);

                try {
                    $this->stockService->sale(
                        $product,
                        $calculatedItem['quantity'],
                        $confirmedBy,
                        $item,
                        "بيع عبر الفاتورة {$lockedInvoice->invoice_number}",
                    );
                } catch (InsufficientStockException $exception) {
                    throw new InvoiceException("المخزون غير كافٍ للصنف {$product->name}. المتاح حاليًا: {$exception->available}.", previous: $exception);
                }
            }

            $lockedInvoice->update([
                ...$this->amountAttributes($amounts),
                'status' => InvoiceStatus::Confirmed,
                'confirmed_by' => $confirmedBy->getKey(),
                'confirmed_at' => now(),
            ]);

            return $lockedInvoice->fresh(['customer', 'items.product', 'items.stockMovement', 'createdBy', 'confirmedBy']);
        });
    }

    public function cancel(SalamiInvoice $invoice, User $cancelledBy, string $reason): SalamiInvoice
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['cancellation_reason' => 'سبب الإلغاء مطلوب.']);
        }

        return DB::transaction(function () use ($invoice, $cancelledBy, $reason): SalamiInvoice {
            $lockedInvoice = SalamiInvoice::query()->lockForUpdate()->find($invoice->getKey());

            if (! $lockedInvoice instanceof SalamiInvoice) {
                throw new InvoiceException('الفاتورة لم تعد موجودة.');
            }

            if ($lockedInvoice->status !== InvoiceStatus::Confirmed) {
                throw new InvoiceException('يمكن إلغاء الفاتورة المعتمدة فقط.');
            }

            $items = SalamiInvoiceItem::query()
                ->where('invoice_id', $lockedInvoice->getKey())
                ->orderBy('product_id')
                ->get();

            foreach ($items as $item) {
                $movement = StockMovement::query()
                    ->where('reference_type', $item->getMorphClass())
                    ->where('reference_id', $item->getKey())
                    ->where('movement_type', 'sale')
                    ->lockForUpdate()
                    ->first();

                if (! $movement instanceof StockMovement) {
                    throw new InvoiceException("تعذر العثور على حركة البيع الخاصة ببند {$item->product_name}.");
                }

                try {
                    $this->stockService->reverse(
                        $movement,
                        $cancelledBy,
                        "إلغاء الفاتورة {$lockedInvoice->invoice_number}: {$reason}",
                    );
                } catch (StockMutationException $exception) {
                    throw new InvoiceException('تعذر إلغاء الفاتورة لأن إحدى حركات البيع تم عكسها سابقًا.', previous: $exception);
                }
            }

            SalamiInvoice::allowConfirmedMutation(function () use ($lockedInvoice, $cancelledBy, $reason): void {
                $lockedInvoice->update([
                    'status' => InvoiceStatus::Cancelled,
                    'cancelled_by' => $cancelledBy->getKey(),
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);
            });

            return $lockedInvoice->fresh(['customer', 'items.product', 'createdBy', 'confirmedBy', 'cancelledBy']);
        });
    }

    private function lockDraft(SalamiInvoice $invoice): SalamiInvoice
    {
        $lockedInvoice = SalamiInvoice::query()->lockForUpdate()->find($invoice->getKey());

        if (! $lockedInvoice instanceof SalamiInvoice) {
            throw new InvoiceException('الفاتورة لم تعد موجودة.');
        }

        if ($lockedInvoice->status !== InvoiceStatus::Draft) {
            throw new InvoiceException('الفاتورة المعتمدة للعرض فقط.');
        }

        return $lockedInvoice;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array{discount_amount?: string|null, paid_amount?: string|null, payment_type: string}  $attributes
     * @return array{items: list<array{product: SalamiProduct, quantity: string, unit_price: string, line_total: string}>, amounts: array{subtotal_amount: string, discount_amount: string, total_amount: string, paid_amount: string, remaining_amount: string, payment_status: PaymentStatus, items: list<array{quantity: string, unit_price: string, line_total: string}>}}
     */
    private function prepareItems(array $items, array $attributes): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'أضف صنفًا واحدًا على الأقل إلى الفاتورة.']);
        }

        $productIds = [];
        $products = [];
        $amountItems = [];

        foreach ($items as $index => $item) {
            $productId = $item['product_id'] ?? null;

            if (! is_numeric($productId)) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'اختر صنف السلامي.']);
            }

            $productId = (int) $productId;

            if (in_array($productId, $productIds, true)) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'لا يمكن تكرار الصنف في الفاتورة نفسها.']);
            }

            $productIds[] = $productId;
            $product = SalamiProduct::query()->find($productId);

            if (! $product instanceof SalamiProduct || ! $product->is_active) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => 'الصنف غير متاح للبيع.']);
            }

            $products[] = $product;
            $amountItems[] = [
                'quantity' => $item['quantity'] ?? '0',
                'unit_price' => $item['unit_price'] ?? '0',
            ];
        }

        $amounts = InvoiceAmounts::calculate(
            $amountItems,
            $attributes['discount_amount'] ?? '0',
            $attributes['paid_amount'] ?? '0',
            $attributes['payment_type'],
        );
        $preparedItems = [];

        foreach ($products as $index => $product) {
            $preparedItems[] = [
                'product' => $product,
                ...$amounts['items'][$index],
            ];
        }

        return ['items' => $preparedItems, 'amounts' => $amounts];
    }

    /**
     * @param  list<array{product: SalamiProduct, quantity: string, unit_price: string, line_total: string}>  $items
     */
    private function createItems(SalamiInvoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $product = $item['product'];
            $invoice->items()->create([
                'product_id' => $product->getKey(),
                'product_name' => $product->name,
                'unit' => $product->unit,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
            ]);
        }
    }

    /**
     * @param  array{subtotal_amount: string, discount_amount: string, total_amount: string, paid_amount: string, remaining_amount: string, payment_status: PaymentStatus}  $amounts
     * @return array{subtotal_amount: string, discount_amount: string, total_amount: string, paid_amount: string, remaining_amount: string, payment_status: PaymentStatus}
     */
    private function amountAttributes(array $amounts): array
    {
        return [
            'subtotal_amount' => $amounts['subtotal_amount'],
            'discount_amount' => $amounts['discount_amount'],
            'total_amount' => $amounts['total_amount'],
            'paid_amount' => $amounts['paid_amount'],
            'remaining_amount' => $amounts['remaining_amount'],
            'payment_status' => $amounts['payment_status'],
        ];
    }

    private function activeCustomer(int|string $customerId): SalamiCustomer
    {
        $customer = SalamiCustomer::query()->find($customerId);
        $this->assertActiveCustomer($customer);

        return $customer;
    }

    private function assertActiveCustomer(?SalamiCustomer $customer): void
    {
        if (! $customer instanceof SalamiCustomer || ! $customer->is_active) {
            throw ValidationException::withMessages(['customer_id' => 'اختر محلًا أو عميلًا نشطًا للفاتورة.']);
        }
    }

    private function nextInvoiceNumber(): string
    {
        return 'SAL-I-'.Str::upper((string) Str::ulid());
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
