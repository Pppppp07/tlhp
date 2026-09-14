<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip', 30)->nullable()->after('name');
            $table->string('jabatan')->nullable()->after('nip');
            $table->string('peran', 20)->default('satker')->after('jabatan');
            $table->foreignId('satker_id')->nullable()->after('peran')
                  ->constrained('satkers')->nullOnDelete();
            $table->boolean('aktif')->default(true)->after('satker_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('satker_id');
            $table->dropColumn(['nip', 'jabatan', 'peran', 'aktif']);
        });
    }
};
