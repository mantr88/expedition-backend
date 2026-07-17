<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    protected $signature = 'user:create {name} {email} {--password=}';

    protected $description = 'Create a new active user';

    public function handle()
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->option('password') ?: Str::password(16);

        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists.");

            return 1;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->info("User {$name} <{$email}> created successfully.");
        $this->info("Password: {$password}");

        return 0;
    }
}
