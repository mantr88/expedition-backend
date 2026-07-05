<?php

namespace App\Models;

use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @property Carbon|null $archived_at
 * @property-read int|null $members_count
 * @property-read int|null $unread_count
 */
#[Fillable(['workspace_id', 'name', 'type', 'topic', 'created_by', 'archived_at'])]
class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ChannelMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ChannelMember::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_members')
            ->withPivot(['role', 'last_read_message_id', 'notifications_level'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Членство поточного автентифікованого користувача — для eager-load
     * `my_membership` у ChannelResource без N+1.
     *
     * @return HasOne<ChannelMember, $this>
     */
    public function myMembership(): HasOne
    {
        return $this->hasOne(ChannelMember::class)->where('user_id', Auth::id());
    }

    /**
     * Членство співрозмовника у DM — для резолву імені каналу
     * (`name` DM-каналу = ім'я співрозмовника, контракт B3).
     *
     * @return HasOne<ChannelMember, $this>
     */
    public function dmCounterpart(): HasOne
    {
        return $this->hasOne(ChannelMember::class)->where('user_id', '!=', Auth::id());
    }

    /**
     * Per-viewer контекст для ChannelResource: моє членство, співрозмовник DM,
     * members_count та unread_count (COUNT на льоту — стратегія MVP, B3).
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withViewerContext(Builder $query, User $viewer): void
    {
        $query
            ->with(['myMembership', 'dmCounterpart.user'])
            ->withCount([
                'members',
                'messages as unread_count' => fn (Builder $messages) => self::applyUnreadFilter($messages, $viewer),
            ]);
    }

    public function loadViewerContext(User $viewer): self
    {
        return $this
            ->load(['myMembership', 'dmCounterpart.user'])
            ->loadCount([
                'members',
                'messages as unread_count' => fn (Builder $messages) => self::applyUnreadFilter($messages, $viewer),
            ]);
    }

    /**
     * Непрочитані = повідомлення з id > last_read_message_id мого членства
     * (NULL → 0, тобто все непрочитане). Soft-deleted виключає глобальний скоуп.
     *
     * @param  Builder<Message>  $messages
     */
    private static function applyUnreadFilter(Builder $messages, User $viewer): void
    {
        $messages->where('messages.id', '>', function ($subquery) use ($viewer): void {
            $subquery
                ->selectRaw('coalesce(max(last_read_message_id), 0)')
                ->from('channel_members')
                ->whereColumn('channel_members.channel_id', 'messages.channel_id')
                ->where('channel_members.user_id', $viewer->id);
        });
    }

    /**
     * Копія каналу для broadcast-payload конкретному отримувачу: без
     * per-viewer relations (my_membership/unread_count → null), але з
     * members_count і резолвом імені DM відносно отримувача — черга
     * не має auth-контексту, тож співрозмовника шукаємо явно.
     */
    public function toBroadcastPayloadFor(User $recipient): self
    {
        $channel = $this->withoutRelations();

        // withCount-атрибут іншого viewer'а міг лишитись на моделі —
        // у спільному payload він завжди null.
        unset($channel->unread_count);

        $channel->loadCount('members');

        if ($channel->type === 'dm') {
            $channel->setRelation(
                'dmCounterpart',
                $channel->members()->with('user')->where('user_id', '!=', $recipient->id)->first(),
            );
        }

        return $channel;
    }

    public function membershipFor(User $user): ?ChannelMember
    {
        return $this->members()->where('user_id', $user->id)->first();
    }

    public function isMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
