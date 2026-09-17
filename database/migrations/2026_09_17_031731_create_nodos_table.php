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
        Schema::create('nodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained()->cascadeOnDelete();
            $table->foreignId('potrero_id')->nullable()->constrained()->nullOnDelete();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->unsignedSmallInteger('intervalo_transmision_minutos')->default(15);
            $table->boolean('activo')->default(true);
            $table->timestamp('instalado_en')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nodos');
    }
};
