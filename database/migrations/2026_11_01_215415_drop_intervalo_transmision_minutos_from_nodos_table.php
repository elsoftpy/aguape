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
        Schema::table('nodos', function (Blueprint $table) {
            $table->dropColumn('intervalo_transmision_minutos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nodos', function (Blueprint $table) {
            $table->unsignedSmallInteger('intervalo_transmision_minutos')->default(15)->after('nombre');
        });
    }
};
