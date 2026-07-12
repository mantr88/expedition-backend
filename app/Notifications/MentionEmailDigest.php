<?php

namespace App\Notifications;

use App\Models\Mention;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Email-дайджест згадок для офлайн-користувача (фаза B3). Надсилається
 * із затримкою debounce-вікна; текст збирається у момент відправлення,
 * тож охоплює всі згадки, накопичені з початку вікна.
 */
class MentionEmailDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Carbon $since) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mentions = Mention::query()
            ->where('mentioned_user_id', $notifiable->id)
            ->where('created_at', '>=', $this->since)
            ->whereHas('message.channel', function ($query) use ($notifiable): void {
                // mute каналу вимикає email-дайджест про згадки в ньому (B5);
                // відсутність запису членства не виключає згадку — виключаємо
                // лише явний mute.
                $query->whereDoesntHave('members', function ($membersQuery) use ($notifiable): void {
                    $membersQuery
                        ->where('user_id', $notifiable->id)
                        ->where('notifications_level', 'mute');
                });
            })
            ->with(['message.channel', 'message.user'])
            ->get();

        $mail = (new MailMessage)
            ->subject('Нові згадки у '.config('app.name'))
            ->greeting("Привіт, {$notifiable->name}!")
            ->line('Поки вас не було, вас згадали у повідомленнях:');

        $mentions
            ->groupBy(fn (Mention $mention): int => $mention->message->channel_id)
            ->each(function ($channelMentions) use ($mail): void {
                $channel = $channelMentions->first()->message->channel;
                $authors = $channelMentions
                    ->map(fn (Mention $mention): string => $mention->message->user->name)
                    ->unique()
                    ->implode(', ');

                $mail->line("«{$channel->name}» — згадок: {$channelMentions->count()} (від: {$authors})");
            });

        return $mail->line('Відкрийте месенджер, щоб переглянути повідомлення.');
    }
}
