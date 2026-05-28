<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>OAuth Callback</title>
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

        code, pre {
            display: block;
            word-break: break-all;
            white-space: pre-wrap;
            background: #f9fafb;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-top: 8px;
        }

        .ok {
            color: #16a34a;
            font-weight: bold;
        }

        .bad {
            color: #dc2626;
            font-weight: bold;
        }

        .info {
            color: #4b5563;
        }

        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 16px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>OAuth callback</h1>

    @if (request('code'))
        <p class="ok">Authorization code получен.</p>

        <p><strong>code:</strong></p>
        <code>{{ request('code') }}</code>

        <p><strong>state:</strong></p>
        <code>{{ request('state') }}</code>

        <p id="token-status" class="info">
            Выполняется обмен authorization code на access_token...
        </p>

        <pre id="debug-output"></pre>
    @else
        <p class="bad">Authorization code не найден.</p>

        <p><strong>Текущий URL:</strong></p>
        <code>{{ request()->fullUrl() }}</code>
    @endif

    <a class="btn" href="{{ route('posts.index') }}">
        Вернуться к постам
    </a>
</div>

<script>
    const CLIENT_ID = @json(config('services.passport.client_id') ?: 'a1e20b09-d9e2-435d-81dc-855559999787');
    const REDIRECT_URI = window.location.origin + '/oauth/callback';

    const statusElement = document.getElementById('token-status');
    const debugElement = document.getElementById('debug-output');

    function showDebug(data) {
        if (debugElement) {
            debugElement.textContent = JSON.stringify(data, null, 2);
        }

        console.log('OAuth callback debug:', data);
    }

    async function exchangeToken() {
        if (!statusElement) {
            return;
        }

        const params = new URLSearchParams(window.location.search);

        const code = params.get('code');
        const state = params.get('state');

        const savedState = sessionStorage.getItem('oauth_state');
        const verifier = sessionStorage.getItem('pkce_verifier');

        const debug = {
            client_id: CLIENT_ID,
            redirect_uri: REDIRECT_URI,
            code_exists: Boolean(code),
            code_length: code ? code.length : 0,
            verifier_exists: Boolean(verifier),
            verifier_length: verifier ? verifier.length : 0,
            state: state,
            saved_state: savedState,
            state_matches: state === savedState,
        };

        showDebug(debug);

        if (!code) {
            statusElement.className = 'bad';
            statusElement.textContent = 'Authorization code отсутствует.';
            return;
        }

        if (!CLIENT_ID) {
            statusElement.className = 'bad';
            statusElement.textContent = 'OAuth Client ID пустой.';
            return;
        }

        if (!verifier) {
            statusElement.className = 'bad';
            statusElement.textContent = 'PKCE verifier не найден в sessionStorage.';
            return;
        }

        if (!savedState || state !== savedState) {
            statusElement.className = 'bad';
            statusElement.textContent = 'OAuth state не совпадает.';
            return;
        }

        const body = new URLSearchParams();

        body.set('grant_type', 'authorization_code');
        body.set('client_id', CLIENT_ID);
        body.set('redirect_uri', REDIRECT_URI);
        body.set('code', code);
        body.set('code_verifier', verifier);

        const response = await fetch('/oauth/token', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
            },
            body: body.toString(),
        });

        const data = await response.json();

        showDebug({
            ...debug,
            token_status: response.status,
            token_response: data,
        });

        if (!response.ok) {
            statusElement.className = 'bad';
            statusElement.textContent =
                'Ошибка обмена code на token: ' +
                (data.error_description || data.message || data.error || 'unknown error');
            return;
        }

        sessionStorage.setItem('boardy_access_token', data.access_token);
        sessionStorage.removeItem('pkce_verifier');
        sessionStorage.removeItem('oauth_state');

        statusElement.className = 'ok';
        statusElement.textContent = 'Access token получен и сохранён. Сейчас произойдёт переход к постам...';

        setTimeout(() => {
            window.location.href = '{{ route('posts.index') }}';
        }, 1000);
    }

    exchangeToken();
</script>
</body>
</html>
