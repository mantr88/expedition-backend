<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('parent_id')->nullable()->constrained('messages'); // треди — self-reference
            $table->uuid('client_message_id')->nullable()->unique(); // ідемпотентність надсилання
            $table->text('body');
            $table->string('type', 16)->default('text'); // text | system | file
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Головний індекс стрічки: курсорна пагінація по (channel_id, id DESC);
            // btree читається у зворотному порядку, окремий DESC-індекс не потрібен.
            $table->index(['channel_id', 'id']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
