<?php

namespace App\Services\Flowers;

use App\Enums\InvoiceStatus;
use App\Exceptions\Flowers\InvoiceException;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Exceptions\Inventory\StockMutationException;
use App\Models\FlowerInvoice;
use App\Models\FlowerInvoiceItem;
use App\Models\FlowerProduct;
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

    /** @param list<array<string, mixed>> $items */
    public function createDraft(array $attributes, array $items, User $createdBy): FlowerInvoice
    {
        return DB::transaction(function () use ($attributes, $items, $createdBy): FlowerInvoice {
            $prepared = $this->prepareItems($items, $attributes);
            $invoice = FlowerInvoice::query()->create([
                'invoice_number' => 'FL-I-'.Str::upper((string) Str::ulid()),
                'recipient_name' => $this->nullableText($attributes['recipient_name'] ?? null),
                'invoice_date' => $attributes['invoice_date'],
                'payment_type' => $attributes['payment_type'],
                ...$this->amountAttributes($prepared['amounts']),
                'status' => InvoiceStatus::Draft,
                'notes' => $this->nullableText($attributes['notes'] ?? null),
                'created_by' => $createdBy->getKey(),
            ]);
            $this->createItems($invoice, $prepared['items']);

            return $invoice->fresh(['items.product']);
        });
    }

    /** @param list<array<string, mixed>> $items */
    public function updateDraft(FlowerInvoice $invoice, array $attributes, array $items, User $updatedBy): FlowerInvoice
    {
        return DB::transaction(function () use ($invoice, $attributes, $items, $updatedBy): FlowerInvoice {
            $locked = $this->lockDraft($invoice);
            $prepared = $this->prepareItems($items, $attributes);
            $locked->update([
                'recipient_name' => $this->nullableText($attributes['recipient_name'] ?? null),
                'invoice_date' => $attributes['invoice_date'],
                'payment_type' => $attributes['payment_type'],
                ...$this->amountAttributes($prepared['amounts']),
                'notes' => $this->nullableText($attributes['notes'] ?? null),
                'updated_by' => $updatedBy->getKey(),
            ]);
            $locked->items()->delete();
            $this->createItems($locked, $prepared['items']);

            return $locked->fresh(['items.product']);
        });
    }

    public function deleteDraft(FlowerInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $locked = $this->lockDraft($invoice);
            $locked->items()->delete();
            $locked->delete();
        });
    }

    public function confirm(FlowerInvoice $invoice, User $confirmedBy): FlowerInvoice
    {
        return DB::transaction(function () use ($invoice, $confirmedBy): FlowerInvoice {
            $locked = FlowerInvoice::query()->lockForUpdate()->find($invoice->getKey());
            if (! $locked instanceof FlowerInvoice) {
                throw new InvoiceException('الفاتورة لم تعد موجودة.');
            }
            if ($locked->status !== InvoiceStatus::Draft) {
                throw new InvoiceException('يمكن اعتماد الفواتير المسودة فقط.');
            }

            $items = FlowerInvoiceItem::query()->with('product')->where('invoice_id', $locked->getKey())->orderBy('flower_product_id')->get();
            if ($items->isEmpty()) {
                throw new InvoiceException('أضف نوع ورد واحدًا على الأقل قبل اعتماد الفاتورة.');
            }
            $amounts = InvoiceAmounts::calculate($items->map(fn (FlowerInvoiceItem $item): array => [
                'quantity' => $item->quantity, 'unit_price' => $item->unit_price,
            ])->all(), $locked->discount_amount, $locked->paid_amount, $locked->payment_type);

            foreach ($items as $index => $item) {
                $product = $item->product;
                if (! $product instanceof FlowerProduct || ! $product->is_active) {
                    throw new InvoiceException('كل بند في الفاتورة يجب أن يشير إلى نوع ورد نشط.');
                }
                $amount = $amounts['items'][$index];
                $item->update([
                    'product_name' => $product->name, 'color' => $product->color, 'unit' => $product->unit,
                    'quantity' => $amount['quantity'], 'unit_price' => $amount['unit_price'], 'line_total' => $amount['line_total'],
                ]);
                try {
                    $this->stockService->sale($product, $amount['quantity'], $confirmedBy, $item, "بيع ورد عبر الفاتورة {$locked->invoice_number}");
                } catch (InsufficientStockException $exception) {
                    throw new InvoiceException("المخزون غير كافٍ لنوع الورد {$product->name}. المتاح حاليًا: {$exception->available}.", previous: $exception);
                }
            }

            $locked->update([
                ...$this->amountAttributes($amounts), 'status' => InvoiceStatus::Confirmed,
                'confirmed_by' => $confirmedBy->getKey(), 'confirmed_at' => now(),
            ]);

            return $locked->fresh(['items.product', 'items.stockMovement', 'createdBy', 'confirmedBy']);
        });
    }

    public function cancel(FlowerInvoice $invoice, User $cancelledBy, string $reason): FlowerInvoice
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['cancellation_reason' => 'سبب الإلغاء مطلوب.']);
        }

        return DB::transaction(function () use ($invoice, $cancelledBy, $reason): FlowerInvoice {
            $locked = FlowerInvoice::query()->lockForUpdate()->find($invoice->getKey());
            if (! $locked instanceof FlowerInvoice) {
                throw new InvoiceException('الفاتورة لم تعد موجودة.');
            }
            if ($locked->status !== InvoiceStatus::Confirmed) {
                throw new InvoiceException('يمكن إلغاء الفاتورة المعتمدة فقط.');
            }
            foreach (FlowerInvoiceItem::query()->where('invoice_id', $locked->getKey())->orderBy('flower_product_id')->get() as $item) {
                $movement = StockMovement::query()->where('reference_type', $item->getMorphClass())->where('reference_id', $item->getKey())->where('movement_type', 'sale')->lockForUpdate()->first();
                if (! $movement instanceof StockMovement) {
                    throw new InvoiceException("تعذر العثور على حركة البيع الخاصة ببند {$item->product_name}.");
                }
                try {
                    $this->stockService->reverse($movement, $cancelledBy, "إلغاء فاتورة الورد {$locked->invoice_number}: {$reason}");
                } catch (StockMutationException $exception) {
                    throw new InvoiceException('تعذر إلغاء الفاتورة لأن إحدى حركات البيع تم عكسها سابقًا.', previous: $exception);
                }
            }
            FlowerInvoice::allowConfirmedMutation(fn () => $locked->update([
                'status' => InvoiceStatus::Cancelled, 'cancelled_by' => $cancelledBy->getKey(),
                'cancelled_at' => now(), 'cancellation_reason' => $reason,
            ]));

            return $locked->fresh(['items.product', 'createdBy', 'confirmedBy', 'cancelledBy']);
        });
    }

    private function lockDraft(FlowerInvoice $invoice): FlowerInvoice
    {
        $locked = FlowerInvoice::query()->lockForUpdate()->find($invoice->getKey());
        if (! $locked instanceof FlowerInvoice) {
            throw new InvoiceException('الفاتورة لم تعد موجودة.');
        }
        if ($locked->status !== InvoiceStatus::Draft) {
            throw new InvoiceException('الفاتورة المعتمدة للعرض فقط.');
        }

        return $locked;
    }

    /** @param list<array<string,mixed>> $items */
    private function prepareItems(array $items, array $attributes): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'أضف نوع ورد واحدًا على الأقل إلى الفاتورة.']);
        }
        $ids = [];
        $products = [];
        $amountItems = [];
        foreach ($items as $index => $item) {
            $id = $item['flower_product_id'] ?? null;
            if (! is_numeric($id)) {
                throw ValidationException::withMessages(["items.{$index}.flower_product_id" => 'اختر نوع الورد.']);
            }
            $id = (int) $id;
            if (in_array($id, $ids, true)) {
                throw ValidationException::withMessages(["items.{$index}.flower_product_id" => 'لا يمكن تكرار نوع الورد في الفاتورة نفسها.']);
            }
            $product = FlowerProduct::query()->find($id);
            if (! $product instanceof FlowerProduct || ! $product->is_active) {
                throw ValidationException::withMessages(["items.{$index}.flower_product_id" => 'نوع الورد غير متاح للبيع.']);
            }
            $ids[] = $id;
            $products[] = $product;
            $amountItems[] = ['quantity' => $item['quantity'] ?? '0', 'unit_price' => $item['unit_price'] ?? '0'];
        }
        $amounts = InvoiceAmounts::calculate($amountItems, $attributes['discount_amount'] ?? '0', $attributes['paid_amount'] ?? '0', $attributes['payment_type']);
        $prepared = [];
        foreach ($products as $index => $product) {
            $prepared[] = ['product' => $product, ...$amounts['items'][$index]];
        }

        return ['items' => $prepared, 'amounts' => $amounts];
    }

    private function createItems(FlowerInvoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $product = $item['product'];
            $invoice->items()->create([
                'flower_product_id' => $product->getKey(), 'product_name' => $product->name, 'color' => $product->color,
                'unit' => $product->unit, 'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'line_total' => $item['line_total'],
            ]);
        }
    }

    private function amountAttributes(array $amounts): array
    {
        return collect($amounts)->only(['subtotal_amount', 'discount_amount', 'total_amount', 'paid_amount', 'remaining_amount', 'payment_status'])->all();
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
