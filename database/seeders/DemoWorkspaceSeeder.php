<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Демо-дані MVP: ~60 користувачів, кілька каналів, повідомлення.
 * Відомий логін для ручного тестування: anna@example.com / password.
 */
class DemoWorkspaceSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $anna = User::factory()->create([
            'name' => 'Anna Petrenko',
            'email' => 'anna@example.com',
        ]);

        $users = User::factory(59)->create()->prepend($anna);

        $channels = collect([
            ['name' => 'general', 'topic' => 'Загальні обговорення', 'type' => 'public'],
            ['name' => 'random', 'topic' => 'Флуд і меми', 'type' => 'public'],
            ['name' => 'dev', 'topic' => 'Розробка продукту', 'type' => 'public'],
            ['name' => 'design', 'topic' => 'Дизайн і UX', 'type' => 'public'],
            ['name' => 'management', 'topic' => 'Тільки для керівників', 'type' => 'private'],
        ])->map(fn (array $definition) => Channel::factory()->create([
            ...$definition,
            'created_by' => $anna->id,
        ]));

        foreach ($channels as $channel) {
            // general — усі; решта — випадкова підмножина. Творець — owner.
            $members = $channel->name === 'general'
                ? $users
                : $users->random(random_int(8, 25))->push($anna)->unique('id');

            foreach ($members as $user) {
                ChannelMember::factory()->create([
                    'channel_id' => $channel->id,
                    'user_id' => $user->id,
                    'role' => $user->is($anna) ? 'owner' : 'member',
                ]);
            }

            $memberIds = $members->pluck('id');
            $createdAt = now()->subDays(7);

            foreach (range(1, random_int(30, 80)) as $i) {
                $createdAt = $createdAt->copy()->addMinutes(random_int(5, 180));

                Message::factory()->create([
                    'channel_id' => $channel->id,
                    'user_id' => $memberIds->random(),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }
}
