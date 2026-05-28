<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Boardy — OAuth authorization</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 40px;
            color: #111827;
        }

        .card {
            max-width: 640px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
            font-size: 28px;
        }

        .muted {
            color: #6b7280;
            margin-bottom: 20px;
        }

        .client {
            padding: 16px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        button {
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 16px;
            cursor: pointer;
        }

        .approve {
            background: #16a34a;
            color: white;
        }

        .deny {
            background: #dc2626;
            color: white;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>Разрешить доступ?</h1>

    <p class="muted">
        Приложение запрашивает доступ к вашему аккаунту Boardy.
    </p>

    <div class="client">
        <strong>Клиент:</strong>
        {{ $client->name ?? 'OAuth client' }}
    </div>

    @if (! empty($scopes))
        <div class="client">
            <strong>Запрошенные scopes:</strong>

            <ul>
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description ?? $scope->id ?? $scope }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf

            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">

            <button type="submit" class="approve">
                Разрешить
            </button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')

            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">

            <button type="submit" class="deny">
                Отказаться
            </button>
        </form>
    </div>
</div>
</body>
</html>
