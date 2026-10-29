<?php

use App\Enums\RolUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->default(RolUsuario::Consulta->value)->after('email');
        });

        Schema::create('patrono_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patrono_id')->constrained('patronos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'patrono_id']);
        });

        // Los usuarios existentes conservan su acceso actual y pasan a ser
        // administradores. Los usuarios nuevos se crean con rol Consulta.
        DB::table('users')->update(['rol' => RolUsuario::Administrador->value]);
    }

    public function down(): void
    {
        Schema::dropIfExists('patrono_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rol');
        });
    }
};
