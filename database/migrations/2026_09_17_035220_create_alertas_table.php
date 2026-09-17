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
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lectura_id')->constrained()->cascadeOnDelete();
            $table->foreignId('umbral_id')->constrained('umbrales')->cascadeOnDelete();
            $table->foreignId('nodo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variable_id')->constrained()->cascadeOnDelete();
            $table->string('estado')->default('pendiente');
            $table->foreignId('atendida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('atendida_en')->nullable();
            $table->timestamp('notificada_en')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
