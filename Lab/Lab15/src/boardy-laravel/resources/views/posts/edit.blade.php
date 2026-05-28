@extends('layouts.boardy')

@section('title', 'Редактировать пост')

@section('content')
    <h1 class="mb-4">Редактировать пост</h1>

    <form method="POST" action="{{ route('posts.update', $post) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="title" class="form-label">Заголовок</label>
            <input
                type="text"
                name="title"
                id="title"
                class="form-control"
                value="{{ old('title', $post->title) }}"
                required
            >
        </div>

        <div class="mb-3">
            <label for="body" class="form-label">Текст</label>
            <textarea
                name="body"
                id="body"
                rows="8"
                class="form-control"
                required
            >{{ old('body', $post->body) }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary">
            Сохранить
        </button>

        <a href="{{ route('posts.show', $post) }}" class="btn btn-secondary">
            Отмена
        </a>
    </form>
@endsection
