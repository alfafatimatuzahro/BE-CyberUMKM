<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id('id_user');
            $table->string('nama_user');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['superadmin', 'admin', 'user'])->default('user');
            $table->string('foto_profil')->nullable();
            $table->string('security_question')->nullable();
            $table->string('security_answer')->nullable();
            $table->enum('status', ['aktif', 'diblokir', 'diblokir_sementara'])->default('aktif');
            $table->timestamp('blokir_hingga')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
