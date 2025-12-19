<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClustersTable extends Migration {
    public function up(){
        Schema::create('clusters', function (Blueprint $table){
            $table->id();
            $table->string('type'); // contoh: "Tipe A", "Tipe B"
            $table->decimal('price', 15, 2);
            $table->integer('luas_tanah'); // m2
            $table->integer('luas_bangunan'); // m2
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('clusters'); }
}