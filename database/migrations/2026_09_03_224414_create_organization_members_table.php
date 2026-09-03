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
        Schema::create('organization_members', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 32)->unique();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('role', 50)->default('member');
            $table->string('status', 50)->default('active');

            $table->timestamps();

            $table->unique([
                'user_id',
                'organization_id',
            ]);

            $table->index([
                'organization_id',
                'role',
            ]);

            $table->index([
                'organization_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_members');
    }
};
