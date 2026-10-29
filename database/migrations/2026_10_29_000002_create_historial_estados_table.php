<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_estados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patrono_id')->nullable()->constrained('patronos')->nullOnDelete();

            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('campo', 50);
            $table->string('valor_anterior')->nullable();
            $table->string('valor_nuevo')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['patrono_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_estados');
    }
};
