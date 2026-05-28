<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $testUser = User::factory()->create([
            'name' => 'Тестовый пользователь',
            'email' => 'test@boardy.local',
            'password' => Hash::make('password'),
        ]);

        $users = User::factory()->count(4)->create();

        $allUsers = $users->push($testUser);

        $posts = Post::factory()->count(10)->create([
            'user_id' => fn () => $allUsers->random()->id,
        ]);

        Comment::factory()->count(25)->create([
            'post_id' => fn () => $posts->random()->id,
            'user_id' => fn () => $allUsers->random()->id,
        ]);
    }
}
