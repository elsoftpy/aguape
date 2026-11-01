<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('configuracion_general', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('intervalo_transmision_minutos')->default(15);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        DB::table('configuracion_general')->insert([
            'intervalo_transmision_minutos' => 15,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_general');
    }
};
