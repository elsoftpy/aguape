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
        Schema::create('lecturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nodo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variable_id')->constrained()->cascadeOnDelete();
            $table->decimal('valor', 10, 4);
            $table->timestamp('medido_en');
            $table->timestamps();

            $table->index(['nodo_id', 'variable_id', 'medido_en']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lecturas');
    }
};
