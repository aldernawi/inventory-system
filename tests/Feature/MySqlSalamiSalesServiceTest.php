<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\MovementType;
use App\Exceptions\Salami\InvoiceException;
use App\Models\SalamiCustomer;
use App\Models\SalamiProduct;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Services\Salami\AdjustmentService;
use App\Services\Salami\InvoiceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Opt-in MySQL integration checks for transactional Salami sales operations.
 */
class MySqlSalamiSalesServiceTest extends TestCase
{
    private const CONNECTION = 'mysql_salami_sales_tests';

    protected function setUp(): void
    {
        parent::setUp();

        if (env('RUN_MYSQL_INVENTORY_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_MYSQL_INVENTORY_TESTS=1 to run the MySQL Salami sales integration tests.');
        }

        $this->configureMySqlTestDatabase();
    }

    public function test_mysql_confirms_a_multi_item_invoice_once_with_exact_decimals(): void
    {
        $user = User::factory()->create();
        $customer = SalamiCustomer::factory()->create();
        $first = SalamiProduct::factory()->create();
        $second = SalamiProduct::factory()->create();
        $stock = app(StockService::class);
        $invoices = app(InvoiceService::class);
        $stock->opening($first, '20.000', $user);
        $stock->opening($second, '30.000', $user);

        $invoice = $invoices->createDraft([
            'customer_id' => $customer->getKey(),
            'invoice_date' => '2026-08-20',
            'payment_type' => 'cash',
            'paid_amount' => '147.713',
        ], [
            ['product_id' => $first->getKey(), 'quantity' => '4.125', 'unit_price' => '10.100'],
            ['product_id' => $second->getKey(), 'quantity' => '5.250', 'unit_price' => '20.200'],
        ], $user);

        $confirmed = $invoices->confirm($invoice, $user);

        $this->assertSame(InvoiceStatus::Confirmed, $confirmed->status);
        $this->assertSame('147.713', $confirmed->total_amount);
        $this->assertSame('15.875', $first->fresh()->current_quantity);
        $this->assertSame('24.750', $second->fresh()->current_quantity);
        $this->assertSame(2, StockMovement::query()->where('movement_type', MovementType::Sale->value)->count());

        try {
            $invoices->confirm($invoice, $user);
            $this->fail('A confirmed invoice must not deduct stock twice.');
        } catch (InvoiceException) {
            $this->assertSame('15.875', $first->fresh()->current_quantity);
            $this->assertSame('24.750', $second->fresh()->current_quantity);
        }
    }

    public function test_mysql_rolls_back_a_multi_item_invoice_when_later_stock_is_insufficient(): void
    {
        $user = User::factory()->create();
        $customer = SalamiCustomer::factory()->create();
        $first = SalamiProduct::factory()->create();
        $second = SalamiProduct::factory()->create();
        $stock = app(StockService::class);
        $invoices = app(InvoiceService::class);
        $stock->opening($first, '20.000', $user);
        $stock->opening($second, '8.000', $user);
        $invoice = $invoices->createDraft([
            'customer_id' => $customer->getKey(),
            'invoice_date' => '2026-08-20',
            'payment_type' => 'cash',
            'paid_amount' => '14.000',
        ], [
            ['product_id' => $first->getKey(), 'quantity' => '4.000', 'unit_price' => '1.000'],
            ['product_id' => $second->getKey(), 'quantity' => '10.000', 'unit_price' => '1.000'],
        ], $user);

        try {
            $invoices->confirm($invoice, $user);
            $this->fail('The whole MySQL transaction must roll back.');
        } catch (InvoiceException) {
            $this->assertSame('20.000', $first->fresh()->current_quantity);
            $this->assertSame('8.000', $second->fresh()->current_quantity);
            $this->assertSame(2, StockMovement::query()->count());
            $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
        }
    }

    public function test_mysql_cancellation_reverses_against_the_current_balance_after_an_adjustment(): void
    {
        $user = User::factory()->create();
        $customer = SalamiCustomer::factory()->create();
        $product = SalamiProduct::factory()->create();
        $stock = app(StockService::class);
        $invoices = app(InvoiceService::class);
        $adjustments = app(AdjustmentService::class);
        $stock->opening($product, '114.000', $user);
        $invoice = $invoices->createDraft([
            'customer_id' => $customer->getKey(),
            'invoice_date' => '2026-08-20',
            'payment_type' => 'cash',
            'paid_amount' => '1000.000',
        ], [[
            'product_id' => $product->getKey(),
            'quantity' => '10.000',
            'unit_price' => '100.000',
        ]], $user);
        $confirmed = $invoices->confirm($invoice, $user);
        $adjustment = $adjustments->record([
            'product_id' => $product->getKey(),
            'actual_quantity' => '100.000',
            'adjustment_date' => '2026-08-20',
            'reason' => 'جرد MySQL',
        ], $user);

        $cancelled = $invoices->cancel($confirmed, $user, 'إلغاء MySQL');

        $this->assertSame('104.000', $adjustment->system_quantity);
        $this->assertSame('-4.000', $adjustment->difference_quantity);
        $this->assertSame(InvoiceStatus::Cancelled, $cancelled->status);
        $this->assertSame('110.000', $product->fresh()->current_quantity);
        $this->assertSame(1, StockMovement::query()->where('movement_type', MovementType::Reversal->value)->count());
    }

    private function configureMySqlTestDatabase(): void
    {
        $database = (string) env('INVENTORY_MYSQL_SALAMI_SALES_TEST_DATABASE', 'inventory_system_salami_sales_tests');

        if (preg_match('/\A[a-zA-Z0-9_]+\z/', $database) !== 1) {
            $this->fail('INVENTORY_MYSQL_SALAMI_SALES_TEST_DATABASE may contain only letters, numbers, and underscores.');
        }

        $connection = [
            'driver' => 'mysql',
            'host' => env('INVENTORY_MYSQL_HOST', '127.0.0.1'),
            'port' => env('INVENTORY_MYSQL_PORT', '3306'),
            'database' => $database,
            'username' => env('INVENTORY_MYSQL_USERNAME', 'root'),
            'password' => env('INVENTORY_MYSQL_PASSWORD', ''),
            'unix_socket' => env('INVENTORY_MYSQL_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ];

        config([
            'database.connections.mysql_salami_sales_admin' => [...$connection, 'database' => null],
            'database.connections.'.self::CONNECTION => $connection,
            'database.default' => self::CONNECTION,
        ]);

        DB::purge('mysql_salami_sales_admin');
        DB::purge(self::CONNECTION);
        DB::setDefaultConnection(self::CONNECTION);

        DB::connection('mysql_salami_sales_admin')->unprepared(sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $database,
        ));

        $exitCode = Artisan::call('migrate:fresh', [
            '--database' => self::CONNECTION,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            $this->fail('Could not migrate the dedicated MySQL Salami sales test database: '.Artisan::output());
        }
    }
}
