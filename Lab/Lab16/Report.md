# Практика 16 — CI/CD для контейнеризированного проекта Boardy

## Цель работы

Цель практики — настроить CI/CD для контейнеризированного проекта Boardy с использованием GitHub Actions, Docker Compose и GitHub Container Registry.

В рамках работы было необходимо:

* добавить health endpoints для Laravel и FastAPI;
* создать автоматические тесты для Laravel и FastAPI;
* настроить локальную проверку контейнеров через Docker Compose;
* создать workflow `ci.yml` для проверки проекта;
* создать workflow `deploy.yml` для сборки образов и деплоя;
* подготовить `docker-compose.yml` к использованию образов из GHCR;
* создать `docker-compose.local.yml` для локальной сборки;
* проверить, что CI блокирует Pull Request при ошибке в тестах;
* настроить защиту ветки `main`;
* настроить GitHub Secrets для деплоя;
* подготовить проект к переносу и выгрузке в GitHub.

---

## Исходные данные проекта

Проект разворачивается на виртуальной машине и является продолжением предыдущей практики по Docker Compose.

Рабочая директория практики:

```text
/home/student/lab16-work
```

За основу была взята готовая директория Lab15:

```text
/home/student/lab15-work
```

Для новой практики была создана отдельная копия:

```bash
cd /home/student
cp -a lab15-work lab16-work
```

Это было сделано, чтобы не изменять готовую практическую работу №15 и выполнять новую работу отдельно.

Основные части проекта:

* Laravel-приложение: `src/boardy-laravel`
* FastAPI-сервис: `src/boardy-api`
* Nginx reverse proxy: `docker/nginx`
* Docker Compose: `docker-compose.yml`
* локальная сборка: `docker-compose.local.yml`
* GitHub Actions workflows: `.github/workflows`
* отчёт и скриншоты: `Lab/Lab16`

---

## 1. Создание структуры практики

В директории `lab16-work` были созданы папки для GitHub Actions, отчёта, скриншотов и тестов:

```bash
cd /home/student/lab16-work

mkdir -p .github/workflows
mkdir -p Lab/Lab16/screenshots
mkdir -p src/boardy-laravel/tests/Feature
mkdir -p src/boardy-api/tests
```

После этого была проверена структура проекта.

**Скриншот:** `01-project-structure.png`

---

## 2. Добавление health endpoint в Laravel

Для проверки доступности Laravel-приложения был добавлен маршрут `/health`.

Файл:

```text
src/boardy-laravel/routes/web.php
```

В конец файла был добавлен код:

```php
Route::get('/health', function () {
    return response()->json(['ok' => true]);
});
```

Этот маршрут возвращает простой JSON-ответ:

```json
{"ok":true}
```

Endpoint нужен для быстрой проверки состояния Laravel в Docker Compose и в GitHub Actions.

Проверка кода выполнялась командой:

```bash
grep -n -B5 -A5 "Route::get('/health'" src/boardy-laravel/routes/web.php
```

**Скриншот:** `02-laravel-health-code.png`

---

## 3. Добавление health endpoint в FastAPI

Для FastAPI-сервиса также был добавлен health endpoint.

Файл:

```text
src/boardy-api/main.py
```

Был добавлен код:

```python
@app.get("/health")
def health():
    return {"ok": True}


@app.get("/api/health")
def api_health():
    return {"ok": True}
```

Endpoint `/health` используется внутри FastAPI, а `/api/health` нужен для проверки через Nginx, потому что FastAPI в проекте доступен через префикс `/api`.

Проверка кода выполнялась командой:

```bash
grep -n -B5 -A5 '@app.get("/health")' src/boardy-api/main.py
```

**Скриншот:** `03-fastapi-health-code.png`

---

## 4. Создание PHPUnit-теста для Laravel

Для Laravel был создан feature-тест, который проверяет маршрут `/health`.

Файл:

```text
src/boardy-laravel/tests/Feature/HealthTest.php
```

Код теста:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
        ]);
    }
}
```

Тест проверяет:

* что маршрут `/health` открывается;
* что сервер возвращает статус `200`;
* что JSON содержит `ok: true`.

Проверка файла:

```bash
nl -ba src/boardy-laravel/tests/Feature/HealthTest.php
```

**Скриншот:** `04-phpunit-test-code.png`

---

## 5. Создание pytest-теста для FastAPI

Для FastAPI был создан тест health endpoint.

Файл:

```text
src/boardy-api/tests/test_health.py
```

Код теста:

```python
from pathlib import Path
import importlib.util

from fastapi.testclient import TestClient


def load_app():
    main_path = Path(__file__).resolve().parents[1] / "main.py"

    spec = importlib.util.spec_from_file_location("boardy_main", main_path)
    module = importlib.util.module_from_spec(spec)

    assert spec is not None
    assert spec.loader is not None

    spec.loader.exec_module(module)

    return module.app


app = load_app()
client = TestClient(app)


def test_health_endpoint_returns_ok():
    response = client.get("/api/health")

    if response.status_code == 404:
        response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {"ok": True}
```

Тест проверяет, что FastAPI возвращает статус `200` и JSON:

```json
{"ok":true}
```

Проверка файла:

```bash
nl -ba src/boardy-api/tests/test_health.py
```

**Скриншот:** `05-pytest-test-code.png`

---

## 6. Добавление зависимостей для pytest

В файл зависимостей FastAPI были добавлены пакеты для тестирования:

```text
src/boardy-api/requirements.txt
```

Добавленные зависимости:

```text
pytest==8.3.4
httpx==0.27.2
```

Проверка выполнялась командой:

```bash
grep -n "pytest\|httpx" src/boardy-api/requirements.txt
```

**Скриншот:** `06-test-dependencies.png`

---

## 7. Создание docker-compose.local.yml

Для локальной сборки контейнеров был создан файл:

```text
docker-compose.local.yml
```

Содержимое файла:

```yaml
services:
  laravel:
    build:
      context: ./src/boardy-laravel
      dockerfile: Dockerfile
    image: boardy-laravel:local

  fastapi:
    build:
      context: ./src/boardy-api
      dockerfile: Dockerfile
    image: boardy-api:local
```

Этот файл используется вместе с основным `docker-compose.yml`.

Основной `docker-compose.yml` нужен для запуска готовых образов из GHCR, а `docker-compose.local.yml` нужен для локальной сборки образов из исходного кода.

Проверка файла:

```bash
cat docker-compose.local.yml
```

**Скриншот:** `07-compose-local.png`

---

## 8. Подготовка env example файлов

Был подготовлен корневой файл:

```text
.env.example
```

Пример содержимого:

```env
GHCR_IMAGE_PREFIX=ghcr.io/zaayn-lox/ubuntu-lab

MYSQL_ROOT_PASSWORD=change_me_root
MYSQL_DATABASE=boardy_laravel
MYSQL_USER=boardy
MYSQL_PASSWORD=boardy_password

DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=boardy_laravel
DB_USERNAME=boardy
DB_PASSWORD=boardy_password

REDIS_HOST=redis
REDIS_PORT=6379
```

Также был подготовлен Laravel env example:

```text
src/boardy-laravel/.env.example
```

В `.env.example` не добавлялись реальные пароли, приватные ключи и другие секретные значения.

Проверка выполнялась командой:

```bash
cat .env.example
cat src/boardy-laravel/.env.example
```

**Скриншот:** `08-env-examples.png`

---

## 9. Подготовка docker-compose.yml под GHCR

Основной `docker-compose.yml` был изменён так, чтобы Laravel и FastAPI использовали Docker-образы из GitHub Container Registry.

Для Laravel был указан образ:

```yaml
image: ${GHCR_IMAGE_PREFIX:-ghcr.io/zaayn-lox/ubuntu-lab}-laravel:latest
```

Для FastAPI был указан образ:

```yaml
image: ${GHCR_IMAGE_PREFIX:-ghcr.io/zaayn-lox/ubuntu-lab}-api:latest
```

Сборка через `build:` была вынесена в `docker-compose.local.yml`.

Проверка выполнялась командой:

```bash
grep -n -A12 -B2 "laravel:\|fastapi:" docker-compose.yml
cat docker-compose.local.yml
```

**Скриншот:** `09-compose-ghcr-images.png`

---

## 10. Создание ci.yml

Был создан workflow:

```text
.github/workflows/ci.yml
```

Он запускается при push и Pull Request.

Pipeline состоит из трёх этапов:

```text
lint → smoke → test
```

### lint

На этапе `lint` проверяется синтаксис PHP- и Python-файлов.

PHP:

```bash
find src/boardy-laravel -name "*.php" -print0 | xargs -0 -n1 php -l
```

Python:

```bash
python -m compileall src/boardy-api -q
```

### smoke

На этапе `smoke` выполняется:

* подготовка env-файлов;
* сборка Docker Compose проекта;
* запуск контейнеров;
* проверка `/health`;
* проверка `/api/health`.

Проверка:

```bash
curl -f http://localhost/health
curl -f http://localhost/api/health
```

### test

На этапе `test` выполняется:

* установка зависимостей Laravel;
* запуск PHPUnit;
* установка зависимостей FastAPI;
* запуск pytest.

Проверка файла:

```bash
cat .github/workflows/ci.yml
```

**Скриншот:** `10-ci-yml.png`

---

## 11. Создание deploy.yml

Был создан workflow:

```text
.github/workflows/deploy.yml
```

Он запускается при push в ветку `main`.

Pipeline состоит из этапов:

```text
lint → smoke → test → build → deploy
```

На этапе `build` выполняется:

* вход в GHCR;
* сборка Laravel image;
* сборка FastAPI image;
* публикация образов в GitHub Container Registry.

Для образов используются два тега:

```text
latest
SHA-коммита
```

На этапе `deploy` GitHub Actions подключается к серверу по SSH и выполняет:

```bash
cd /opt/boardy
git pull
docker compose pull
docker compose up -d
docker image prune -f
```

Проверка файла:

```bash
cat .github/workflows/deploy.yml
```

**Скриншот:** `11-deploy-yml.png`

---

## 12. Локальная сборка Docker Compose

Для локальной проверки была выполнена сборка контейнеров:

```bash
cd /home/student/lab16-work

docker compose -f docker-compose.yml -f docker-compose.local.yml build
```

Сборка выполнялась для сервисов Laravel и FastAPI.

После сборки был проверен список образов:

```bash
docker image ls | grep -E "boardy-laravel|boardy-api|lab16-work"
```

**Скриншот:** `12-compose-build.png`

---

## 13. Локальный запуск контейнеров

Перед запуском контейнеров был остановлен системный Nginx, чтобы освободить порт 80:

```bash
sudo systemctl stop nginx || true
```

Затем контейнеры были запущены:

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml up -d
```

Проверка контейнеров:

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml ps
```

В результате были запущены контейнеры:

* `boardy-nginx`
* `boardy-laravel`
* `boardy-api`
* `boardy-mysql`
* `boardy-redis`

**Скриншот:** `13-compose-up.png`

---

## 14. Проверка Laravel route list

Для проверки, что маршрут `/health` зарегистрирован в Laravel, была выполнена команда:

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml exec laravel php artisan route:list | grep health
```

В результате был найден маршрут:

```text
GET|HEAD health
```

Это означает, что Laravel увидел добавленный health route.

**Скриншот:** `14-laravel-route-list.png`

---

## 15. Проверка health endpoints

Проверка health endpoints выполнялась через Nginx:

```bash
curl -i http://localhost/health
curl -i http://localhost/api/health
```

Laravel вернул:

```json
{"ok":true}
```

FastAPI также вернул:

```json
{"ok":true}
```

Это подтверждает, что оба сервиса доступны через Docker Compose и Nginx.

**Скриншот:** `15-health-curl.png`

---

## 16. Проверка PHPUnit

Для запуска Laravel-теста была выполнена команда:

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml exec laravel php artisan test --filter=HealthTest
```

Результат:

```text
PASS  Tests\Feature\HealthTest
Tests: 1 passed
```

Перед успешным запуском теста был создан `.env` внутри Laravel-контейнера и задан `APP_KEY`, так как без него Laravel выдавал предупреждение.

**Скриншот:** `16-phpunit-local.png`

---

## 17. Проверка pytest

Для запуска FastAPI-теста была выполнена команда:

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml exec fastapi pytest -v tests/test_health.py
```

Результат:

```text
tests/test_health.py::test_health_endpoint_returns_ok PASSED
1 passed
```

Тест подтвердил, что FastAPI health endpoint работает корректно.

**Скриншот:** `17-pytest-local.png`

---

## 18. Подготовка архива для переноса через WinSCP

Так как работа выполнялась на виртуальной машине через PuTTY, для переноса файлов был создан архив:

```bash
cd /home/student

tar \
  --exclude='lab16-work/.env' \
  --exclude='lab16-work/src/boardy-laravel/.env' \
  --exclude='lab16-work/src/boardy-laravel/vendor' \
  --exclude='lab16-work/src/boardy-laravel/node_modules' \
  --exclude='lab16-work/src/boardy-laravel/storage/oauth-private.key' \
  --exclude='lab16-work/src/boardy-laravel/storage/oauth-public.key' \
  --exclude='lab16-work/src/boardy-api/venv' \
  --exclude='lab16-work/src/boardy-api/__pycache__' \
  --exclude='*.pyc' \
  -czf lab16-work.tar.gz lab16-work
```

Проверка архива:

```bash
tar -tzf /home/student/lab16-work.tar.gz | grep -E "\.env$|oauth-private.key|oauth-public.key|vendor/|node_modules/|venv/" || echo "OK: secrets excluded"
```

Результат:

```text
OK: secrets excluded
```

Это означает, что секретные и тяжёлые файлы не попали в архив.

**Скриншот:** `18-archive-check.png`

---

## 19. Перенос файлов на Windows и выгрузка в GitHub

После создания архива файл был перенесён через WinSCP:

```text
/home/student/lab16-work.tar.gz
```

После распаковки содержимое `lab16-work` было перенесено в локальный репозиторий на Windows.

Далее была создана отдельная ветка:

```bash
git switch main
git pull origin main
git switch -c lab16
```

Файлы были добавлены в git:

```bash
git add .github
git add docker-compose.yml
git add docker-compose.local.yml
git add .env.example
git add src/boardy-laravel/.env.example
git add src/boardy-laravel/routes/web.php
git add src/boardy-laravel/tests/Feature/HealthTest.php
git add src/boardy-api/main.py
git add src/boardy-api/requirements.txt
git add src/boardy-api/tests/test_health.py
git add Lab/Lab16
```

Коммит:

```bash
git commit -m "lab16: add CI CD pipeline"
git push -u origin lab16
```

---

## 20. Создание Pull Request

На GitHub был создан Pull Request:

```text
base: main
compare: lab16
```

Название Pull Request:

```text
Lab16: CI/CD через GitHub Actions
```

Pull Request нужен для проверки изменений через GitHub Actions перед слиянием в `main`.

**Скриншот:** `19-github-pr.png`

---

## 21. Проверка успешного CI

После push и создания Pull Request автоматически запустился workflow `CI`.

В нём должны пройти этапы:

```text
lint
smoke
test
```

Если все этапы зелёные, это означает, что:

* синтаксис PHP и Python корректный;
* контейнеры собираются и запускаются;
* health endpoints доступны;
* PHPUnit и pytest проходят успешно.

**Скриншот:** `20-actions-ci-green.png`

---

## 22. Проверка job lint

В job `lint` были проверены PHP- и Python-файлы.

Проверка PHP выполнялась через `php -l`.

Проверка Python выполнялась через `python -m compileall`.

Если ошибок синтаксиса нет, job завершается успешно.

**Скриншот:** `21-lint-log.png`

---

## 23. Проверка job smoke

В job `smoke` были выполнены:

* сборка контейнеров;
* запуск Docker Compose;
* проверка Laravel health endpoint;
* проверка FastAPI health endpoint.

Ключевые команды проверки:

```bash
curl -f http://localhost/health
curl -f http://localhost/api/health
```

**Скриншот:** `22-smoke-log.png`

---

## 24. Проверка job test

В job `test` были запущены автоматические тесты:

Laravel:

```bash
php artisan test --filter=HealthTest
```

FastAPI:

```bash
pytest -v tests/test_health.py
```

Успешный результат показывает, что базовые проверки приложения проходят.

**Скриншот:** `23-test-log.png`

---

## 25. Демонстрация сломанного теста

Чтобы проверить, что CI действительно блокирует ошибочный код, FastAPI-тест был временно сломан.

В файле:

```text
src/boardy-api/tests/test_health.py
```

строка:

```python
assert response.status_code == 200
```

была заменена на:

```python
assert response.status_code == 999
```

После push workflow завершился с ошибкой.

**Скриншот:** `24-test-failed.png`

---

## 26. Проверка блокировки последующих этапов

После падения теста следующие этапы pipeline не должны выполняться.

Это подтверждает правильную зависимость jobs:

```text
lint → smoke → test → build → deploy
```

Если `test` падает, сборка и деплой не должны продолжаться.

**Скриншот:** `25-build-skipped.png`

---

## 27. Проверка блокировки Pull Request

После падения теста Pull Request был заблокирован, потому что обязательные проверки не прошли.

Это показывает, что ошибочный код нельзя слить в ветку `main`.

**Скриншот:** `26-pr-blocked.png`

---

## 28. Восстановление теста

После демонстрации ошибки тест был восстановлен.

Строка снова была заменена на:

```python
assert response.status_code == 200
```

После push CI снова прошёл успешно.

**Скриншот:** `27-test-restored-green.png`

---

## 29. Настройка защиты ветки main

Для ветки `main` была настроена защита.

Основные настройки:

```text
Require a pull request before merging
Require status checks to pass before merging
Required checks: lint, smoke, test
```

Это запрещает слияние Pull Request, если CI завершился с ошибкой.

**Скриншот:** `28-branch-protection.png`

---

## 30. Настройка GitHub Secrets

Для автоматического деплоя были добавлены GitHub Secrets:

```text
SERVER_HOST
SERVER_USER
SSH_PRIVATE_KEY
```

Назначение:

* `SERVER_HOST` — адрес сервера;
* `SERVER_USER` — пользователь для SSH;
* `SSH_PRIVATE_KEY` — приватный SSH-ключ для GitHub Actions.

Секреты не хранятся в репозитории и не отображаются в открытом виде.

**Скриншот:** `29-secrets.png`

---

## 31. Проверка GitHub Container Registry

После слияния изменений в `main` workflow `Deploy` должен собрать и отправить Docker-образы в GitHub Container Registry.

Ожидаемые образы:

```text
ubuntu-lab-laravel
ubuntu-lab-api
```

Для каждого образа используются теги:

```text
latest
SHA-коммита
```

**Скриншот:** `30-ghcr-packages.png`

---

## 32. Проверка Deploy workflow

После merge в `main` должен запуститься workflow `Deploy`.

Он выполняет этапы:

```text
lint
smoke
test
build
deploy
```

Если все этапы зелёные, значит pipeline успешно проверил проект, собрал образы и выполнил деплой.

**Скриншот:** `31-deploy-green.png`

---

## 33. Проверка логов деплоя

В логах deploy job должны быть видны команды:

```bash
git pull
docker compose pull
docker compose up -d
docker image prune -f
```

Эти команды подтверждают, что GitHub Actions подключился к серверу и обновил контейнеры.

**Скриншот:** `32-deploy-log.png`

---

## 34. Проверка контейнеров на сервере

После деплоя на сервере была выполнена команда:

```bash
docker compose ps
```

Контейнеры должны находиться в состоянии `Up` или `Healthy`.

**Скриншот:** `33-server-containers.png`

---

## 35. Проверка production health

После деплоя были проверены health endpoints на сервере:

```bash
curl -i http://localhost/health
curl -i http://localhost/api/health
```

Оба endpoint должны вернуть:

```json
{"ok":true}
```

**Скриншот:** `34-prod-health.png`

---

## 36. Финальная проверка файлов на GitHub

В Pull Request были проверены добавленные файлы:

```text
.github/workflows/ci.yml
.github/workflows/deploy.yml
docker-compose.yml
docker-compose.local.yml
.env.example
src/boardy-laravel/.env.example
src/boardy-laravel/routes/web.php
src/boardy-laravel/tests/Feature/HealthTest.php
src/boardy-api/main.py
src/boardy-api/requirements.txt
src/boardy-api/tests/test_health.py
Lab/Lab16/Report.md
Lab/Lab16/screenshots/
```

**Скриншот:** `35-final-github-files.png`

---

## 37. Почему нужен health endpoint

Health endpoint нужен для быстрой проверки, что сервис запущен и отвечает.

В этой практике используются два health endpoint:

```text
/health
/api/health
```

Они позволяют:

* быстро проверить Laravel;
* быстро проверить FastAPI;
* использовать проверку в GitHub Actions;
* использовать проверку после деплоя;
* быстро понять, какой сервис не работает.

---

## 38. Почему lint выполняется первым

Этап `lint` выполняется первым, потому что он самый быстрый.

Он проверяет синтаксис файлов и позволяет сразу остановить pipeline, если в коде есть грубая ошибка.

Это экономит время, потому что нет смысла собирать Docker-образы и запускать тесты, если код содержит синтаксическую ошибку.

---

## 39. Почему smoke выполняется перед test

Этап `smoke` проверяет, что проект вообще может собраться и запуститься.

Он проверяет:

* Docker build;
* Docker Compose up;
* Laravel `/health`;
* FastAPI `/api/health`.

Если приложение не запускается, то запускать более подробные тесты уже бессмысленно.

---

## 40. Почему test блокирует build и deploy

Этап `test` проверяет работоспособность ключевых частей приложения.

Если тесты падают, значит код нельзя отправлять в production.

Поэтому build и deploy должны зависеть от успешного прохождения тестов.

Это защищает сервер от выкатывания ошибочного кода.

---

## 41. Почему используется GHCR

GitHub Container Registry используется для хранения готовых Docker-образов.

Это удобно, потому что серверу не нужно собирать проект из исходников.

Сервер просто выполняет:

```bash
docker compose pull
docker compose up -d
```

Такой подход быстрее и безопаснее, чем сборка на сервере.

---

## 42. Почему используются теги latest и SHA

Для Docker-образов используются два вида тегов:

```text
latest
SHA-коммита
```

`latest` удобен для обычного обновления.

SHA-тег нужен для точного понимания, какая версия кода была собрана, и для возможного отката на конкретный коммит.

---

## 43. Почему нужен отдельный SSH-ключ для deploy

Для деплоя используется отдельный SSH-ключ.

Это безопаснее, потому что:

* ключ используется только для GitHub Actions;
* его можно быстро удалить или заменить;
* не нужно использовать личный основной SSH-ключ;
* доступ к серверу контролируется через GitHub Secrets.

---

## 44. Проблемы, которые возникали в ходе работы

### Конфликт имён контейнеров

При запуске Lab16 возникла ошибка:

```text
container name "/boardy-mysql" is already in use
```

Причина: контейнеры от предыдущей практики ещё были запущены.

Решение:

```bash
cd /home/student/lab15-work
docker compose down
```

После этого контейнеры Lab16 были запущены отдельно.

---

### FastAPI /api/health возвращал 404

Сначала был добавлен только endpoint `/health`, но через Nginx проверялся путь `/api/health`.

Решение — добавить отдельный endpoint:

```python
@app.get("/api/health")
def api_health():
    return {"ok": True}
```

После пересборки FastAPI endpoint стал доступен.

---

### Laravel test не запускался

При запуске команды:

```bash
php artisan test
```

появилась ошибка:

```text
Command "test" is not defined
```

Причина: внутри контейнера Laravel не были установлены dev-зависимости.

Решение:

```bash
composer install --no-interaction --prefer-dist
```

После этого команда `php artisan test` стала доступна.

---

### Laravel ругался на отсутствие APP_KEY

При запуске теста Laravel не видел `.env` и `APP_KEY`.

Решение — создать `.env` внутри контейнера и указать `APP_KEY`.

После очистки кэша тест прошёл успешно.

---

### pytest не находил main.py

При первом запуске pytest появилась ошибка:

```text
ModuleNotFoundError: No module named 'main'
```

Решение — изменить тест так, чтобы он явно загружал `main.py` через `importlib`.

---

### pytest не находил routers

После исправления импорта появилась ошибка:

```text
ModuleNotFoundError: No module named 'routers'
```

Причина: FastAPI Dockerfile не копировал все файлы проекта.

Решение — изменить Dockerfile FastAPI так, чтобы он копировал весь проект:

```dockerfile
COPY . .
```

Также был добавлен файл:

```text
src/boardy-api/routers/__init__.py
```

После пересборки тест прошёл успешно.

---

## 45. Безопасность

В репозиторий не добавлялись секретные файлы:

```text
.env
oauth-private.key
oauth-public.key
```

Также не добавлялись тяжёлые и генерируемые директории:

```text
vendor/
node_modules/
venv/
__pycache__/
```

Для передачи файлов с виртуальной машины был создан архив с исключениями, чтобы секреты не попали в репозиторий.

Для деплоя использовались GitHub Secrets:

```text
SERVER_HOST
SERVER_USER
SSH_PRIVATE_KEY
```

---

## 46. Вывод

В ходе практики для проекта Boardy был настроен CI/CD-процесс на базе GitHub Actions.

В результате было реализовано:

* health endpoint для Laravel;
* health endpoint для FastAPI;
* PHPUnit-тест для Laravel;
* pytest-тест для FastAPI;
* локальная сборка через Docker Compose;
* workflow `ci.yml`;
* workflow `deploy.yml`;
* подготовка образов для GHCR;
* автоматическая проверка Pull Request;
* демонстрация блокировки PR при ошибке в тестах;
* защита ветки `main`;
* подготовка автоматического деплоя через SSH.

Главный результат практики — проект получил автоматизированную систему проверки и доставки. Теперь изменения проходят через pipeline, где сначала проверяется синтаксис, затем запуск контейнеров, затем тесты, после чего возможна сборка Docker-образов и деплой на сервер.

---

## 47. Список скриншотов

1. `01-project-structure.png`
2. `02-laravel-health-code.png`
3. `03-fastapi-health-code.png`
4. `04-phpunit-test-code.png`
5. `05-pytest-test-code.png`
6. `06-test-dependencies.png`
7. `07-compose-local.png`
8. `08-env-examples.png`
9. `09-compose-ghcr-images.png`
10. `10-ci-yml.png`
11. `11-deploy-yml.png`
12. `12-compose-build.png`
13. `13-compose-up.png`
14. `14-laravel-route-list.png`
15. `15-health-curl.png`
16. `16-phpunit-local.png`
17. `17-pytest-local.png`
18. `18-archive-check.png`
19. `19-github-pr.png`
20. `20-actions-ci-green.png`
21. `21-lint-log.png`
22. `22-smoke-log.png`
23. `23-test-log.png`
24. `24-test-failed.png`
25. `25-build-skipped.png`
26. `26-pr-blocked.png`
27. `27-test-restored-green.png`
28. `28-branch-protection.png`
29. `29-secrets.png`
30. `30-ghcr-packages.png`
31. `31-deploy-green.png`
32. `32-deploy-log.png`
33. `33-server-containers.png`
34. `34-prod-health.png`
35. `35-final-github-files.png`
