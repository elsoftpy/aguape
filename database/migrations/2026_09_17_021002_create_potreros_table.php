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
        Schema::create('potreros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained()->onDelete('cascade');
            $table->string('nombre');
            $table->decimal('hectareas', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['establecimiento_id', 'nombre']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('potreros');
    }
};
