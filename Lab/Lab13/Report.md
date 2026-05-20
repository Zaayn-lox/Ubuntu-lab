# Практика 13 — WebSocket: реалтайм-лента

## Цель работы

Цель практики — добавить в проект Boardy реалтайм-обновление ленты постов через WebSocket.

До выполнения практики лента постов обновлялась только после ручной перезагрузки страницы. После реализации WebSocket новый пост, созданный одним пользователем, автоматически появляется у других пользователей без нажатия F5.

## Общая схема работы

```text
Пользователь создаёт пост в Laravel
        ↓
Laravel сохраняет пост в MySQL
        ↓
Laravel отправляет HTTP-callback в FastAPI:
POST http://127.0.0.1:8000/internal/broadcast
        ↓
FastAPI получает событие
        ↓
ConnectionManager рассылает JSON всем WebSocket-клиентам
        ↓
JavaScript в Blade получает событие
        ↓
Новый пост добавляется в ленту без перезагрузки страницы
```

Используемые компоненты:

- Laravel — основное веб-приложение;
- FastAPI — WebSocket-сервер;
- Nginx — reverse proxy для API и WebSocket;
- JavaScript — клиент WebSocket в Blade-шаблоне;
- MySQL — хранение постов и пользователей.

## Исходные данные проекта

Основной сайт:

```text
https://belyaevubuntu.ru
```

API-домен:

```text
https://api.belyaevubuntu.ru
```

Laravel-проект на сервере:

```text
/var/www/boardy
```

FastAPI-проект на сервере:

```text
/opt/boardy-api
```

Основные изменённые файлы:

```text
/opt/boardy-api/main.py
/opt/boardy-api/routers/ws.py
/var/www/boardy/app/Http/Controllers/PostController.php
/var/www/boardy/resources/views/posts/index.blade.php
/etc/nginx/sites-available/boardy-api
```

Файлы для сдачи в репозитории:

```text
Lab/Lab13/Report.md
Lab/Lab13/screenshots/
src/boardy-api/main.py
src/boardy-api/routers/ws.py
src/boardy-laravel/app/Http/Controllers/PostController.php
src/boardy-laravel/resources/views/posts/index.blade.php
config/nginx/boardy-api
```

---

# Часть A. FastAPI: WebSocket

## Задание 1. ConnectionManager и endpoint `/ws`

В FastAPI был создан новый файл:

```text
/opt/boardy-api/routers/ws.py
```

В нём реализован `ConnectionManager`, который хранит список активных WebSocket-подключений и умеет рассылать сообщение всем подключённым клиентам.

Основная логика:

```python
class ConnectionManager:
    def __init__(self) -> None:
        self.active: List[WebSocket] = []

    async def connect(self, ws: WebSocket) -> None:
        await ws.accept()
        self.active.append(ws)

    def disconnect(self, ws: WebSocket) -> None:
        if ws in self.active:
            self.active.remove(ws)

    async def broadcast(self, message: dict) -> None:
        dead_connections: List[WebSocket] = []

        for ws in self.active:
            try:
                await ws.send_text(json.dumps(message, ensure_ascii=False))
            except Exception:
                dead_connections.append(ws)

        for ws in dead_connections:
            self.disconnect(ws)
```

Также был добавлен endpoint:

```python
@router.websocket("/ws")
async def websocket_endpoint(ws: WebSocket):
    await manager.connect(ws)

    try:
        while True:
            await ws.receive_text()
    except WebSocketDisconnect:
        manager.disconnect(ws)
    except Exception:
        manager.disconnect(ws)
```

Проверка выполнялась Python-клиентом:

```bash
cd /opt/boardy-api
source venv/bin/activate

python3 -c "
import asyncio, websockets

async def listen():
    async with websockets.connect('ws://127.0.0.1:8000/ws') as ws:
        print('connected')
        async for msg in ws:
            print('got:', msg)

asyncio.run(listen())
"
```

Результат:

```text
connected
```

**Скриншот:** `01-ws-connected.png`

### Почему `self.active` — список в памяти процесса, а не в базе данных?

`self.active` хранит не обычные данные, а живые WebSocket-соединения. WebSocket-соединение — это объект активного сетевого подключения между браузером и сервером. Такой объект нельзя сохранить в MySQL как строку или запись таблицы.

База данных подходит для хранения постоянных данных: пользователей, постов, комментариев. Но активные WebSocket-сессии существуют только пока открыт процесс FastAPI и пока клиент подключён к серверу.

Поэтому список активных клиентов хранится в оперативной памяти процесса Uvicorn.

### Что произойдёт, если Uvicorn перезапустится?

При перезапуске Uvicorn:

1. процесс FastAPI завершится;
2. список `self.active` будет очищен;
3. все текущие WebSocket-соединения оборвутся;
4. браузеры получат событие `onclose`;
5. клиентский JavaScript должен переподключиться заново.

Именно поэтому в Blade-шаблоне реализовано автоматическое переподключение через `setTimeout(connectBoardyWebSocket, 3000)`.

---

## Задание 2. Endpoint `/internal/broadcast`

В `main.py` был подключён WebSocket-роутер и добавлен внутренний endpoint:

```python
from fastapi import FastAPI, Request
from routers import ws

app.include_router(ws.router)

@app.post("/internal/broadcast")
async def internal_broadcast(request: Request):
    data = await request.json()

    await ws.manager.broadcast({
        "type": "new_post",
        "post": data,
    })

    return {"ok": True}
```

Проверка выполнялась командой:

```bash
curl -X POST http://127.0.0.1:8000/internal/broadcast \
  -H "Content-Type: application/json" \
  -d '{"id":1,"title":"Тест WS","body":"Проверка broadcast","author":"Паша","created_at":"2026-05-13 21:00"}'
```

WebSocket-клиент получил JSON-событие.

**Скриншот:** `02-broadcast.png`

### Почему `/internal/broadcast` не требует JWT-авторизации?

`/internal/broadcast` — это технический endpoint для связи между внутренними сервисами. Его вызывает не пользователь из браузера, а Laravel-приложение с того же сервера.

JWT нужен для проверки действий пользователя. В данном случае endpoint используется как внутренний транспорт между Laravel и FastAPI.

### Какой риск остаётся?

Если `/internal/broadcast` оставить открытым наружу, любой внешний пользователь сможет отправить POST-запрос и подделать событие в реалтайм-ленте.

Например, злоумышленник сможет вызвать:

```bash
curl -X POST https://api.belyaevubuntu.ru/internal/broadcast \
  -H "Content-Type: application/json" \
  -d '{"title":"Фейковый пост"}'
```

И этот фейковый пост может появиться у всех подключённых клиентов.

### Как этот риск закрывает Nginx?

В Nginx добавлено ограничение:

```nginx
location /internal {
    allow 127.0.0.1;
    deny all;

    proxy_pass http://127.0.0.1:8000;
}
```

Это разрешает доступ к `/internal` только с локального сервера и запрещает внешние запросы.

---

## Задание 3. Два клиента

Для проверки были открыты два WebSocket-клиента. После отправки события через `/internal/broadcast` оба клиента получили один и тот же JSON.

**Скриншот:** `03-two-clients.png`

### Что произойдёт, если один из клиентов отключился, а broadcast уже начался?

Если клиент отключился, попытка отправить ему сообщение через `ws.send_text()` вызовет исключение. В коде это обрабатывается в методе `broadcast()`:

```python
try:
    await ws.send_text(json.dumps(message, ensure_ascii=False))
except Exception:
    dead_connections.append(ws)
```

После завершения обхода все мёртвые подключения удаляются:

```python
for ws in dead_connections:
    self.disconnect(ws)
```

Такой подход нужен, чтобы один отключившийся клиент не сломал рассылку остальным клиентам.


---

# Часть B. Laravel: HTTP-callback

## Задание 4. Обновление `PostController`

В Laravel был обновлён метод `store()` в `PostController.php`.

После создания поста Laravel отправляет POST-запрос в FastAPI:

```php
try {
    Http::timeout(2)->post('http://127.0.0.1:8000/internal/broadcast', [
        'id' => $post->id,
        'title' => $post->title,
        'body' => $post->body,
        'author' => $post->author->name,
        'created_at' => $post->created_at->format('Y-m-d H:i'),
    ]);
} catch (\Throwable $e) {
    Log::warning('WS broadcast failed: ' . $e->getMessage());
}
```

Полная логика метода:

```php
public function store(Request $request): RedirectResponse
{
    $this->authorize('create', Post::class);

    $data = $request->validate([
        'title' => ['required', 'string', 'max:200'],
        'body' => ['required', 'string', 'max:5000'],
    ]);

    $post = $request->user()->posts()->create($data);
    $post->load('author');

    try {
        Http::timeout(2)->post('http://127.0.0.1:8000/internal/broadcast', [
            'id' => $post->id,
            'title' => $post->title,
            'body' => $post->body,
            'author' => $post->author->name,
            'created_at' => $post->created_at->format('Y-m-d H:i'),
        ]);
    } catch (\Throwable $e) {
        Log::warning('WS broadcast failed: ' . $e->getMessage());
    }

    return redirect()
        ->route('posts.show', $post)
        ->with('success', 'Пост создан');
}
```

После создания поста был проверен файл:

```bash
tail -n 50 /var/www/boardy/storage/logs/laravel.log
```

Также использовалась проверка:

```bash
grep -n "WS broadcast failed" storage/logs/laravel.log || echo "OK: WS broadcast failed not found"
```

Ошибки `WS broadcast failed` не было.

**Скриншот:** `04-laravel-log.png`

### Зачем нужен `timeout(2)`?

`timeout(2)` ограничивает ожидание ответа от FastAPI двумя секундами.

Это важно, потому что создание поста не должно зависеть от реалтайм-механизма. Если FastAPI временно недоступен, пост всё равно должен быть создан в базе Laravel.

### Что случится, если FastAPI недоступен и timeout не указан?

Если не указать timeout, HTTP-запрос может зависнуть до системного таймаута. Пользователь будет ждать слишком долго после нажатия кнопки создания поста.

Без timeout проблема в дополнительном сервисе может ухудшить работу основной функции сайта — создания постов.

---

## Задание 5. Проверка callback

После создания поста через Laravel в логах Uvicorn появился входящий запрос:

```text
POST /internal/broadcast HTTP/1.1" 200 OK
```

Это подтверждает, что Laravel действительно вызвал FastAPI.

**Скриншот:** `05-callback.png`

### Почему HTTP-callback называют костылём?

HTTP-callback в этой архитектуре работает, но он является временным и хрупким решением.

Основные проблемы:

1. **Laravel зависит от доступности FastAPI.** Если FastAPI не работает, реалтайм-событие не отправится.

2. **Запрос синхронный.** Laravel создаёт пост и сразу делает HTTP-запрос к FastAPI. Даже с timeout это дополнительная задержка в пользовательском сценарии.

3. **Нет очереди событий.** Если FastAPI недоступен в момент создания поста, событие теряется. Когда FastAPI вернётся в работу, он не узнает о пропущенном посте.

4. **Проблемы при нескольких worker-процессах.** Если Uvicorn будет запущен в несколько workers, у каждого процесса будет свой `ConnectionManager`. Часть клиентов может быть подключена к одному worker, часть — к другому. Тогда broadcast из одного процесса не дойдёт до клиентов другого процесса.

5. **Сервисы связаны напрямую.** Laravel должен знать адрес FastAPI и конкретный endpoint `/internal/broadcast`. Это повышает связанность системы.

Более правильное решение — использовать очередь или Pub/Sub, например Redis. Тогда Laravel публикует событие в Redis, а FastAPI подписывается на канал и рассылает события клиентам.

---

# Часть C. JavaScript-клиент

## Задание 6. WebSocket в Blade

В файл:

```text
resources/views/posts/index.blade.php
```

был добавлен блок ленты:

```blade
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
```

И JavaScript WebSocket-клиент:

```javascript
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
```

В DevTools был проверен WebSocket-запрос:

```text
wss://api.belyaevubuntu.ru/ws
```

Статус соединения:

```text
101 Switching Protocols
```

**Скриншот:** `06-devtools-ws.png`

### Почему на локалке нужно `ws://`, а на проде `wss://`?

`ws://` — это обычный WebSocket без TLS-шифрования. Он подходит для локальной разработки, например:

```text
ws://127.0.0.1:8000/ws
```

`wss://` — это WebSocket поверх TLS. Он используется на проде, когда сайт открыт через HTTPS.

Так как основной сайт работает по HTTPS:

```text
https://belyaevubuntu.ru
```

браузер должен подключаться к WebSocket тоже безопасно:

```text
wss://api.belyaevubuntu.ru/ws
```

### Что произойдёт, если использовать `wss://` без TLS?

Если сервер не настроен на TLS, браузер не сможет установить защищённое соединение. В DevTools появится ошибка WebSocket-подключения, например SSL/protocol error.

---

## Задание 7. Два браузера

Для проверки были открыты два браузера с лентой постов.

Порядок проверки:

1. В первом браузере открыт сайт и форма создания поста.
2. Во втором браузере открыта страница `/posts`.
3. В первом браузере создан новый пост.
4. Во втором браузере новый пост появился в ленте без перезагрузки страницы.

**Скриншот:** `07-two-browsers.png`

Также во вкладке DevTools:

```text
Network → WS → Messages
```

был проверен входящий JSON-фрейм:

```json
{
  "type": "new_post",
  "post": {
    "id": 1,
    "title": "Новый пост",
    "body": "Текст поста",
    "author": "Zaayn-lox",
    "created_at": "2026-05-20 18:00"
  }
}
```

**Скриншот:** `08-devtools-frame.png`

---

## Задание 8. XSS

Был создан пост с телом:

```html
<script>alert('xss')</script>
```

Результат:

- alert не появился;
- JavaScript не выполнился;
- строка отобразилась как обычный текст.

**Скриншот:** `09-xss.png`

### Что делает функция `escapeHtml()`?

Функция `escapeHtml()` превращает потенциально опасный HTML-код в безопасный текст.

Код функции:

```javascript
function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
```

Она создаёт временный `div`, записывает пользовательские данные в `textContent`, а затем забирает безопасный HTML через `innerHTML`.

Например строка:

```html
<script>alert('xss')</script>
```

не будет выполнена браузером, а будет показана как текст.

### Что случится, если вставить данные напрямую в `innerHTML` без экранирования?

Если напрямую вставить пользовательский ввод в `innerHTML`, злоумышленник сможет внедрить HTML или JavaScript.

Например:

```javascript
card.innerHTML = `<p>${post.body}</p>`;
```

при `post.body = "<script>alert('xss')</script>"` может привести к выполнению вредоносного кода в браузере пользователя.

Это XSS-уязвимость.

---

## Задание 9. Переподключение

В JavaScript реализовано автоматическое переподключение:

```javascript
ws.onclose = () => {
    console.warn('WS closed. Reconnecting in 3 seconds...');
    setTimeout(connectBoardyWebSocket, 3000);
};
```

Проверка:

1. В браузере открыта страница `/posts`.
2. В DevTools открыта вкладка `Network → WS`.
3. FastAPI был остановлен:

```bash
sudo systemctl stop boardy-api
```

4. Через несколько секунд FastAPI был снова запущен:

```bash
sudo systemctl start boardy-api
```

5. В DevTools стало видно старое закрытое WebSocket-соединение и новое открытое соединение со статусом `101`.

**Скриншот:** `10-reconnect.png`


---

# Часть D. Nginx

## Задание 10. WS-проксирование

Был обновлён Nginx-конфиг API-домена:

```text
/etc/nginx/sites-available/boardy-api
```

В server-блок для `api.belyaevubuntu.ru` был добавлен `location /ws`:

```nginx
location /ws {
    proxy_pass http://127.0.0.1:8000;
    proxy_http_version 1.1;

    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;

    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    proxy_read_timeout 86400;
    proxy_send_timeout 86400;
}
```

После изменения была выполнена проверка:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Проверка WSS:

```bash
cd /opt/boardy-api
source venv/bin/activate

python3 -c "
import asyncio, websockets

async def listen():
    async with websockets.connect('wss://api.belyaevubuntu.ru/ws') as ws:
        print('connected via wss')

asyncio.run(listen())
"
```

Результат:

```text
connected via wss
```

**Скриншот:** `11-nginx-ws.png`

### Что сломается, если убрать `proxy_http_version 1.1`?

WebSocket Upgrade работает через HTTP/1.1. Если убрать эту строку, Nginx может отправить запрос к FastAPI как обычный HTTP/1.0-запрос. В таком случае FastAPI не получит корректное WebSocket-подключение.

Симптом: запрос `/ws` будет возвращать ошибку или 404 вместо статуса `101 Switching Protocols`.

### Что сломается, если убрать `proxy_set_header Upgrade $http_upgrade`?

Этот заголовок сообщает backend-серверу, что клиент хочет перейти с обычного HTTP на WebSocket. Без него FastAPI не поймёт, что запрос нужно обработать как WebSocket.

### Что сломается, если убрать `proxy_read_timeout`?

WebSocket-соединение является долгоживущим. Если оставить стандартный короткий timeout, Nginx может закрывать соединение, даже если оно должно оставаться открытым.

В результате WebSocket будет постоянно отключаться.

---

## Задание 11. Закрытие `/internal`

В Nginx был добавлен блок:

```nginx
location /internal {
    allow 127.0.0.1;
    deny all;

    proxy_pass http://127.0.0.1:8000;

    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

Проверка внешнего доступа:

```bash
curl -i -X POST https://api.belyaevubuntu.ru/internal/broadcast \
  -H "Content-Type: application/json" \
  -d '{"id":999,"title":"external","body":"blocked"}'
```

Ожидаемый результат:

```text
HTTP/1.1 403 Forbidden
```

**Скриншот:** `12-internal-denied.png`

### Почему `/internal/broadcast` опасен без ограничения доступа?

Без ограничения доступа этот endpoint сможет вызвать любой внешний пользователь. Это позволит отправлять произвольные события в WebSocket-ленту.

Фактически злоумышленник сможет подделывать реалтайм-события, которые увидят все подключённые браузеры.

### Кто мог бы его вызвать?

Если endpoint открыт наружу, его может вызвать любой человек или бот из интернета через `curl`, Postman, браузерный fetch-запрос или собственный скрипт.

Именно поэтому `/internal` закрыт через Nginx и доступен только с `127.0.0.1`.

---

# Проверка результата

## Проверка 1. `/ws` отвечает 101

В DevTools:

```text
Network → WS → /ws
```

Статус:

```text
101 Switching Protocols
```

## Проверка 2. Broadcast работает

Команда:

```bash
curl -X POST http://127.0.0.1:8000/internal/broadcast \
  -H "Content-Type: application/json" \
  -d '{"id":1,"title":"Тест","body":"Привет"}'
```

WebSocket-клиент получает JSON.

## Проверка 3. Laravel вызывает FastAPI

После создания поста в Laravel в логах Uvicorn появляется:

```text
POST /internal/broadcast HTTP/1.1" 200 OK
```

## Проверка 4. Два браузера

Новый пост создаётся в первом браузере и появляется во втором браузере без F5.

## Проверка 5. XSS

Пост с телом:

```html
<script>alert('xss')</script>
```

отображается как текст. Alert не срабатывает.

## Проверка 6. Reconnect

После остановки и запуска FastAPI WebSocket переподключается автоматически.

## Проверка 7. `/internal` закрыт снаружи

Внешний запрос к:

```text
https://api.belyaevubuntu.ru/internal/broadcast
```

возвращает:

```text
403 Forbidden
```

---

# Возникшие проблемы и решения

## Проблема 1. `/ws` возвращал 404 через Nginx

При проверке `wss://api.belyaevubuntu.ru/ws` возникала ошибка:

```text
server rejected WebSocket connection: HTTP 404
```

В логах Uvicorn было видно:

```text
GET /ws HTTP/1.0" 404 Not Found
```

Причина: Nginx проксировал WebSocket как обычный HTTP-запрос без Upgrade-заголовков.

Решение: добавить в Nginx:

```nginx
proxy_http_version 1.1;
proxy_set_header Upgrade $http_upgrade;
proxy_set_header Connection "upgrade";
```

После этого `wss://api.belyaevubuntu.ru/ws` начал подключаться корректно.

---

## Проблема 2. Лог Laravel был пустой

После создания поста `tail laravel.log` не показывал новых строк.

Это оказалось нормальным поведением: Laravel пишет в лог только ошибки или явно указанные сообщения. В коде логирование настроено только в случае ошибки broadcast:

```php
Log::warning('WS broadcast failed: ' . $e->getMessage());
```

Если ошибки нет, лог может быть пустым. Поэтому для проверки использовалась команда:

```bash
grep -n "WS broadcast failed" storage/logs/laravel.log || echo "OK: WS broadcast failed not found"
```

---

## Проблема 3. Нужно было отличить локальный WS от WSS через Nginx

Локальная проверка:

```text
ws://127.0.0.1:8000/ws
```

показывала, что FastAPI работает.

Внешняя проверка:

```text
wss://api.belyaevubuntu.ru/ws
```

проверяла уже Nginx, TLS и Upgrade-заголовки.

Это помогло быстро понять, что проблема была не в FastAPI, а именно в Nginx.

---

# Итог

В результате практики была реализована реалтайм-лента постов в проекте Boardy.

Было сделано:

- создан WebSocket endpoint `/ws` в FastAPI;
- реализован `ConnectionManager`;
- добавлен внутренний endpoint `/internal/broadcast`;
- Laravel начал отправлять HTTP-callback в FastAPI после создания поста;
- в Blade-шаблон добавлен WebSocket-клиент;
- новый пост появляется в ленте без перезагрузки страницы;
- реализована защита от XSS через `escapeHtml()`;
- реализовано автоматическое переподключение WebSocket;
- настроен Nginx для проксирования WebSocket;
- закрыт внешний доступ к `/internal`.

Главный результат: теперь пользователи видят новые посты в ленте в реальном времени, без ручного обновления страницы.

---

# Список скриншотов

1. `01-ws-connected.png` — WebSocket-клиент подключился к `/ws`.
2. `02-broadcast.png` — `/internal/broadcast` отправил событие клиенту.
3. `03-two-clients.png` — два клиента получили одно событие.
4. `04-laravel-log.png` — после создания поста нет ошибки `WS broadcast failed`.
5. `05-callback.png` — Uvicorn получил `POST /internal/broadcast`.
6. `06-devtools-ws.png` — DevTools показывает WebSocket `101 Switching Protocols`.
7. `07-two-browsers.png` — пост появился во втором браузере без F5.
8. `08-devtools-frame.png` — входящий JSON-фрейм в DevTools.
9. `09-xss.png` — XSS-строка отображается как текст, alert не сработал.
10. `10-reconnect.png` — после перезапуска FastAPI WebSocket переподключился.
11. `11-nginx-ws.png` — WSS через Nginx подключается успешно.
12. `12-internal-denied.png` — внешний доступ к `/internal/broadcast` возвращает 403.
