<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member'); // owner | admin | member
            // Без FK на messages: таблиця messages створюється пізніше,
            // а маркер прочитаного не потребує referential-гарантій.
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->string('notifications_level', 16)->default('all'); // all | mentions | mute
            $table->timestamps();

            $table->unique(['channel_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_members');
    }
};
