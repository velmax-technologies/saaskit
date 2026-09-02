<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('public_id', 64)->nullable()->after('id');
        });

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'public_id' => 'usr_'.Str::ulid(),
                        ]);
                }
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('public_id', 'users_public_id_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('public_id', 64)->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_public_id_unique');
            $table->dropColumn('public_id');
        });
    }
};
