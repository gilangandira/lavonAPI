<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration {
    public function up()
{
    Schema::create('customers', function (Blueprint $table) {
        $table->id();
        $table->string('nik')->unique();
        $table->string('name');
        $table->string('phone');
        $table->string('address');
        $table->string('email')->unique();
        $table->enum('criteria', ['Visited', 'Deposited', 'Booked', 'Process']);
        $table->integer('cicilan')->nullable();

        // Mengunci cluster yang dipilih customer
        $table->foreignId('cluster_id')->constrained('clusters')->cascadeOnDelete();

        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('customers');
}

}
