<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;

class PostController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $posts = Post::with('author')
            ->latest()
            ->paginate(10);

        return view('posts.index', compact('posts'));
    }

    public function create(): View
    {
        $this->authorize('create', Post::class);

        return view('posts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Post::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $post = $request->user()->posts()->create($data);
        $post->load('author');

        try {
            Redis::publish('new_post', json_encode([
                'id' => $post->id,
                'title' => $post->title,
                'body' => $post->body,
                'author' => $post->author->name,
                'created_at' => $post->created_at->format('Y-m-d H:i'),
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::warning('Redis publish new_post failed: ' . $e->getMessage());
        }

        return redirect()
            ->route('posts.show', $post)
            ->with('success', 'Пост создан');
    }

    public function show(Post $post): View
    {
        $post->load('author');

        return view('posts.show', compact('post'));
    }

    public function edit(Post $post): View
    {
        $this->authorize('update', $post);

        return view('posts.edit', compact('post'));
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $post->update($data);

        return redirect()
            ->route('posts.show', $post)
            ->with('success', 'Пост обновлён');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return redirect()
            ->route('posts.index')
            ->with('success', 'Пост удалён');
    }
}
