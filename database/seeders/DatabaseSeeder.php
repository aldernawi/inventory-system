<?php

namespace Database\Seeders;

use App\Enums\InventoryRecordStatus;
use App\Enums\PaymentType;
use App\Enums\SupplierScope;
use App\Enums\UserRole;
use App\Models\FlowerInvoice;
use App\Models\FlowerProduct;
use App\Models\FlowerReceipt;
use App\Models\SalamiCustomer;
use App\Models\SalamiDeliveryAgent;
use App\Models\SalamiInvoice;
use App\Models\SalamiProduct;
use App\Models\SalamiReceipt;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Flowers\ExitService as FlowerExitService;
use App\Services\Flowers\InvoiceService as FlowerInvoiceService;
use App\Services\Flowers\ReceivingService as FlowerReceivingService;
use App\Services\Flowers\WasteService as FlowerWasteService;
use App\Services\Inventory\StockService;
use App\Services\Salami\AdjustmentService as SalamiAdjustmentService;
use App\Services\Salami\InvoiceService as SalamiInvoiceService;
use App\Services\Salami\ReceivingService as SalamiReceivingService;
use App\Services\Salami\WasteService as SalamiWasteService;
use App\Support\Quantity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@inventory.test'],
            ['name' => 'مدير النظام', 'password' => Hash::make('password'), 'role' => UserRole::Admin, 'is_active' => true, 'email_verified_at' => now()],
        );
        User::query()->updateOrCreate(
            ['email' => 'employee@inventory.test'],
            ['name' => 'موظف المخزن', 'password' => Hash::make('password'), 'role' => UserRole::Employee, 'is_active' => true, 'email_verified_at' => now()],
        );

        $salamiSupplier = Supplier::query()->updateOrCreate(['name' => 'شركة غذاء طرابلس'], [
            'company_name' => 'شركة غذاء طرابلس للتوريد', 'phone' => '0910000001', 'country' => 'ليبيا', 'module_scope' => SupplierScope::Salami, 'is_active' => true,
        ]);
        $flowerSupplier = Supplier::query()->updateOrCreate(['name' => 'مشتل الياسمين'], [
            'company_name' => 'مشتل الياسمين للزهور', 'phone' => '0910000002', 'country' => 'ليبيا', 'module_scope' => SupplierScope::Flower, 'is_active' => true,
        ]);
        $sharedSupplier = Supplier::query()->updateOrCreate(['name' => 'مخازن الساحل'], [
            'company_name' => 'مخازن الساحل للتجارة', 'phone' => '0910000003', 'country' => 'ليبيا', 'module_scope' => SupplierScope::Both, 'is_active' => true,
        ]);

        $deliveryAgents = collect([
            ['name' => 'محمد الورفلي', 'phone' => '0910000020'],
            ['name' => 'سالم الفيتوري', 'phone' => '0910000021'],
        ])->map(fn (array $deliveryAgent): SalamiDeliveryAgent => SalamiDeliveryAgent::query()->updateOrCreate(['name' => $deliveryAgent['name']], [...$deliveryAgent, 'is_active' => true, 'created_by' => $admin->getKey()]));

        $customers = collect([
            ['name' => 'محل الوداد', 'contact_person' => 'مصطفى', 'phone' => '0910000010', 'area' => 'سوق الجمعة', 'delivery_agent_id' => $deliveryAgents->firstWhere('name', 'محمد الورفلي')->getKey()],
            ['name' => 'سوبر ماركت النخيل', 'contact_person' => 'سالم', 'phone' => '0910000011', 'area' => 'طرابلس المركز', 'delivery_agent_id' => $deliveryAgents->firstWhere('name', 'محمد الورفلي')->getKey()],
            ['name' => 'مخزن باب البحر', 'contact_person' => 'علي', 'phone' => '0910000012', 'area' => 'باب البحر', 'delivery_agent_id' => $deliveryAgents->firstWhere('name', 'سالم الفيتوري')->getKey()],
        ])->map(fn (array $customer): SalamiCustomer => SalamiCustomer::query()->updateOrCreate(['name' => $customer['name']], [...$customer, 'is_active' => true]));

        // Local demo invoices created before delivery agents existed keep their historical stock and amounts;
        // only the reporting snapshot is completed from the linked demo store.
        $customers->each(fn (SalamiCustomer $customer) => DB::table('salami_invoices')
            ->whereNull('delivery_agent_id')
            ->where('customer_id', $customer->getKey())
            ->update(['delivery_agent_id' => $customer->delivery_agent_id]));

        $salamiProducts = collect([
            ['code' => 'SAL-001', 'name' => 'سلامي سادة', 'unit' => 'قطعة', 'purchase_price' => '8.000', 'sale_price' => '10.000', 'minimum_quantity' => '20.000'],
            ['code' => 'SAL-002', 'name' => 'سلامي حار', 'unit' => 'قطعة', 'purchase_price' => '8.500', 'sale_price' => '11.000', 'minimum_quantity' => '15.000'],
            ['code' => 'SAL-003', 'name' => 'سلامي مدخن', 'unit' => 'قطعة', 'purchase_price' => '9.500', 'sale_price' => '12.500', 'minimum_quantity' => '12.000'],
            ['code' => 'SAL-004', 'name' => 'سلامي شرائح', 'unit' => 'قطعة', 'purchase_price' => '7.000', 'sale_price' => '9.200', 'minimum_quantity' => '10.000'],
        ])->map(fn (array $product): SalamiProduct => SalamiProduct::query()->updateOrCreate(['code' => $product['code']], [...$product, 'is_active' => true, 'created_by' => $admin->getKey()]));

        $flowerProducts = collect([
            ['code' => 'FL-001', 'name' => 'جوري', 'company_name' => 'مزارع الجبل', 'color' => 'أحمر', 'grade' => 'درجة أولى', 'unit' => 'ربطة', 'purchase_price' => '15.000', 'sale_price' => '22.000', 'minimum_quantity' => '30.000'],
            ['code' => 'FL-002', 'name' => 'جوري', 'company_name' => 'مزارع الجبل', 'color' => 'أبيض', 'grade' => 'درجة أولى', 'unit' => 'ربطة', 'purchase_price' => '15.000', 'sale_price' => '22.000', 'minimum_quantity' => '25.000'],
            ['code' => 'FL-003', 'name' => 'توليب', 'company_name' => 'هولندا فلور', 'color' => 'أصفر', 'grade' => 'درجة أولى', 'unit' => 'ربطة', 'purchase_price' => '18.000', 'sale_price' => '28.000', 'minimum_quantity' => '20.000'],
            ['code' => 'FL-004', 'name' => 'ليليوم', 'company_name' => 'فلورز العالمية', 'color' => 'أبيض', 'grade' => 'ممتاز', 'unit' => 'ربطة', 'purchase_price' => '25.000', 'sale_price' => '38.000', 'minimum_quantity' => '15.000'],
            ['code' => 'FL-005', 'name' => 'توليب', 'company_name' => 'مزارع المتوسط', 'color' => 'أصفر', 'grade' => 'ممتاز', 'unit' => 'ربطة', 'purchase_price' => '20.000', 'sale_price' => '31.000', 'minimum_quantity' => '18.000'],
        ])->map(fn (array $product): FlowerProduct => FlowerProduct::query()->updateOrCreate(['code' => $product['code']], [...$product, 'is_active' => true, 'created_by' => $admin->getKey()]));

        $stock = app(StockService::class);
        foreach (['SAL-001' => '120.000', 'SAL-002' => '90.000', 'SAL-003' => '45.000', 'SAL-004' => '18.000'] as $code => $quantity) {
            $product = $salamiProducts->firstWhere('code', $code);
            if (! StockMovement::query()->where('stockable_type', $product->getMorphClass())->where('stockable_id', $product->getKey())->exists()) {
                $stock->opening($product, $quantity, $admin, 'رصيد افتتاحي تجريبي');
            }
        }
        foreach (['FL-001' => '100.000', 'FL-002' => '70.000', 'FL-003' => '120.000', 'FL-004' => '40.000', 'FL-005' => '90.000'] as $code => $quantity) {
            $product = $flowerProducts->firstWhere('code', $code);
            if (! StockMovement::query()->where('stockable_type', $product->getMorphClass())->where('stockable_id', $product->getKey())->exists()) {
                $stock->opening($product, $quantity, $admin, 'رصيد افتتاحي تجريبي');
            }
        }

        if (! SalamiReceipt::query()->exists()) {
            $salamiReceiving = app(SalamiReceivingService::class);
            $salamiReceipt = $salamiReceiving->createDraft(['supplier_id' => $salamiSupplier->getKey(), 'receipt_date' => now()->subDays(3)->toDateString(), 'supplier_invoice_number' => 'SUP-SAL-1001', 'notes' => 'استلام بضاعة تجريبي مع نقص وتالف'], [
                ['product_id' => $salamiProducts->firstWhere('code', 'SAL-001')->getKey(), 'expected_quantity' => '100.000', 'received_quantity' => '96.000', 'damaged_quantity' => '2.000', 'purchase_price' => '80.000'],
                ['product_id' => $salamiProducts->firstWhere('code', 'SAL-002')->getKey(), 'expected_quantity' => '60.000', 'received_quantity' => '66.000', 'damaged_quantity' => '1.000', 'purchase_price' => '85.000'],
            ], $admin);
            $salamiReceiving->confirm($salamiReceipt, $admin);
            $secondSalamiReceipt = $salamiReceiving->createDraft(['supplier_id' => $sharedSupplier->getKey(), 'receipt_date' => now()->subDay()->toDateString(), 'supplier_invoice_number' => 'SUP-SAL-1002'], [
                ['product_id' => $salamiProducts->firstWhere('code', 'SAL-003')->getKey(), 'expected_quantity' => '40.000', 'received_quantity' => '40.000', 'damaged_quantity' => '0.000', 'purchase_price' => '95.000'],
                ['product_id' => $salamiProducts->firstWhere('code', 'SAL-004')->getKey(), 'expected_quantity' => '25.000', 'received_quantity' => '25.000', 'damaged_quantity' => '0.000', 'purchase_price' => '70.000'],
            ], $admin);
            $salamiReceiving->confirm($secondSalamiReceipt, $admin);
            $salamiReceiving->createDraft(['supplier_id' => $salamiSupplier->getKey(), 'receipt_date' => now()->toDateString(), 'notes' => 'مسودة للعرض في النظام'], [['product_id' => $salamiProducts->firstWhere('code', 'SAL-001')->getKey(), 'expected_quantity' => '30.000', 'received_quantity' => '30.000', 'damaged_quantity' => '0.000', 'purchase_price' => '80.000']], $admin);
        }

        if (! SalamiInvoice::query()->exists()) {
            $salamiInvoices = app(SalamiInvoiceService::class);
            $invoice = $salamiInvoices->createDraft(['customer_id' => $customers[0]->getKey(), 'invoice_date' => now()->subDays(2)->toDateString(), 'payment_type' => PaymentType::Partial->value, 'paid_amount' => '1000.000', 'notes' => 'فاتورة تجريبية'], [
                ['product_id' => $salamiProducts->firstWhere('code', 'SAL-001')->getKey(), 'quantity' => '18.000', 'unit_price' => '100.000'],
                ['product_id' => $salamiProducts->firstWhere('code', 'SAL-002')->getKey(), 'quantity' => '10.000', 'unit_price' => '110.000'],
            ], $admin);
            $salamiInvoices->confirm($invoice, $admin);
            $invoice = $salamiInvoices->createDraft(['customer_id' => $customers[1]->getKey(), 'invoice_date' => now()->subDay()->toDateString(), 'payment_type' => PaymentType::Partial->value, 'paid_amount' => '500.000'], [['product_id' => $salamiProducts->firstWhere('code', 'SAL-003')->getKey(), 'quantity' => '8.000', 'unit_price' => '125.000']], $admin);
            $salamiInvoices->confirm($invoice, $admin);
            $salamiInvoices->createDraft(['customer_id' => $customers[2]->getKey(), 'invoice_date' => now()->toDateString(), 'payment_type' => PaymentType::Credit->value, 'paid_amount' => '0.000', 'notes' => 'فاتورة مسودة للعرض'], [['product_id' => $salamiProducts->firstWhere('code', 'SAL-004')->getKey(), 'quantity' => '5.000', 'unit_price' => '92.000']], $admin);
            app(SalamiWasteService::class)->record(['product_id' => $salamiProducts->firstWhere('code', 'SAL-001')->getKey(), 'quantity' => '4.000', 'reason' => 'تالف أثناء التخزين', 'waste_date' => now()->toDateString()], $admin);
            app(SalamiAdjustmentService::class)->record(['product_id' => $salamiProducts->firstWhere('code', 'SAL-002')->getKey(), 'actual_quantity' => '148.000', 'adjustment_date' => now()->toDateString(), 'reason' => 'فرق جرد بسيط', 'notes' => 'بيانات تجريبية'], $admin);
        }

        if (! FlowerReceipt::query()->exists()) {
            $flowerReceiving = app(FlowerReceivingService::class);
            $flowerReceipt = $flowerReceiving->createDraft(['supplier_id' => $flowerSupplier->getKey(), 'receipt_date' => now()->subDays(2)->toDateString(), 'supplier_invoice_number' => 'SUP-FL-2001', 'notes' => 'استلام ورد مع تالف عند الوصول'], [
                ['flower_product_id' => $flowerProducts->firstWhere('code', 'FL-001')->getKey(), 'expected_quantity' => '300.000', 'received_quantity' => '280.000', 'damaged_quantity' => '10.000', 'purchase_price' => '15.000'],
                ['flower_product_id' => $flowerProducts->firstWhere('code', 'FL-002')->getKey(), 'expected_quantity' => '200.000', 'received_quantity' => '220.000', 'damaged_quantity' => '5.000', 'purchase_price' => '15.000'],
            ], $admin);
            $flowerReceiving->confirm($flowerReceipt, $admin);
            $secondFlowerReceipt = $flowerReceiving->createDraft(['supplier_id' => $sharedSupplier->getKey(), 'receipt_date' => now()->toDateString(), 'supplier_invoice_number' => 'SUP-FL-2002'], [
                ['flower_product_id' => $flowerProducts->firstWhere('code', 'FL-003')->getKey(), 'expected_quantity' => '160.000', 'received_quantity' => '150.000', 'damaged_quantity' => '0.000', 'purchase_price' => '18.000'],
                ['flower_product_id' => $flowerProducts->firstWhere('code', 'FL-004')->getKey(), 'expected_quantity' => '80.000', 'received_quantity' => '75.000', 'damaged_quantity' => '3.000', 'purchase_price' => '25.000'],
            ], $admin);
            $flowerReceiving->confirm($secondFlowerReceipt, $admin);
            $flowerReceiving->createDraft(['supplier_id' => $flowerSupplier->getKey(), 'receipt_date' => now()->toDateString(), 'notes' => 'مسودة استلام ورد'], [['flower_product_id' => $flowerProducts->firstWhere('code', 'FL-001')->getKey(), 'expected_quantity' => '100.000', 'received_quantity' => '90.000', 'damaged_quantity' => '0.000', 'purchase_price' => '15.000']], $admin);
        }

        if (! FlowerInvoice::query()->exists()) {
            $flowerInvoices = app(FlowerInvoiceService::class);
            $flowerInvoice = $flowerInvoices->createDraft(['recipient_name' => 'قاعة أفراح النخيل', 'invoice_date' => now()->subDay()->toDateString(), 'payment_type' => PaymentType::Partial->value, 'paid_amount' => '2000.000', 'notes' => 'فاتورة ورد تجريبية'], [
                ['flower_product_id' => $flowerProducts->firstWhere('code', 'FL-001')->getKey(), 'quantity' => '90.000', 'unit_price' => '22.000'],
                ['flower_product_id' => $flowerProducts->firstWhere('code', 'FL-002')->getKey(), 'quantity' => '60.000', 'unit_price' => '22.000'],
            ], $admin);
            $flowerInvoices->confirm($flowerInvoice, $admin);
            $flowerInvoice = $flowerInvoices->createDraft(['recipient_name' => 'محل زهور طرابلس', 'invoice_date' => now()->toDateString(), 'payment_type' => PaymentType::Partial->value, 'paid_amount' => '500.000'], [['flower_product_id' => $flowerProducts->firstWhere('code', 'FL-003')->getKey(), 'quantity' => '45.000', 'unit_price' => '28.000']], $admin);
            $flowerInvoices->confirm($flowerInvoice, $admin);
            $flowerInvoices->createDraft(['recipient_name' => 'مسودة بدون اعتماد', 'invoice_date' => now()->toDateString(), 'payment_type' => PaymentType::Credit->value, 'paid_amount' => '0.000'], [['flower_product_id' => $flowerProducts->firstWhere('code', 'FL-004')->getKey(), 'quantity' => '10.000', 'unit_price' => '38.000']], $admin);
            app(FlowerWasteService::class)->record(['flower_product_id' => $flowerProducts->firstWhere('code', 'FL-001')->getKey(), 'quantity' => '8.000', 'reason' => 'ذبول', 'waste_date' => now()->toDateString(), 'notes' => 'تالف بعد التخزين'], $admin);
            app(FlowerExitService::class)->record(['flower_product_id' => $flowerProducts->firstWhere('code', 'FL-002')->getKey(), 'quantity' => '12.000', 'exit_date' => now()->toDateString(), 'exit_type' => 'gift', 'recipient_name' => 'مناسبة عائلية', 'notes' => 'خروج غير بيعي تجريبي'], $admin);

            $flowerForAdjustment = $flowerProducts->firstWhere('code', 'FL-003')->fresh();
            DB::transaction(function () use ($flowerForAdjustment, $admin, $stock): void {
                $systemQuantity = Quantity::from($flowerForAdjustment->current_quantity);
                $actualQuantity = $systemQuantity->minus(Quantity::from('5.000'));
                $adjustment = StockAdjustment::query()->create([
                    'stockable_type' => $flowerForAdjustment->getMorphClass(),
                    'stockable_id' => $flowerForAdjustment->getKey(),
                    'system_quantity' => $systemQuantity->toString(),
                    'actual_quantity' => $actualQuantity->toString(),
                    'difference_quantity' => '-5.000',
                    'adjustment_date' => now()->toDateString(),
                    'reason' => 'فرق جرد ورد بسيط',
                    'notes' => 'تسوية تجريبية للعرض',
                    'status' => InventoryRecordStatus::Confirmed,
                    'created_by' => $admin->getKey(),
                    'confirmed_by' => $admin->getKey(),
                    'confirmed_at' => now(),
                ]);
                $stock->adjust($flowerForAdjustment, '-5.000', $admin, $adjustment, 'تسوية مخزون ورد تجريبية');
            });
        }

        if (! StockAdjustment::query()->where('stockable_type', $flowerProducts->firstWhere('code', 'FL-003')->getMorphClass())->where('stockable_id', $flowerProducts->firstWhere('code', 'FL-003')->getKey())->exists()) {
            $flowerForAdjustment = $flowerProducts->firstWhere('code', 'FL-003')->fresh();
            DB::transaction(function () use ($flowerForAdjustment, $admin, $stock): void {
                $systemQuantity = Quantity::from($flowerForAdjustment->current_quantity);
                $actualQuantity = $systemQuantity->minus(Quantity::from('5.000'));
                $adjustment = StockAdjustment::query()->create([
                    'stockable_type' => $flowerForAdjustment->getMorphClass(),
                    'stockable_id' => $flowerForAdjustment->getKey(),
                    'system_quantity' => $systemQuantity->toString(),
                    'actual_quantity' => $actualQuantity->toString(),
                    'difference_quantity' => '-5.000',
                    'adjustment_date' => now()->toDateString(),
                    'reason' => 'فرق جرد ورد بسيط',
                    'notes' => 'تسوية تجريبية للعرض',
                    'status' => InventoryRecordStatus::Confirmed,
                    'created_by' => $admin->getKey(),
                    'confirmed_by' => $admin->getKey(),
                    'confirmed_at' => now(),
                ]);
                $stock->adjust($flowerForAdjustment, '-5.000', $admin, $adjustment, 'تسوية مخزون ورد تجريبية');
            });
        }
    }
}
