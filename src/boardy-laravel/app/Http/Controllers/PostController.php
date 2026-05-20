<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return redirect()
            ->route('posts.show', $post)
            ->with('success', 'Пост создан');
    }

    public function show(Post $post): View
    {
        $post->load([
            'author',
            'comments.author',
        ]);

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
