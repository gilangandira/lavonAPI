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
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('cluster_id')->constrained()->onDelete('cascade');
            $table->string('locked_type')->nullable();
            $table->decimal('price', 15, 2);
            $table->string('status')->default('Process');
            $table->date('booking_date')->nullable();
            $table->decimal('payment_amount', 15, 2)->default(0);
            $table->integer('cicilan_count')->default(0);

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
