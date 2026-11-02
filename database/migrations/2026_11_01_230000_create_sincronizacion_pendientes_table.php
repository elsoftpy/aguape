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
        Schema::create('sincronizacion_pendientes', function (Blueprint $table) {
            $table->id();
            $table->string('tabla', 64);
            $table->unsignedBigInteger('registro_id');
            $table->string('operacion', 16);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('intentos')->default(0);
            $table->text('ultimo_error')->nullable();
            $table->timestamps();

            $table->unique(['tabla', 'registro_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sincronizacion_pendientes');
    }
};
