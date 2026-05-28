@extends('layouts.boardy')

@section('title', $post->title)

@section('content')
    <a href="{{ route('posts.index') }}" class="btn btn-link px-0 mb-3">
        ← Назад к постам
    </a>

    <article class="card mb-4">
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

                    <form
                        method="POST"
                        action="{{ route('posts.destroy', $post) }}"
                        onsubmit="return confirm('Удалить пост?')"
                    >
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="btn btn-outline-danger">
                            Удалить
                        </button>
                    </form>
                </div>
            @endcan
        </div>
    </article>

    <section class="card mb-4">
        <div class="card-body">
            <h2 class="mb-3">Комментарии</h2>

            <div
                id="comments-root"
                data-post-id="{{ $post->id }}"
                data-user-name="{{ auth()->user()->name ?? 'Guest' }}"
            >
                <div class="alert alert-light border mb-0">
                    Загрузка комментариев...
                </div>
            </div>
        </div>
    </section>

    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>

    <script type="module" src="/js/pkce.js"></script>
    <script type="module" src="/js/auth.js"></script>
    <script type="module" src="/js/comments.js"></script>
@endsection
