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
        Schema::create('ith_cortes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nodo_id')->constrained()->cascadeOnDelete();
            $table->timestamp('inicio');
            $table->timestamp('fin');
            $table->decimal('promedio', 6, 2);
            $table->decimal('maximo', 6, 2);
            $table->decimal('minimo', 6, 2);
            $table->unsignedInteger('cantidad_lecturas');
            $table->timestamps();

            $table->unique(['nodo_id', 'inicio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ith_cortes');
    }
};
