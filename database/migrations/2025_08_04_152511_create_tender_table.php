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
        Schema::create('tender', function (Blueprint $table) {
            $table->id();
            $table->text('customer_quota_description')->nullable();
            $table->date('cust_quota_start_date')->nullable();
            $table->date('cust_quota_end_date')->nullable();
            $table->float('cust_quota_quantity')->nullable();
            $table->float('invoice_quantity')->nullable();
            $table->float('return_quantity')->nullable();
            $table->float('allocated_quantity')->nullable();
            $table->float('used_quota')->nullable();
            $table->float('remaining_quota')->nullable();
            $table->date('report_run_date')->nullable();
            $table->decimal('tender_price', 15, 2)->nullable();
            $table->string('sap_item_code')->nullable();
            $table->text('item_short_description')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender');
    }
};
