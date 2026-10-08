<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_user', function (Blueprint $table): void {
            $table->string('public_id', 32)
                ->nullable()
                ->unique()
                ->after('id');
        });

        DB::table('organization_user')
            ->whereNull('public_id')
            ->orderBy('id')
            ->eachById(function (object $membership): void {
                DB::table('organization_user')
                    ->where('id', $membership->id)
                    ->update([
                        'public_id' => 'mem_'.Str::ulid(),
                    ]);
            });

        Schema::table('organization_user', function (Blueprint $table): void {
            $table->string('public_id', 32)
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('organization_user', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
