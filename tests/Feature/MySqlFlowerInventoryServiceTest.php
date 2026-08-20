<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\ReceiptStatus;
use App\Enums\SupplierScope;
use App\Exceptions\Flowers\ReceivingException;
use App\Exceptions\Inventory\DuplicateStockMutationException;
use App\Models\FlowerProduct;
use App\Models\SalamiProduct;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Flowers\ExitService;
use App\Services\Flowers\ReceivingService;
use App\Services\Flowers\WasteService;
use App\Services\Inventory\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Opt-in MySQL checks for Flower operational stock workflows.
 *
 * They create and refresh only a dedicated database. Enable with
 * RUN_MYSQL_INVENTORY_TESTS=1.
 */
class MySqlFlowerInventoryServiceTest extends TestCase
{
    private const CONNECTION = 'mysql_flower_inventory_tests';

    private const SECONDARY_CONNECTION = 'mysql_flower_inventory_tests_secondary';

    private static bool $databasePrepared = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (env('RUN_MYSQL_INVENTORY_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_MYSQL_INVENTORY_TESTS=1 to run the MySQL Flower inventory integration tests.');
        }

        $this->configureMySqlTestDatabase();
    }

    public function test_mysql_receiving_is_exact_idempotent_and_polymorphically_separated(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['module_scope' => SupplierScope::Both]);
        $flower = FlowerProduct::factory()->create();
        $salami = SalamiProduct::factory()->create();
        $stock = app(StockService::class);
        $receiving = app(ReceivingService::class);
        $stock->opening($flower, '100.000', $user);
        $stock->opening($salami, '7.000', $user);

        $receipt = $receiving->createDraft([
            'supplier_id' => $supplier->getKey(),
            'receipt_date' => '2026-08-20',
        ], [[
            'flower_product_id' => $flower->getKey(),
            'expected_quantity' => '500.000',
            'received_quantity' => '480.125',
            'damaged_quantity' => '30.125',
            'purchase_price' => '12.125',
        ]], $user);

        $confirmed = $receiving->confirm($receipt, $user);
        $item = $confirmed->items->sole();

        $this->assertSame(ReceiptStatus::Confirmed, $confirmed->status);
        $this->assertSame('19.875', $item->shortage_quantity);
        $this->assertSame('450.000', $item->accepted_quantity);
        $this->assertSame('550.000', $flower->fresh()->current_quantity);
        $this->assertSame('7.000', $salami->fresh()->current_quantity);
        $this->assertSame(1, StockMovement::query()->where('stockable_type', 'flower_product')->where('movement_type', MovementType::Receipt)->count());

        try {
            $receiving->confirm($receipt, $user);
            $this->fail('A confirmed Flower receipt cannot be confirmed twice.');
        } catch (ReceivingException) {
            $this->assertSame('550.000', $flower->fresh()->current_quantity);
        }

        $movement = StockMovement::query()->where('reference_id', $item->getKey())->where('reference_type', 'flower_receipt_item')->sole();
        $this->assertSame('450.000', $movement->quantity);

        try {
            $stock->receipt($flower, '1.000', $user, $item, 'duplicate source test');
            $this->fail('A Flower receipt item may only produce one receipt movement.');
        } catch (DuplicateStockMutationException) {
            $this->assertSame('550.000', $flower->fresh()->current_quantity);
        }
    }

    public function test_mysql_multi_item_receipt_failure_rolls_back_every_flower_product(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['module_scope' => SupplierScope::Flower]);
        $first = FlowerProduct::factory()->create();
        $second = FlowerProduct::factory()->create();
        $receiving = app(ReceivingService::class);
        $receipt = $receiving->createDraft([
            'supplier_id' => $supplier->getKey(), 'receipt_date' => '2026-08-20',
        ], [
            ['flower_product_id' => $first->getKey(), 'expected_quantity' => '2.500', 'received_quantity' => '2.500', 'damaged_quantity' => '0.000'],
            ['flower_product_id' => $second->getKey(), 'expected_quantity' => '3.000', 'received_quantity' => '3.000', 'damaged_quantity' => '0.000'],
        ], $user);
        $second->update(['is_active' => false]);

        try {
            $receiving->confirm($receipt, $user);
            $this->fail('Every Flower receipt item must roll back when a later item fails.');
        } catch (ReceivingException) {
            $this->assertSame('0.000', $first->fresh()->current_quantity);
            $this->assertSame('0.000', $second->fresh()->current_quantity);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertSame(ReceiptStatus::Draft, $receipt->fresh()->status);
        }
    }

    public function test_mysql_waste_and_manual_exit_are_atomic_and_use_one_source_movement_each(): void
    {
        $user = User::factory()->create();
        $product = FlowerProduct::factory()->create();
        $stock = app(StockService::class);
        $stock->opening($product, '550.000', $user);

        $waste = app(WasteService::class)->record([
            'flower_product_id' => $product->getKey(), 'quantity' => '15.000', 'reason' => 'ذبول', 'waste_date' => '2026-08-20',
        ], $user);
        $exit = app(ExitService::class)->record([
            'flower_product_id' => $product->getKey(), 'quantity' => '20.000', 'exit_date' => '2026-08-20', 'exit_type' => 'gift', 'recipient_name' => 'قاعة الربيع',
        ], $user);

        $this->assertSame('515.000', $product->fresh()->current_quantity);
        $this->assertSame(1, StockMovement::query()->where('reference_type', 'stock_waste')->where('reference_id', $waste->getKey())->where('movement_type', MovementType::Waste)->count());
        $this->assertSame(1, StockMovement::query()->where('reference_type', 'flower_exit')->where('reference_id', $exit->getKey())->where('movement_type', MovementType::ManualExit)->count());
        $this->assertSame('قاعة الربيع', $exit->recipient_name);
    }

    public function test_mysql_flower_product_row_lock_blocks_a_secondary_for_update_transaction(): void
    {
        $product = FlowerProduct::factory()->create();
        $primary = DB::connection(self::CONNECTION);
        $secondary = DB::connection(self::SECONDARY_CONNECTION);
        $primary->beginTransaction();

        try {
            $primary->table('flower_products')->where('id', $product->getKey())->lockForUpdate()->first();
            $secondary->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $secondary->beginTransaction();

            try {
                $secondary->table('flower_products')->where('id', $product->getKey())->lockForUpdate()->first();
                $this->fail('The second Flower transaction should wait for the row lock.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Lock wait timeout exceeded', $exception->getMessage());
            } finally {
                if ($secondary->transactionLevel() > 0) {
                    $secondary->rollBack();
                }
            }
        } finally {
            if ($primary->transactionLevel() > 0) {
                $primary->rollBack();
            }
        }
    }

    private function configureMySqlTestDatabase(): void
    {
        $database = (string) env('INVENTORY_MYSQL_FLOWER_TEST_DATABASE', 'inventory_system_flower_tests');

        if (preg_match('/\A[a-zA-Z0-9_]+\z/', $database) !== 1) {
            $this->fail('INVENTORY_MYSQL_FLOWER_TEST_DATABASE may contain only letters, numbers, and underscores.');
        }

        $connection = [
            'driver' => 'mysql', 'host' => env('INVENTORY_MYSQL_HOST', '127.0.0.1'), 'port' => env('INVENTORY_MYSQL_PORT', '3306'),
            'database' => $database, 'username' => env('INVENTORY_MYSQL_USERNAME', 'root'), 'password' => env('INVENTORY_MYSQL_PASSWORD', ''),
            'unix_socket' => env('INVENTORY_MYSQL_SOCKET', ''), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'prefix_indexes' => true, 'strict' => true, 'engine' => null,
        ];

        config([
            'database.connections.mysql_flower_inventory_admin' => [...$connection, 'database' => null],
            'database.connections.'.self::CONNECTION => $connection,
            'database.connections.'.self::SECONDARY_CONNECTION => $connection,
            'database.default' => self::CONNECTION,
        ]);
        DB::purge('mysql_flower_inventory_admin');
        DB::purge(self::CONNECTION);
        DB::purge(self::SECONDARY_CONNECTION);
        DB::setDefaultConnection(self::CONNECTION);

        DB::connection('mysql_flower_inventory_admin')->unprepared(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $database,
        ));
        if (! self::$databasePrepared) {
            $exitCode = Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);

            if ($exitCode !== 0) {
                $this->fail('Could not migrate the dedicated MySQL Flower test database: '.Artisan::output());
            }

            self::$databasePrepared = true;

            return;
        }

        $this->truncateTestTables();
    }

    private function truncateTestTables(): void
    {
        $connection = DB::connection(self::CONNECTION);
        $connection->statement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($connection->select('SHOW TABLES') as $row) {
                $table = array_values((array) $row)[0] ?? null;

                if (! is_string($table) || $table === 'migrations' || preg_match('/\A[a-zA-Z0-9_]+\z/', $table) !== 1) {
                    continue;
                }

                $connection->unprepared("TRUNCATE TABLE `{$table}`");
            }
        } finally {
            $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
