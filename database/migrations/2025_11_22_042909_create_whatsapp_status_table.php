<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_status', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->default('default')->unique();
            $table->text('qr_code')->nullable();
            $table->string('status')->default('DISCONNECTED'); // CONNECTING, CONNECTED, DISCONNECTED
            $table->text('logs')->nullable(); // Guardaremos el último log aquí
            $table->timestamps();
        });
        
        // Insertamos el registro inicial
        DB::table('whatsapp_status')->insert([
            'session_id' => 'default',
            'status' => 'DISCONNECTED'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_status');
    }
};
