<?php

namespace App\Actions;

use App\Events\MessageSent;
use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendMessage
{
    /**
     * Ідемпотентне надсилання: повтор із тим самим client_message_id
     * (reconnect/ретрай) повертає існуюче повідомлення замість дубля.
     * Той самий UUID від іншого автора чи в іншому каналі — 422.
     *
     * @param  array{body: string, client_message_id: string, parent_id?: int|null}  $attributes
     */
    public function handle(User $author, Channel $channel, array $attributes): Message
    {
        $existing = $this->findDuplicate($author, $channel, $attributes['client_message_id']);

        if ($existing !== null) {
            return $existing;
        }

        try {
            // Транзакція (savepoint) — щоб після unique violation з'єднання
            // Postgres лишалося придатним для запиту-відкату нижче.
            $message = DB::transaction(fn (): Message => $channel->messages()->create([
                'user_id' => $author->id,
                'parent_id' => $attributes['parent_id'] ?? null,
                'client_message_id' => $attributes['client_message_id'],
                'body' => $attributes['body'],
                'type' => 'text',
            ]));

            // Broadcast лише для реально створеного повідомлення: дублікат
            // (ретрай) події не генерує — фронт уже отримав її першого разу.
            MessageSent::dispatch($message);

            return $message;
        } catch (UniqueConstraintViolationException) {
            // Гонка двох одночасних ретраїв: перший вставив — віддаємо його.
            $winner = $this->findDuplicate($author, $channel, $attributes['client_message_id']);

            if ($winner !== null) {
                return $winner;
            }

            throw ValidationException::withMessages([
                'client_message_id' => 'The client message id has already been used.',
            ]);
        }
    }

    private function findDuplicate(User $author, Channel $channel, string $clientMessageId): ?Message
    {
        return Message::query()
            ->where('client_message_id', $clientMessageId)
            ->where('channel_id', $channel->id)
            ->where('user_id', $author->id)
            ->first();
    }
}
