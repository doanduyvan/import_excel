<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->string('order_number')->unique();
            $table->string('invoice_number')->nullable();
            $table->string('contract_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('selling_price', 15, 2)->nullable();
            $table->float('commercial_quantity')->nullable();
            $table->date('invoice_confirmed_date')->nullable();
            $table->decimal('net_sales_value', 15, 2)->nullable();
            $table->date('accounts_receivable_date')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
