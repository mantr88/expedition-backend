<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            // Мультитенантність (workspaces) закладена наперед: поле nullable,
            // таблиця workspaces з'явиться при масштабуванні без болючої міграції.
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->string('name');
            $table->string('type', 16)->default('public'); // public | private | dm
            $table->string('topic')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
