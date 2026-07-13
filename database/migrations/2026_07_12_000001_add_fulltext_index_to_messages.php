<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * GIN-індекс повнотекстового пошуку (фаза B5). Лише Postgres:
     * у тестах (sqlite) пошук працює через LIKE-фолбек без індексу.
     * Конфігурація 'simple' — контент україномовний, стемінга для
     * української у стоковому Postgres немає; вираз індексу має
     * дослівно збігатися з виразом у SearchMessages.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("create index messages_body_fts on messages using gin (to_tsvector('simple', body))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('drop index if exists messages_body_fts');
    }
};
