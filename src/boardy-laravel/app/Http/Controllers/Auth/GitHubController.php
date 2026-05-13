<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GitHubController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        $githubId = (string) $githubUser->getId();

        $name = $githubUser->getName()
            ?: $githubUser->getNickname()
            ?: 'GitHub User';

        $email = $githubUser->getEmail()
            ?: 'github_' . $githubId . '@github.local';

        $user = User::where('github_id', $githubId)->first();

        if (!$user) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            $user->update([
                'github_id' => $githubId,
                'name' => $name,
            ]);
        } else {
            $user = User::create([
                'github_id' => $githubId,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()
            ->route('posts.index')
            ->with('success', 'Вы вошли через GitHub');
    }
}
