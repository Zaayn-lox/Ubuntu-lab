<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Boardy — OAuth Callback</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            padding: 40px;
            color: #111827;
        }

        .card {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
            font-size: 40px;
        }

        code {
            display: block;
            word-break: break-all;
            background: #f9fafb;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-top: 8px;
            line-height: 1.5;
        }

        .ok {
            color: #16a34a;
            font-weight: bold;
            font-size: 20px;
        }

        .bad {
            color: #dc2626;
            font-weight: bold;
            font-size: 20px;
        }

        .info {
            color: #2563eb;
            font-weight: bold;
            font-size: 20px;
        }

        .muted {
            color: #6b7280;
        }

        .btn {
            display: inline-block;
            margin-top: 20px;
            background: #2563eb;
            color: white;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>OAuth callback</h1>

    @if (request('code'))
        <p class="ok">Authorization code получен.</p>

        <p><strong>code:</strong></p>
        <code id="oauth-code">{{ request('code') }}</code>

        <p><strong>state:</strong></p>
        <code id="oauth-state">{{ request('state') }}</code>

        <p id="oauth-status" class="info">
            Проверяем, можно ли автоматически обменять code на access_token...
        </p>

        <p class="muted">
            Если OAuth был запущен через кнопку на сайте, страница автоматически получит access_token.
            Если OAuth был запущен вручную через ссылку, скопируй code и обменяй его через curl.
        </p>

        <a href="/posts" class="btn">Вернуться к постам</a>
    @else
        <p class="bad">Authorization code не найден.</p>

        <p>Текущий URL:</p>
        <code>{{ request()->fullUrl() }}</code>

        <a href="/posts" class="btn">Вернуться к постам</a>
    @endif
</div>

@if (request('code'))
    <script type="module">
        import { handleCallback } from '/js/auth.js';

        const status = document.getElementById('oauth-status');

        const hasPkceVerifier = sessionStorage.getItem('pkce_verifier');
        const hasOAuthState = sessionStorage.getItem('oauth_state');

        if (!hasPkceVerifier || !hasOAuthState) {
            status.className = 'info';
            status.textContent = 'PKCE verifier в sessionStorage не найден. Это нормально для ручной проверки через curl.';
        } else {
            handleCallback()
                .then((token) => {
                    if (!token) {
                        status.className = 'bad';
                        status.textContent = 'Access token не получен.';
                        return;
                    }

                    status.className = 'ok';
                    status.textContent = 'Access token получен и сохранён в sessionStorage. Сейчас вернёмся к постам...';

                    setTimeout(() => {
                        window.location.href = '/posts';
                    }, 1200);
                })
                .catch((error) => {
                    console.error(error);

                    status.className = 'bad';
                    status.textContent = 'Ошибка обмена code на token: ' + error.message;
                });
        }
    </script>
@endif
</body>
</html>
