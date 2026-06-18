# Telegram Bot (Slim 4 + Eloquent ORM + PostgreSQL)

Универсальный каркас для создания масштабируемого Telegram-бота на PHP 8.3 с использованием Docker-окружения, микрофреймворка Slim 4 и ORM Eloquent.

## 🛠 Технологический стек
* **Язык:** PHP 8.3 (Alpine)
* **Фреймворк:** Slim 4 (PSR-7)
* **База данных:** PostgreSQL 16
* **ORM:** Illuminate Database (Eloquent 10)
* **Окружение:** Docker / Docker Compose
* **API Telegram:** Longman Telegram Bot API

---

## 🚀 Быстрый старт

### 1. Подготовка окружения
Склонируйте репозиторий и создайте файл конфигурации:
```bash
cp .env.example .env
```
Откройте файл `.env` и укажите ваш токен бота от `@BotFather`, а также параметры подключения к БД.

### 2. Запуск контейнеров
Соберите и запустите Docker-контейнеры в фоновом режиме:
```bash
docker compose up -d --build
```

### 3. Установка зависимостей
Установите все PHP-пакеты через Composer внутри контейнера:
```bash
docker compose exec php composer install
```

### 4. Запуск миграций
Создайте необходимую структуру таблиц в PostgreSQL:
```bash
docker compose exec php php migrate.php
```

### 5. Запуск локального сервера
Для локальной разработки и приема вебхуков запустите встроенный сервер PHP:
```bash
docker compose exec -d php php -S 0.0.0.0:8080 -t public
```

---

## 📂 Структура проекта
* `config/` — инициализация базы данных и общие настройки.
* `database/migrations/` — файлы миграций для контроля версий БД.
* `public/index.php` — точка входа в приложение, регистрация маршрутов Slim.
* `src/Controllers/` — HTTP-контроллеры для обработки вебхуков от Telegram.
* `src/Models/` — ORM-модели Eloquent для работы с таблицами базы данных.
* `migrate.php` — кастомный универсальный раннер миграций.
* `compose.yaml` — конфигурация Docker-сервисов (php, db).

---

## 🤖 Настройка Webhook (Локально)
1. Запустите туннель через **ngrok**: `ngrok http 8080`
2. Скопируйте полученный `https://...` адрес.
3. Установите вебхук, открыв в браузере ссылку:
   `https://telegram.org<ВАШ_ТОКЕН>/setWebhook?url=https://<ВАШ_АДРЕС_NGROK>/webhook/telegram`
