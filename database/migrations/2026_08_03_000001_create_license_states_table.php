<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_states', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('product')->unique();
            $table->string('status')->default('not_activated');
            $table->text('license_key')->nullable();    // encrypted
            $table->text('fingerprint')->nullable();    // encrypted installation fingerprint
            $table->text('token')->nullable();          // encrypted last signed token
            $table->json('payload')->nullable();        // decoded token claims (uuid, features, ...)
            $table->json('public_keys')->nullable();    // keyring: kid => public key
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_states');
    }
};
