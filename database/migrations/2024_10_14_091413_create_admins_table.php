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
        Schema::create('admins', function (Blueprint $table) {
            $table->string('adminId')->primary();
            $table->string('userName')->unique();
            $table->string('passWord');
            $table->string('email');
            $table->string('fullName');
            $table->string('phoneNumber')->nullable();
            $table->string('role')->default('admin');
            $table->boolean('isActive')->default(true);
            $table->timestamp('lastLogin')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
