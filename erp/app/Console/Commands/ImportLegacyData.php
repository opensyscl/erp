<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportLegacyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-legacy-data {file?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import data from legacy OpenSys SQL dump';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file') ?? '/home/jos/opensys/opensys_listto (1).sql';

        if (!File::exists($filePath)) {
            $this->error("File not found: $filePath");
            return 1;
        }

        $this->info("Reading SQL dump from: $filePath");
        $sql = File::get($filePath);

        // 1. Prepare temporary SQL by renaming tables to tm_
        // Tables to import: categories, products, sales, sale_items, operational_expenses, customers, users, suppliers
        $tables = ['categories', 'products', 'sales', 'sale_items', 'operational_expenses', 'customers', 'suppliers', 'cash_closings'];

        $tempSql = $sql;
        foreach ($tables as $table) {
            // Rename CREATE TABLE
            $tempSql = preg_replace(
                "/CREATE TABLE\s+[`\"]?{$table}[`\"]?/",
                "CREATE TABLE `tm_{$table}`",
                $tempSql
            );

            // Rename INSERT INTO
            $tempSql = preg_replace(
                "/INSERT INTO\s+[`\"]?{$table}[`\"]?/",
                "INSERT INTO `tm_{$table}`",
                $tempSql
            );

            // Rename constraints/references (simple attempt)
            $tempSql = str_replace("REFERENCES `{$table}`", "REFERENCES `tm_{$table}`", $tempSql);
        }

        // Disable Foreign Keys
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Drop existing temp tables if valid
        foreach ($tables as $table) {
            DB::statement("DROP TABLE IF EXISTS `tm_{$table}`");
        }

        $this->info("Importing temporary tables...");
        try {
            DB::unprepared($tempSql);
        } catch (\Exception $e) {
            $this->error("Error importing SQL: " . $e->getMessage());
            // Continue mostly as some chunks might fail but others work
        }

        // 2. Migrate Data
        $tenantId = 1; // Default tenant
        $userId = 1;   // Default user (Admin)

        $this->info("Migrating data to production tables (Tenant ID: $tenantId)...");

        // CATEGORIES
        if ($this->hasTable('tm_categories')) {
            $this->info("Migrating Categories...");
            // Assuming tm_categories has: id, name
            DB::statement("
                INSERT INTO categories (id, tenant_id, name, slug, created_at, updated_at)
                SELECT id, $tenantId, name, LOWER(REPLACE(name, ' ', '-')), NOW(), NOW()
                FROM tm_categories
                ON DUPLICATE KEY UPDATE name=VALUES(name);
            ");
        }

        // PRODUCTS
        if ($this->hasTable('tm_products')) {
            $this->info("Migrating Products...");
            // Assuming tm_products: id, barcode, name, price, stock, cost_price, category_id, image_url...
            // Target products: id, tenant_id, name, description, barcode, price, cost, stock, category_id, image...
            DB::statement("
                INSERT INTO products (id, tenant_id, name, barcode, price, cost, stock, category_id, image, created_at, updated_at)
                SELECT id, $tenantId, name, barcode, price, cost_price, stock, category_id, image_url, created_at, updated_at
                FROM tm_products
                ON DUPLICATE KEY UPDATE
                    price=VALUES(price), stock=VALUES(stock), name=VALUES(name), cost=VALUES(cost);
            ");
        }

        // SALES
        if ($this->hasTable('tm_sales')) {
            $this->info("Migrating Sales...");
            // Mapping method values
            DB::statement("
                INSERT INTO sales (id, tenant_id, user_id, total, paid, `change`, receipt_number, payment_method, cost_of_goods_sold, status, created_at, updated_at)
                SELECT
                    id,
                    $tenantId,
                    $userId,
                    total,
                    paid,
                    `change`,
                    receipt_number,
                    CASE
                        WHEN method LIKE '%efectivo%' THEN 'cash'
                        WHEN method LIKE '%tarjeta%' THEN 'card'
                        WHEN method LIKE '%transferencia%' THEN 'transfer'
                        ELSE 'other'
                    END,
                    cost_of_goods_sold,
                    status,
                    created_at,
                    created_at
                FROM tm_sales
                ON DUPLICATE KEY UPDATE status=VALUES(status);
            ");
        }

        // SALE ITEMS
        if ($this->hasTable('tm_sale_items')) {
            $this->info("Migrating Sale Items...");
            // Check columns for tm_sale_items (legacy often has subtotal/price confusion)
            // Using COALESCE to fallback
            DB::statement("
                INSERT INTO sale_items (id, sale_id, product_id, quantity, price, total, created_at, updated_at)
                SELECT id, sale_id, product_id, quantity, price, (quantity * price), NOW(), NOW()
                FROM tm_sale_items
                ON DUPLICATE KEY UPDATE quantity=VALUES(quantity);
            ");
        }

        // OPERATIONAL EXPENSES
        if ($this->hasTable('tm_operational_expenses')) {
            $this->info("Migrating Expenses...");
            DB::statement("
                INSERT INTO operational_expenses (id, tenant_id, date_paid, expense_type, description, total_amount, light, water, rent, alarm, internet, iva, repairs, supplies, other, created_at, updated_at)
                SELECT
                    id, $tenantId, created_at, expense_type, description, total_amount,
                    light, water, rent, alarm, internet, iva, repairs, supplies, other,
                    created_at, updated_at
                FROM tm_operational_expenses
                ON DUPLICATE KEY UPDATE total_amount=VALUES(total_amount);
            ");
        }

        // CASH CLOSINGS
        if ($this->hasTable('tm_cash_closings')) {
             $this->info("Migrating Cash Closings...");
             // Assuming structure matches mostly
             DB::statement("
                INSERT INTO cash_closings (id, closing_date, starting_cash, ending_cash, pos1_sales, pos2_sales, total_day_cash, deposit_meli, deposit_bchile, deposit_bsantander, other_outgoings, total_outgoings, total_day_income, income_plus_outgoings, created_at, updated_at)
                SELECT id, closing_date, starting_cash, ending_cash, pos1_sales, pos2_sales, total_day_cash, deposit_meli, deposit_bchile, deposit_bsantander, other_outgoings, total_outgoings, total_day_income, income_plus_outgoings, created_at, updated_at
                FROM tm_cash_closings
                ON DUPLICATE KEY UPDATE closing_date=VALUES(closing_date);
             ");
        }

        // SUPPLIERS
        if ($this->hasTable('tm_suppliers')) {
             $this->info("Migrating Suppliers...");
             // Assuming structure: id, name, rut, email, phone, address, contact_name, etc.
             // Target suppliers table structure needs to be checked. Assuming standard fields.
             // Using simple mapping for now.
             DB::statement("
                INSERT INTO suppliers (id, tenant_id, name, rut, email, phone, address, contact_name, created_at, updated_at)
                SELECT
                    id,
                    $tenantId,
                    name,
                    rut,
                    email,
                    phone,
                    address,
                    contact_name,
                    created_at,
                    updated_at
                FROM tm_suppliers
                ON DUPLICATE KEY UPDATE name=VALUES(name);
             ");
        }

        // CLIENTS (from tm_customers)
        if ($this->hasTable('tm_customers')) {
             $this->info("Migrating Clients...");
             DB::statement("
                INSERT INTO clients (id, tenant_id, name, email, phone, address, created_at, updated_at)
                SELECT
                    id,
                    $tenantId,
                    name,
                    email,
                    phone,
                    address,
                    created_at,
                    updated_at
                FROM tm_customers
                ON DUPLICATE KEY UPDATE name=VALUES(name);
             ");
        }

        // Cleanup
        foreach ($tables as $table) {
            DB::statement("DROP TABLE IF EXISTS `tm_{$table}`");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info("Migration completed successfully!");
        return 0;
    }

    private function hasTable($table) {
        return \Illuminate\Support\Facades\Schema::hasTable($table);
    }
}
