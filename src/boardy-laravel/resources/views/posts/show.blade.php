@extends('layouts.boardy')

@section('title', $post->title)

@section('content')
    <a href="{{ route('posts.index') }}" class="btn btn-link px-0 mb-3">
        ← Назад к постам
    </a>

    <div class="card mb-4">
        <div class="card-body">
            <h1>{{ $post->title }}</h1>

            <div class="text-muted mb-3">
                {{ $post->author->name }} · {{ $post->created_at->format('Y-m-d H:i') }}
            </div>

            <p style="white-space: pre-line">{{ $post->body }}</p>

            @can('update', $post)
                <div class="d-flex gap-2 mt-4">
                    <a href="{{ route('posts.edit', $post) }}" class="btn btn-outline-primary">
                        Редактировать
                    </a>

                    <form method="POST" action="{{ route('posts.destroy', $post) }}"
                          onsubmit="return confirm('Удалить пост?')">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="btn btn-outline-danger">
                            Удалить
                        </button>
                    </form>
                </div>
            @endcan
        </div>
    </div>

    <h2 class="mb-3">Комментарии</h2>

    @forelse ($post->comments as $comment)
        <div class="card mb-2">
            <div class="card-body">
                <div class="fw-bold">
                    {{ $comment->author->name }}
                </div>

                <div class="text-muted small mb-2">
                    {{ $comment->created_at->format('Y-m-d H:i') }}
                </div>

                <p class="mb-0" style="white-space: pre-line">{{ $comment->body }}</p>
            </div>
        </div>
    @empty
        <div class="alert alert-light border">
            Комментариев пока нет.
        </div>
    @endforelse

    @auth
        <div class="card mt-4">
            <div class="card-body">
                <h3 class="h5">Добавить комментарий</h3>

                <form method="POST" action="{{ route('comments.store') }}">
                    @csrf

                    <input type="hidden" name="post_id" value="{{ $post->id }}">

                    <div class="mb-3">
                        <label for="body" class="form-label">Комментарий</label>
                        <textarea
                            name="body"
                            id="body"
                            rows="4"
                            class="form-control"
                            required
                        >{{ old('body') }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-success">
                        Отправить
                    </button>
                </form>
            </div>
        </div>
    @else
        <div class="alert alert-warning mt-4">
            Чтобы оставить комментарий, войдите в аккаунт.
        </div>
    @endauth
@endsection
