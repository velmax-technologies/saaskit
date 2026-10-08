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
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('public_id', 32)->nullable()->unique()->after('id');
        });

        DB::table('organizations')
            ->whereNull('public_id')
            ->orderBy('id')
            ->eachById(function (object $organization): void {
                DB::table('organizations')
                    ->where('id', $organization->id)
                    ->update([
                        'public_id' => 'org_'.Str::ulid(),
                    ]);
            });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('public_id', 32)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
