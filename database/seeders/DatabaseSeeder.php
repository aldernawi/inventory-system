<?php

namespace Database\Seeders;

use App\Enums\SupplierScope;
use App\Enums\UserRole;
use App\Models\FlowerProduct;
use App\Models\SalamiCustomer;
use App\Models\SalamiProduct;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@inventory.test'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'employee@inventory.test'],
            [
                'name' => 'موظف المخزن',
                'password' => Hash::make('password'),
                'role' => UserRole::Employee,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        Supplier::query()->updateOrCreate(
            ['name' => 'مورد السلامي المحلي'],
            [
                'company_name' => 'شركة السلامي للتوريد',
                'phone' => '0910000001',
                'country' => 'ليبيا',
                'module_scope' => SupplierScope::Salami,
                'is_active' => true,
            ],
        );

        Supplier::query()->updateOrCreate(
            ['name' => 'مورد الورد المحلي'],
            [
                'company_name' => 'شركة الورد للتوريد',
                'phone' => '0910000002',
                'country' => 'ليبيا',
                'module_scope' => SupplierScope::Flower,
                'is_active' => true,
            ],
        );

        Supplier::query()->updateOrCreate(
            ['name' => 'مورد مشترك'],
            [
                'company_name' => 'شركة التوريد المشترك',
                'phone' => '0910000003',
                'country' => 'ليبيا',
                'module_scope' => SupplierScope::Both,
                'is_active' => true,
            ],
        );

        SalamiProduct::query()->updateOrCreate(
            ['code' => 'SAL-001'],
            [
                'name' => 'سلامي نوع A',
                'unit' => 'كرتونة',
                'purchase_price' => '80.000',
                'sale_price' => '100.000',
                'minimum_quantity' => '10.000',
                'is_active' => true,
            ],
        );

        FlowerProduct::query()->updateOrCreate(
            ['code' => 'FL-001'],
            [
                'name' => 'جوري',
                'color' => 'أحمر',
                'grade' => 'درجة أولى',
                'unit' => 'ربطة',
                'purchase_price' => '15.000',
                'sale_price' => '22.000',
                'minimum_quantity' => '10.000',
                'is_active' => true,
            ],
        );

        SalamiCustomer::query()->updateOrCreate(
            ['name' => 'محل الأمل'],
            [
                'contact_person' => 'أحمد',
                'phone' => '0910000010',
                'area' => 'طرابلس',
                'is_active' => true,
            ],
        );
    }
}
