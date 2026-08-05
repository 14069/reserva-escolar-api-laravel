<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_admins', function (Blueprint $table) {
            $table->string('api_token', 128)->nullable()->unique()->after('last_login_at');
            $table->timestamp('api_token_expires_at')->nullable()->after('api_token');
        });
    }

    public function down(): void
    {
        Schema::table('system_admins', function (Blueprint $table) {
            $table->dropColumn(['api_token', 'api_token_expires_at']);
        });
    }
};
