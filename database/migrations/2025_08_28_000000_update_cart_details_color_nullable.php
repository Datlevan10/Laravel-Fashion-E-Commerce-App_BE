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
        Schema::table('cart_details', function (Blueprint $table) {
            // Make color field nullable if it isn't already
            $table->string('color')->nullable()->change();
            
            // Make image field nullable as well
            $table->string('image')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_details', function (Blueprint $table) {
            // Revert color field back to required
            $table->string('color')->nullable(false)->change();
            
            // Revert image field back to required
            $table->string('image')->nullable(false)->change();
        });
    }
};