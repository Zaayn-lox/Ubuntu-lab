# Практика 12 — Переход проекта Boardy на Laravel

## Цель работы

Цель практики — перенести учебный проект Boardy с самописного PHP-приложения на Laravel и реализовать базовую серверную архитектуру с использованием возможностей фреймворка.

В рамках работы было необходимо:

- сохранить старую версию проекта как legacy;
- установить Laravel 11;
- настроить Nginx на директорию `public`;
- создать отдельную базу данных для Laravel;
- реализовать модели, миграции и связи;
- создать CRUD для постов и комментариев;
- подключить авторизацию через Laravel Breeze;
- подключить OAuth-вход через GitHub с помощью Laravel Socialite;
- оформить отчёт и подготовить проект к сдаче через GitHub.

---

## Исходные данные проекта

Проект разворачивается на домене:

- основной домен: `https://belyaevubuntu.ru`
- legacy-проект: `/var/www/boardy-legacy`
- Laravel-проект: `/var/www/boardy`

Старый проект до этой практики состоял из:

- PHP-страниц;
- MySQL-базы `boardy`;
- PHP-сессий;
- React-страницы комментариев;
- FastAPI backend.

В 12-й практике старый проект не удалялся, а был сохранён как legacy:

- старый проект: `/var/www/boardy-legacy`
- новый Laravel-проект: `/var/www/boardy`

---

## 1. Установка Composer, PHP и расширений

Сначала была проверена текущая версия PHP и наличие PHP-FPM.

После этого были установлены необходимые расширения для Laravel:

- `mbstring`
- `xml`
- `bcmath`
- `curl`
- `mysql`
- `zip`
- `gd`

Также был установлен Composer.

Проверка выполнялась командами:

```bash
composer --version
php -v
php -m | grep -E "mbstring|xml|bcmath|curl|mysql|zip"
```

В результате PHP и Composer были готовы для установки Laravel.

**Скриншот:** `01-composer-php.png`

---

## 2. Сохранение legacy-проекта и установка Laravel

Старый проект был переименован:

```bash
sudo mv /var/www/boardy /var/www/boardy-legacy
```

После этого был создан новый Laravel-проект:

```bash
sudo composer create-project laravel/laravel boardy "^11.0"
```

Laravel был размещён в директории:

```text
/var/www/boardy
```

Старый проект остался доступен в директории:

```text
/var/www/boardy-legacy
```

Это важно, потому что старый код может понадобиться для сравнения, отката или переноса отдельных решений.

**Скриншот:** `02-folders.png`

---

## 3. Проверка версии Laravel

Версия Laravel была проверена командой:

```bash
php artisan --version
```

В результате был установлен Laravel 11.

**Скриншот:** `03-laravel-version.png`

---

## 4. Настройка Nginx

Nginx был перенастроен так, чтобы основной домен указывал не на корень Laravel-проекта, а на директорию:

```text
/var/www/boardy/public
```

Это обязательное требование для Laravel, потому что входной файл приложения находится здесь:

```text
/var/www/boardy/public/index.php
```

Ключевые строки в конфигурации Nginx:

```nginx
root /var/www/boardy/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

После изменения конфигурации были выполнены команды:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

**Скриншот:** `04-nginx-config.png`

После этого на домене открылась стартовая страница Laravel.

**Скриншот:** `05-laravel-welcome.png`

---

## 5. Создание новой базы данных

Для Laravel была создана отдельная база данных:

```text
boardy_main
```

Старая база `boardy` не удалялась, потому что она относится к legacy-проекту.

Создание базы:

```sql
CREATE DATABASE boardy_main
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Права были выданы пользователю `boardy`.

Проверка баз данных выполнялась через:

```bash
mysql -u boardy -p -e "SHOW DATABASES;"
```

**Скриншот:** `06-databases.png`

---

## 6. Настройка `.env`

В файле `.env` Laravel были указаны параметры подключения к новой базе данных:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=boardy_main
DB_USERNAME=boardy
DB_PASSWORD=********
```

Также для сессий использовался файловый драйвер:

```env
SESSION_DRIVER=file
CACHE_STORE=file
```

Файл `.env` не добавляется в git, потому что содержит секреты и локальные настройки.

Подключение к базе проверялось через Tinker:

```php
DB::connection()->getPdo()
```

**Скриншот:** `07-tinker-pdo.png`

---

## 7. Миграции

Были созданы модели и миграции:

```bash
php artisan make:model Post -m
php artisan make:model Comment -m
```

В таблице `posts` были созданы поля:

- `id`
- `user_id`
- `title`
- `body`
- `created_at`
- `updated_at`

В таблице `comments` были созданы поля:

- `id`
- `post_id`
- `user_id`
- `body`
- `created_at`
- `updated_at`

Миграции были применены командой:

```bash
php artisan migrate
```

Статус миграций проверялся командой:

```bash
php artisan migrate:status
```

**Скриншот:** `08-migrate-status.png`

Таблицы в MySQL проверялись командой:

```bash
mysql -u boardy -p boardy_main -e "SHOW TABLES;"
```

**Скриншот:** `09-show-tables.png`

---

## 8. Модели и связи

Были реализованы модели:

- `User`
- `Post`
- `Comment`

Связи:

### User

Пользователь имеет много постов:

```php
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}
```

Пользователь имеет много комментариев:

```php
public function comments(): HasMany
{
    return $this->hasMany(Comment::class);
}
```

### Post

Пост принадлежит пользователю:

```php
public function author(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id');
}
```

Пост имеет много комментариев:

```php
public function comments(): HasMany
{
    return $this->hasMany(Comment::class)->latest();
}
```

### Comment

Комментарий принадлежит посту:

```php
public function post(): BelongsTo
{
    return $this->belongsTo(Post::class);
}
```

Комментарий принадлежит пользователю:

```php
public function author(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id');
}
```

Связи проверялись через Tinker.

**Скриншот:** `10-model-relations.png`

---

## 9. Фабрики и сидер

Для наполнения базы тестовыми данными были созданы фабрики:

```bash
php artisan make:factory PostFactory --model=Post
php artisan make:factory CommentFactory --model=Comment
```

Был создан сидер, который добавляет:

- тестового пользователя;
- дополнительных пользователей;
- посты;
- комментарии.

Тестовый пользователь:

```text
email: test@boardy.local
password: password
```

После настройки сидера база пересобиралась командой:

```bash
php artisan migrate:fresh --seed
```

Количество записей проверялось через Tinker:

```php
\App\Models\User::count()
\App\Models\Post::count()
\App\Models\Comment::count()
```

**Скриншот:** `11-seed-counts.png`

---

## 10. Маршруты

Маршруты были настроены в `routes/web.php`.

Основные маршруты:

- `/posts` — список постов;
- `/posts/{post}` — просмотр одного поста;
- `/posts/create` — создание поста;
- `/posts/{post}/edit` — редактирование поста;
- `/comments` — создание комментария;
- `/login` — вход;
- `/register` — регистрация;
- `/auth/github` — вход через GitHub;
- `/auth/github/callback` — callback от GitHub.

Список маршрутов проверялся командой:

```bash
php artisan route:list
```

**Скриншот:** `12-route-list.png`

---

## 11. CRUD постов

Для постов был создан `PostController`.

Он реализует:

- вывод списка постов;
- просмотр одного поста;
- создание поста;
- редактирование поста;
- удаление поста.

Список постов отображается на странице:

```text
/posts
```

**Скриншот:** `13-posts-index.png`

Страница отдельного поста:

```text
/posts/{id}
```

**Скриншот:** `14-post-show.png`

Форма создания поста:

```text
/posts/create
```

**Скриншот:** `15-post-create.png`

После создания поста пользователь перенаправляется на страницу созданного поста.

**Скриншот:** `16-post-after-create.png`

---

## 12. Политики доступа

Для постов была создана политика:

```bash
php artisan make:policy PostPolicy --model=Post
```

Правила:

- создать пост может любой авторизованный пользователь;
- редактировать пост может только его автор;
- удалить пост может только его автор.

Проверка собственного поста:

**Скриншот:** `17-edit-own.png`

Проверка чужого поста:

**Скриншот:** `18-edit-foreign-403.png`

Если пользователь пытается редактировать чужой пост, Laravel возвращает ошибку `403 Forbidden`.

---

## 13. Удаление поста

Удаление поста реализовано через метод `destroy` в `PostController`.

После удаления пользователь возвращается на страницу списка постов.

**Скриншот:** `19-post-deleted.png`

---

## 14. Комментарии

Для комментариев был создан `CommentController`.

Комментарий может оставить только авторизованный пользователь.

Комментарий содержит:

- `post_id`
- `user_id`
- `body`

После отправки комментарий появляется на странице поста.

**Скриншот:** `20-comment-created.png`

---

## 15. Laravel Breeze

Для готовой системы регистрации и входа был установлен Laravel Breeze.

Breeze добавил:

- `/register`
- `/login`
- `/dashboard`
- logout
- Blade-шаблоны авторизации
- CSRF-защиту форм

Страница регистрации:

**Скриншот:** `21-register.png`

Страница входа:

**Скриншот:** `22-login.png`

После регистрации пользователь автоматически входит в систему.

**Скриншот:** `23-after-register.png`

---

## 16. GitHub OAuth App

На GitHub было создано OAuth-приложение:

```text
Boardy Laravel
```

Параметры приложения:

```text
Homepage URL:
https://belyaevubuntu.ru

Authorization callback URL:
https://belyaevubuntu.ru/auth/github/callback
```

На скриншоте показан `Client ID`, но `Client Secret` не показывается, потому что это секретное значение.

**Скриншот:** `24-github-app.png`

---

## 17. Laravel Socialite

Для входа через GitHub был установлен пакет:

```bash
composer require laravel/socialite
```

В таблицу `users` был добавлен столбец:

```text
github_id
```

Он нужен, чтобы связывать локального пользователя Laravel с аккаунтом GitHub.

В `.env` были добавлены переменные:

```env
GITHUB_CLIENT_ID=...
GITHUB_CLIENT_SECRET=...
GITHUB_REDIRECT_URI=https://belyaevubuntu.ru/auth/github/callback
```

Реальный `Client Secret` в отчёте не указывается.

В `config/services.php` был добавлен блок:

```php
'github' => [
    'client_id' => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect' => env('GITHUB_REDIRECT_URI'),
],
```

---

## 18. GitHubController

Для OAuth был создан контроллер:

```text
app/Http/Controllers/Auth/GitHubController.php
```

Он выполняет два действия:

1. `redirect()` — перенаправляет пользователя на GitHub;
2. `callback()` — получает пользователя от GitHub, создаёт или находит локального пользователя и выполняет вход через `Auth::login()`.

Логика callback:

- получить данные GitHub-пользователя;
- взять `github_id`;
- найти пользователя по `github_id`;
- если не найден — попробовать найти по email;
- если пользователя нет — создать нового;
- авторизовать пользователя в Laravel.

---

## 19. Кнопка входа через GitHub

На страницу `/login` была добавлена кнопка:

```text
Войти через GitHub
```

**Скриншот:** `25-login-with-github.png`

После нажатия пользователь перенаправляется на GitHub.

---

## 20. OAuth flow

Полный OAuth flow работает так:

1. пользователь нажимает `Войти через GitHub`;
2. Laravel отправляет пользователя на GitHub;
3. GitHub показывает страницу подтверждения доступа;
4. пользователь нажимает `Authorize`;
5. GitHub возвращает пользователя на `/auth/github/callback`;
6. Laravel получает данные пользователя;
7. пользователь создаётся или находится в базе;
8. Laravel создаёт сессию;
9. пользователь оказывается залогинен на сайте.

Страница авторизации GitHub:

**Скриншот:** `26-github-authorize.png`

Пользователь после входа через GitHub:

**Скриншот:** `27-after-github-login.png`

Проверка `github_id` в базе:

```bash
mysql -u boardy -p boardy_main -e "SELECT id, name, email, github_id FROM users WHERE github_id IS NOT NULL;"
```

**Скриншот:** `28-mysql-github-id.png`

---

## 21. Почему legacy-проект не удалялся

Старый проект был сохранён по нескольким причинам:

1. Возможность отката, если Laravel-версия сломается.
2. Возможность сравнить старую и новую архитектуру.
3. Старый проект содержит решения из предыдущих практик.
4. В нём остались FastAPI, React и логика JWT из прошлых работ.
5. Удаление старого проекта усложнило бы восстановление при ошибках.

Поэтому правильный подход — не удалять `/var/www/boardy`, а переименовать его в `/var/www/boardy-legacy`.

---

## 22. Почему старая база `boardy` не удалялась

Старая база данных относится к legacy-проекту.

Она не удалялась, потому что:

- содержит данные прошлых практик;
- может понадобиться для сравнения;
- может использоваться старым PHP/FastAPI проектом;
- её удаление необратимо уничтожило бы старые данные.

Для Laravel была создана новая база:

```text
boardy_main
```

Такой подход безопаснее, потому что старый и новый проекты не конфликтуют между собой.

---

## 23. Почему FastAPI и React пока не используются

В предыдущих практиках React и FastAPI использовались для комментариев и API.

В Lab12 основной фокус изменился:

- Laravel становится главным приложением;
- Blade используется вместо React;
- Eloquent и контроллеры Laravel заменяют часть API-логики;
- Breeze и Socialite закрывают авторизацию.

FastAPI и React пока остаются в legacy-части. Их можно вернуть позже, если потребуется API или SPA-frontend.

---

## 24. Что нужно для realtime-комментариев

Чтобы добавить realtime-комментарии в Laravel, потребуется:

1. событие Laravel, например `CommentCreated`;
2. broadcasting;
3. WebSocket-сервер, например Laravel Reverb;
4. Laravel Echo на frontend;
5. приватные или публичные каналы;
6. отправка события после создания комментария;
7. JavaScript-подписка на канал поста.

Примерная схема:

```text
User creates comment
→ Laravel saves comment
→ Event CommentCreated
→ Broadcast via WebSocket
→ Browser receives event
→ Comment appears without page reload
```

Это логичное развитие текущей архитектуры, если нужно сделать комментарии без обновления страницы.

---

## 25. Сравнение старого PHP-подхода и Laravel

| Критерий | Старый PHP-проект | Laravel |
|---|---|---|
| Роутинг | Ручные PHP-файлы | `routes/web.php` |
| Работа с БД | SQL/PDO вручную | Eloquent ORM |
| Миграции | Вручную через SQL | Laravel migrations |
| Авторизация | Самописные сессии | Breeze/Auth |
| OAuth | Самописный GitHub flow | Socialite |
| Проверка прав | Ручные условия | Policies |
| Шаблоны | PHP/HTML | Blade |
| Структура | Менее строгая | MVC |
| Поддержка | Сложнее | Проще |

Laravel уменьшает количество ручного кода и снижает риск ошибок в типовых задачах.

---

## 26. Проблемы, которые возникали в ходе работы

### Ошибка SQLite

Laravel пытался использовать SQLite, но драйвер не был установлен. Решение — переключить `.env` на MySQL и использовать базу `boardy_main`.

### Ошибка прав на cache/storage

Laravel не мог очистить кэш из-за прав доступа. Решение:

```bash
sudo chown -R student:www-data /var/www/boardy
sudo chmod -R 775 /var/www/boardy/storage /var/www/boardy/bootstrap/cache
```

### Дубликаты миграций

При создании файлов через `nano` были случайно созданы файлы с `XXXXXXXX` в имени. Laravel пытался повторно создать уже существующие таблицы. Лишние миграции были удалены.

### Ошибка Vite manifest

Breeze использует Vite. Если `public/build/manifest.json` отсутствует, нужно выполнить:

```bash
npm install
npm run build
```

### Ошибка Socialite config = null

Laravel не видел настройки GitHub, потому что нужно добавить блок `github` в `config/services.php` и переменные в `.env`.

---

## 27. Вывод

В ходе практики проект Boardy был перенесён на Laravel.

В результате было реализовано:

- установка Laravel 11;
- настройка Nginx на `/public`;
- отдельная база данных `boardy_main`;
- миграции для `posts` и `comments`;
- модели и связи Eloquent;
- сидер и фабрики;
- CRUD постов;
- комментарии через Blade;
- авторизация через Breeze;
- GitHub OAuth через Socialite;
- политики доступа к постам.

Главный результат практики — проект получил более правильную архитектуру на базе Laravel MVC. Большая часть самописной логики из предыдущих практик была заменена стандартными инструментами Laravel: маршруты, контроллеры, модели, миграции, Blade, Breeze, Policies и Socialite.

---

## 28. Список скриншотов

1. `01-composer-php.png`
2. `02-folders.png`
3. `03-laravel-version.png`
4. `04-nginx-config.png`
5. `05-laravel-welcome.png`
6. `06-databases.png`
7. `07-tinker-pdo.png`
8. `08-migrate-status.png`
9. `09-show-tables.png`
10. `10-model-relations.png`
11. `11-seed-counts.png`
12. `12-route-list.png`
13. `13-posts-index.png`
14. `14-post-show.png`
15. `15-post-create.png`
16. `16-post-after-create.png`
17. `17-edit-own.png`
18. `18-edit-foreign-403.png`
19. `19-post-deleted.png`
20. `20-comment-created.png`
21. `21-register.png`
22. `22-login.png`
23. `23-after-register.png`
24. `24-github-app.png`
25. `25-login-with-github.png`
26. `26-github-authorize.png`
27. `27-after-github-login.png`
28. `28-mysql-github-id.png`