@extends('layouts.boardy')

@section('title', 'Все посты')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Все посты</h1>

        @auth
            <a href="{{ route('posts.create') }}" class="btn btn-primary">
                Добавить пост
            </a>
        @endauth
    </div>

    @forelse ($posts as $post)
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="h5">
                    <a href="{{ route('posts.show', $post) }}" class="text-decoration-none">
                        {{ $post->title }}
                    </a>
                </h3>

                <div class="text-muted small mb-2">
                    {{ $post->author->name }} · {{ $post->created_at->format('Y-m-d H:i') }}
                </div>

                <p class="mb-0">
                    {{ Str::limit($post->body, 220) }}
                </p>
            </div>
        </div>
    @empty
        <div class="alert alert-info">
            Постов пока нет.
        </div>
    @endforelse

    {{ $posts->links() }}
@endsection
