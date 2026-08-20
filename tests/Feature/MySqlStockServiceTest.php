<?php

namespace Tests\Feature;

use App\Models\FlowerProduct;
use App\Models\SalamiProduct;
use App\Models\User;
use App\Services\Inventory\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * These tests are opt-in because they create and refresh a dedicated MySQL test database.
 *
 * Run with RUN_MYSQL_INVENTORY_TESTS=1. The database name defaults to
 * inventory_system_stock_tests and can be overridden with INVENTORY_MYSQL_TEST_DATABASE.
 */
class MySqlStockServiceTest extends TestCase
{
    private const CONNECTION = 'mysql_inventory_tests';

    private const SECONDARY_CONNECTION = 'mysql_inventory_tests_secondary';

    protected function setUp(): void
    {
        parent::setUp();

        if (env('RUN_MYSQL_INVENTORY_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_MYSQL_INVENTORY_TESTS=1 to run the MySQL inventory integration tests.');
        }

        $this->configureMySqlTestDatabase();
    }

    public function test_mysql_executes_the_stock_service_locking_path_with_exact_decimals_and_fixed_morph_aliases(): void
    {
        $user = User::factory()->create();
        $salamiProduct = SalamiProduct::factory()->create();
        $flowerProduct = FlowerProduct::factory()->create();
        $stock = app(StockService::class);

        $salamiOpening = $stock->opening($salamiProduct, '0.100', $user);
        $salamiReceipt = $stock->receipt($salamiProduct, '0.200', $user);
        $flowerOpening = $stock->opening($flowerProduct, '100.000', $user);

        $salamiProduct->refresh();
        $flowerProduct->refresh();

        $this->assertSame('0.300', $salamiProduct->current_quantity);
        $this->assertSame('0.100', $salamiOpening->quantity);
        $this->assertSame('0.300', $salamiReceipt->balance_after);
        $this->assertSame('100.000', $flowerProduct->current_quantity);
        $this->assertSame('salami_product', $salamiOpening->getRawOriginal('stockable_type'));
        $this->assertSame('flower_product', $flowerOpening->getRawOriginal('stockable_type'));

        try {
            DB::connection(self::CONNECTION)->table('stock_movements')->insert([
                'stockable_type' => $salamiProduct->getMorphClass(),
                'stockable_id' => $salamiProduct->getKey(),
                'movement_type' => 'receipt',
                'quantity' => '1.000',
                'balance_before' => '0.000',
                'balance_after' => '999.000',
                'created_by' => $user->getKey(),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('The MySQL stock movement balance CHECK constraint should reject an invalid transition.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('stock_movements_balances_check', $exception->getMessage());
        }
    }

    public function test_mysql_for_update_blocks_a_second_connection_from_reading_the_same_product_for_update(): void
    {
        $product = SalamiProduct::factory()->create();
        $primary = DB::connection(self::CONNECTION);
        $secondary = DB::connection(self::SECONDARY_CONNECTION);

        $primary->beginTransaction();

        try {
            $primary->table('salami_products')->where('id', $product->getKey())->lockForUpdate()->first();
            $secondary->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $secondary->beginTransaction();

            try {
                $secondary->table('salami_products')->where('id', $product->getKey())->lockForUpdate()->first();
                $this->fail('The secondary transaction should wait for the primary row lock.');
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

    /**
     * Configure independent primary and secondary MySQL connections so row locks are real.
     */
    private function configureMySqlTestDatabase(): void
    {
        $database = (string) env('INVENTORY_MYSQL_TEST_DATABASE', 'inventory_system_stock_tests');

        if (preg_match('/\A[a-zA-Z0-9_]+\z/', $database) !== 1) {
            $this->fail('INVENTORY_MYSQL_TEST_DATABASE may contain only letters, numbers, and underscores.');
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
        $administrativeConnection = [...$connection, 'database' => null];

        config([
            'database.connections.mysql_inventory_admin' => $administrativeConnection,
            'database.connections.'.self::CONNECTION => $connection,
            'database.connections.'.self::SECONDARY_CONNECTION => $connection,
            'database.default' => self::CONNECTION,
        ]);

        DB::purge('mysql_inventory_admin');
        DB::purge(self::CONNECTION);
        DB::purge(self::SECONDARY_CONNECTION);
        DB::setDefaultConnection(self::CONNECTION);

        DB::connection('mysql_inventory_admin')->unprepared(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $database,
        ));

        $exitCode = Artisan::call('migrate:fresh', [
            '--database' => self::CONNECTION,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            $this->fail('Could not migrate the dedicated MySQL inventory test database: '.Artisan::output());
        }
    }
}
