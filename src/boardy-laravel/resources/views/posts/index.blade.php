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

    <div id="posts-feed">
        @forelse ($posts as $post)
            <div class="card mb-3" id="post-{{ $post->id }}">
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
            <div class="alert alert-info" id="empty-posts-message">
                Постов пока нет.
            </div>
        @endforelse
    </div>

    {{ $posts->links() }}

    <script>
        const wsUrl = window.location.protocol === 'https:'
            ? 'wss://api.belyaevubuntu.ru/ws'
            : 'ws://127.0.0.1:8000/ws';

        function connectBoardyWebSocket() {
            const ws = new WebSocket(wsUrl);

            ws.onopen = () => {
                console.log('WS connected:', wsUrl);
            };

            ws.onmessage = (event) => {
                try {
                    const message = JSON.parse(event.data);

                    if (message.type === 'new_post' && message.post) {
                        prependPost(message.post);
                    }
                } catch (error) {
                    console.error('WS message parse error:', error);
                }
            };

            ws.onerror = (error) => {
                console.error('WS error:', error);
            };

            ws.onclose = () => {
                console.warn('WS closed. Reconnecting in 3 seconds...');
                setTimeout(connectBoardyWebSocket, 3000);
            };
        }

        function prependPost(post) {
            const feed = document.getElementById('posts-feed');

            if (!feed) {
                return;
            }

            const emptyMessage = document.getElementById('empty-posts-message');

            if (emptyMessage) {
                emptyMessage.remove();
            }

            if (document.getElementById(`post-${post.id}`)) {
                return;
            }

            const card = document.createElement('div');
            card.className = 'card mb-3';
            card.id = `post-${post.id}`;

            card.innerHTML = `
                <div class="card-body">
                    <h3 class="h5">
                        <a href="/posts/${encodeURIComponent(post.id)}" class="text-decoration-none">
                            ${escapeHtml(post.title)}
                        </a>
                    </h3>

                    <div class="text-muted small mb-2">
                        ${escapeHtml(post.author)} · ${escapeHtml(post.created_at)}
                    </div>

                    <p class="mb-0">
                        ${escapeHtml(limitText(post.body, 220))}
                    </p>
                </div>
            `;

            feed.prepend(card);
        }

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        function limitText(value, maxLength) {
            const text = String(value ?? '');

            if (text.length <= maxLength) {
                return text;
            }

            return text.slice(0, maxLength) + '...';
        }

        connectBoardyWebSocket();
    </script>
@endsection
