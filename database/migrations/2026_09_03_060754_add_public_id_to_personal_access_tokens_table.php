<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->string('public_id', 64)
                ->nullable()
                ->after('id');
        });

        $tokens = DB::table('personal_access_tokens')
            ->select('id')
            ->orderBy('id')
            ->get();

        foreach ($tokens as $token) {
            DB::table('personal_access_tokens')
                ->where('id', $token->id)
                ->update([
                    'public_id' => 'tok_'.Str::ulid(),
                ]);
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->unique(
                'public_id',
                'personal_access_tokens_public_id_unique',
            );
        });

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->string('public_id', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropUnique('personal_access_tokens_public_id_unique');
            $table->dropColumn('public_id');
        });
    }
};
