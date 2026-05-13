@extends('layouts.boardy')

@section('title', 'Создать пост')

@section('content')
    <h1 class="mb-4">Создать пост</h1>

    <form method="POST" action="{{ route('posts.store') }}">
        @csrf

        <div class="mb-3">
            <label for="title" class="form-label">Заголовок</label>
            <input
                type="text"
                name="title"
                id="title"
                class="form-control"
                value="{{ old('title') }}"
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
            >{{ old('body') }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary">
            Создать
        </button>

        <a href="{{ route('posts.index') }}" class="btn btn-secondary">
            Отмена
        </a>
    </form>
@endsection
