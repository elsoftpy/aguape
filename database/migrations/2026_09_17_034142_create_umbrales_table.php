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
        Schema::create('umbrales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nodo_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('tipo');
            $table->decimal('valor', 10, 4);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['variable_id', 'nodo_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('umbrales');
    }
};
