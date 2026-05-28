<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UserObserver
{
    public function updated(User $user): void
    {
        if (! $user->wasChanged('name')) {
            return;
        }

        try {
            Redis::publish('user.renamed', json_encode([
                'id' => $user->id,
                'new_name' => $user->name,
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::warning('Redis publish user.renamed failed: ' . $e->getMessage());
        }
    }
}
