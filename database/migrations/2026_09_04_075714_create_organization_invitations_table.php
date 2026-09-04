<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_invitations', function (Blueprint $table): void {
            $table->id();

            $table->string('public_id', 32)->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('invited_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('email');
            $table->string('role', 50)->default('member');

            $table->string('token_hash', 64)->unique();

            $table->string('status', 50)
                ->default('pending');

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index([
                'organization_id',
                'email',
                'status',
            ]);

            $table->index([
                'organization_id',
                'status',
            ]);

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
