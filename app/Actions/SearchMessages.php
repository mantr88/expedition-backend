<?php

namespace App\Actions;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Пошук повідомлень (фаза B5) з урахуванням прав: лише канали, де
 * користувач член. Postgres — full-text (tsvector 'simple' + GIN;
 * plainto_tsquery нейтралізує синтаксис запиту, тож ін'єкція
 * tsquery-операторів неможлива); sqlite (тести) — LIKE-фолбек.
 * Порядок — за свіжістю (id DESC), курсорна пагінація як у стрічці.
 */
class SearchMessages
{
    /**
     * @return array{messages: Collection<int, Message>, has_more: bool}
     */
    public function handle(User $user, string $query, ?int $channelId, ?int $before, int $limit): array
    {
        $builder = Message::query()
            ->whereHas('channel.members', fn (Builder $members) => $members->where('user_id', $user->id))
            ->when($channelId !== null, fn (Builder $q) => $q->where('channel_id', $channelId))
            ->when($before !== null, fn (Builder $q) => $q->where('id', '<', $before))
            ->with([
                'user',
                'attachments',
                'reactions',
                'channel' => fn ($q) => $q->withCount('members'),
                'channel.dmCounterpart.user',
            ])
            ->withCount('replies')
            ->withMax('replies', 'created_at');

        $this->applyTextMatch($builder, $query);

        $messages = $builder
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        return [
            'messages' => $messages->take($limit)->values(),
            'has_more' => $messages->count() > $limit,
        ];
    }

    /**
     * @param  Builder<Message>  $builder
     */
    private function applyTextMatch(Builder $builder, string $term): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $builder->whereRaw("to_tsvector('simple', body) @@ plainto_tsquery('simple', ?)", [$term]);

            return;
        }

        $builder->whereLike('body', '%'.$term.'%');
    }
}
