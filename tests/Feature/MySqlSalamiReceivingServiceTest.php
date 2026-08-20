<?php

namespace Tests\Feature;

use App\Enums\ReceiptStatus;
use App\Enums\SupplierScope;
use App\Exceptions\Salami\ReceivingException;
use App\Models\SalamiProduct;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Services\Salami\ReceivingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Opt-in MySQL integration checks for the transactional Salami receiving workflow.
 */
class MySqlSalamiReceivingServiceTest extends TestCase
{
    private const CONNECTION = 'mysql_salami_receiving_tests';

    protected function setUp(): void
    {
        parent::setUp();

        if (env('RUN_MYSQL_INVENTORY_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_MYSQL_INVENTORY_TESTS=1 to run the MySQL Salami receiving integration tests.');
        }

        $this->configureMySqlTestDatabase();
    }

    public function test_mysql_confirmation_uses_exact_decimals_and_rejects_a_repeat_without_double_adding_stock(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['module_scope' => SupplierScope::Both]);
        $product = SalamiProduct::factory()->create();
        $stock = app(StockService::class);
        $receiving = app(ReceivingService::class);

        $stock->opening($product, '20.000', $user);
        $receipt = $receiving->createDraft([
            'supplier_id' => $supplier->getKey(),
            'receipt_date' => '2026-08-20',
        ], [[
            'product_id' => $product->getKey(),
            'expected_quantity' => '100.000',
            'received_quantity' => '94.000',
            'damaged_quantity' => '4.000',
            'purchase_price' => '80.125',
        ]], $user);

        $confirmed = $receiving->confirm($receipt, $user);
        $item = $confirmed->items->sole();

        $this->assertSame(ReceiptStatus::Confirmed, $confirmed->status);
        $this->assertSame('6.000', $item->shortage_quantity);
        $this->assertSame('90.000', $item->accepted_quantity);
        $this->assertSame('20.000', $item->balance_before);
        $this->assertSame('110.000', $item->balance_after);
        $this->assertSame('110.000', $product->fresh()->current_quantity);
        $this->assertDatabaseCount('stock_movements', 2);

        try {
            $receiving->confirm($receipt, $user);
            $this->fail('A confirmed receipt must not be confirmed twice.');
        } catch (ReceivingException) {
            $this->assertSame('110.000', $product->fresh()->current_quantity);
            $this->assertDatabaseCount('stock_movements', 2);
        }
    }

    public function test_mysql_multi_item_confirmation_rolls_back_every_product_when_one_item_fails(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $firstProduct = SalamiProduct::factory()->create();
        $secondProduct = SalamiProduct::factory()->create();
        $receiving = app(ReceivingService::class);

        $receipt = $receiving->createDraft([
            'supplier_id' => $supplier->getKey(),
            'receipt_date' => '2026-08-20',
        ], [
            ['product_id' => $firstProduct->getKey(), 'expected_quantity' => '2.500', 'received_quantity' => '2.500', 'damaged_quantity' => '0.000'],
            ['product_id' => $secondProduct->getKey(), 'expected_quantity' => '3.000', 'received_quantity' => '3.000', 'damaged_quantity' => '0.000'],
        ], $user);
        $secondProduct->update(['is_active' => false]);

        try {
            $receiving->confirm($receipt, $user);
            $this->fail('All stock changes must roll back if one receipt item cannot be confirmed.');
        } catch (ReceivingException) {
            $this->assertSame('0.000', $firstProduct->fresh()->current_quantity);
            $this->assertSame('0.000', $secondProduct->fresh()->current_quantity);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertSame(ReceiptStatus::Draft, $receipt->fresh()->status);
        }
    }

    private function configureMySqlTestDatabase(): void
    {
        $database = (string) env('INVENTORY_MYSQL_SALAMI_TEST_DATABASE', 'inventory_system_salami_receiving_tests');

        if (preg_match('/\A[a-zA-Z0-9_]+\z/', $database) !== 1) {
            $this->fail('INVENTORY_MYSQL_SALAMI_TEST_DATABASE may contain only letters, numbers, and underscores.');
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
            'database.connections.mysql_salami_receiving_admin' => [...$connection, 'database' => null],
            'database.connections.'.self::CONNECTION => $connection,
            'database.default' => self::CONNECTION,
        ]);

        DB::purge('mysql_salami_receiving_admin');
        DB::purge(self::CONNECTION);
        DB::setDefaultConnection(self::CONNECTION);

        DB::connection('mysql_salami_receiving_admin')->unprepared(sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $database,
        ));

        $exitCode = Artisan::call('migrate:fresh', [
            '--database' => self::CONNECTION,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            $this->fail('Could not migrate the dedicated MySQL Salami receiving test database: '.Artisan::output());
        }
    }
}
