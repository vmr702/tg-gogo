<?php

namespace App\Services;

use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request as TelegramRequest;
use Longman\TelegramBot\Entities\InlineKeyboard;
use App\Models\User;
use App\Models\Event;
use Carbon\Carbon;

class BotService
{
    private Telegram $telegram;

    public function __construct()
    {
        $this->telegram = new Telegram(getenv('TG_TOKEN'));
        TelegramRequest::initialize($this->telegram);
    }

    public function handleStartCommand(int $chatId): void
    {
        $inline_keyboard = new InlineKeyboard([
            [
                'text' => 'Москва', 
                'callback_data' => 'set_city:Москва'
            ],
            [
                'text' => 'Санкт-Петербург', 
                'callback_data' => 'set_city:СПб'
            ],
        ]);

        TelegramRequest::sendMessage([
            'chat_id'      => $chatId,
            'text'         => "Афиша. Выберите ваш город:",
            'reply_markup' => $inline_keyboard,
        ]);
    }

    public function handleSetCityCallback(string $callbackQueryId, int $chatId, string $city, array $from): void
    {
        User::updateOrCreate(
            ['telegram_id' => $from['id']],
            ['username' => $from['username'] ?? null, 'city' => $city, 'step' => 'idle']
        );

        TelegramRequest::answerCallbackQuery([
            'callback_query_id' => $callbackQueryId,
            'text'              => "Город сохранен!",
        ]);

        $this->sendEventsList($chatId, $city, 0);
    }

        public function sendEventsList(int $chatId, string $city, int $offset): void
    {
        // Вытаскиваем мероприятия из СУБД с нужным смещением
        $events = Event::where('city', $city)->orderBy('id', 'asc')->skip($offset)->take(10)->get();

        if ($events->isEmpty()) {
            $this->sendSimpleMessage($chatId, "🎭 Больше мероприятий в городе $city пока нет.");
            return;
        }

        // Формируем текст
        $textOutput = "📅 **Мероприятия в городе $city (Показано " . ($offset + 1) . "-" . ($offset + $events->count()) . "):**\n\n";
        
        $number = 1;
        foreach ($events as $event) {
            // Если поле price пустое или равен 0 — пишем Бесплатно, иначе цену
            $priceLabel = ($event->is_free || !$event->price) ? "💸 Бесплатно" : "{$event->price}₽";
            $textOutput .= "$number **{$event->title}** ($priceLabel)\n";
            $number++;
        }
        $textOutput .= "\n👉 *Введите цифру (1-10), чтобы открыть описание подробнее.*";

        // ПРАВИЛЬНАЯ ДВУМЕРНАЯ СТРУКТУРА МАССИВА КНОПОК ДЛЯ LONGMAN TG BOT API
        $nextOffset = $offset + 10;
        
        $keyboard = new InlineKeyboard([
            ['text' => '🔄 Показать еще', 'callback_data' => "more_events:$nextOffset"],
            ['text' => '➕ Создать мероприятие', 'callback_data' => 'add_event']
        ]);

        TelegramRequest::sendMessage([
            'chat_id'      => $chatId,
            'text'         => $textOutput,
            'parse_mode'   => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    public function handleMoreEventsCallback(string $callbackQueryId, int $chatId, int $offset, int $telegramId): void
    {
        $user = User::where('telegram_id', $telegramId)->first();
        if ($user && $user->city) {
            TelegramRequest::answerCallbackQuery(['callback_query_id' => $callbackQueryId]);
            $this->sendEventsList($chatId, $user->city, $offset);
        }
    }

    public function handleEventDetailCommand(int $chatId, int $inputNumber, int $telegramId): void
    {
        $user = User::where('telegram_id', $telegramId)->first();
        if (!$user || !$user->city) {
            $this->sendSimpleMessage($chatId, "🛑 Сначала выберите город с помощью команды /start");
            return;
        }

        $index = $inputNumber - 1;
        $events = Event::where('city', $user->city)->orderBy('id', 'asc')->take(10)->get();

        if (isset($events[$index])) {
            $event = $events[$index];
            $event->increment('views_count');

            $priceText = $event->is_free ? "🆓 Бесплатно" : "💰 Цена: {$event->price} руб.";
            $dateText = $event->event_date ? $event->event_date->format('d.m.Y в H:i') : "Не указана";

            $messageText = "📌 **{$event->title}**\n\n"
                         . "📅 **Когда:** $dateText\n"
                         . "$priceText\n"
                         . "👀 Просмотров: {$event->views_count}\n\n"
                         . "📝 **Описание:**\n{$event->description}";
        } else {
            $messageText = "❌ Мероприятия под номером $inputNumber не найдено.";
        }

        $this->sendMarkdownMessage($chatId, $messageText);
    }

    // ==========================================
    // ЛОГИКА СЦЕНАРИЯ СОЗДАНИЯ (FSM)
    // ==========================================

    public function handleStartCreateEventCallback(string $callbackQueryId, int $chatId, int $telegramId): void
    {
        $user = User::where('telegram_id', $telegramId)->first();
        
        if (!$user || !$user->city) {
            TelegramRequest::answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text'              => "Сначала выберите город!",
            ]);
            return;
        }

        TelegramRequest::answerCallbackQuery(['callback_query_id' => $callbackQueryId]);

        // Переводим юзера на первый шаг и очищаем старый черновик
        $user->update([
            'step' => 'awaiting_title',
            'draft_event' => []
        ]);

        $this->sendSimpleMessage($chatId, "📝 Шаг 1/4: Введите НАЗВАНИЕ вашего мероприятия:");
    }

    public function handleFsmStep(int $chatId, string $text, User $user): void
    {
        $draft = $user->draft_event ?? [];

        switch ($user->step) {
            case 'awaiting_title':
                $draft['title'] = $text;
                $user->update([
                    'step' => 'awaiting_description',
                    'draft_event' => $draft
                ]);
                $this->sendSimpleMessage($chatId, "📝 Шаг 2/4: Введите ПОДРОБНОЕ ОПИСАНИЕ мероприятия:");
                break;

            case 'awaiting_description':
                $draft['description'] = $text;
                $user->update([
                    'step' => 'awaiting_date',
                    'draft_event' => $draft
                ]);
                $this->sendSimpleMessage($chatId, "📅 Шаг 3/4: Введите ДАТУ и ВРЕМЯ (в формате ГГГГ-ММ-ДД ЧЧ:ММ, например: 2026-07-25 18:00):");
                break;

            case 'awaiting_date':
                try {
                    // Проверяем валидность даты с помощью библиотеки Carbon
                    $date = Carbon::createFromFormat('Y-m-d H:i', $text);
                    $draft['event_date'] = $date->toDateTimeString();
                    
                    $user->update([
                        'step' => 'awaiting_price',
                        'draft_event' => $draft
                    ]);
                    $this->sendSimpleMessage($chatId, "💰 Шаг 4/4: Введите СТОИМОСТЬ в рублях (цифрой, например: 500). Если вход свободный, введите цифру 0:");
                } catch (\Exception $e) {
                    $this->sendSimpleMessage($chatId, "❌ Неверный формат даты! Пожалуйста, напишите дату строго по шаблону: ГГГГ-ММ-ДД ЧЧ:ММ");
                }
                break;

            case 'awaiting_price':
                if (!is_numeric($text)) {
                    $this->sendSimpleMessage($chatId, "❌ Стоимость должна быть числом! Введите 0 или цену:");
                    return;
                }

                $price = (int)$text;
                $isFree = ($price === 0);

                // ВСЕ ДАННЫЕ СОБРАНЫ -> СОХРАНЯЕМ МЕРОПРИЯТИЕ В ПОСТГРЕС
                Event::create([
                    'user_id'     => $user->id,
                    'city'        => $user->city,
                    'title'       => $draft['title'],
                    'description' => $draft['description'],
                    'event_date'  => $draft['event_date'],
                    'is_free'     => $isFree,
                    'price'       => $isFree ? null : $price,
                ]);

                // Сбрасываем стейт пользователя обратно в режим idle
                $user->update([
                    'step' => 'idle',
                    'draft_event' => null
                ]);

                $this->sendSimpleMessage($chatId, "🎉 Ура! Мероприятие успешно создано и добавлено в афишу города {$user->city}.");
                
                // Сразу показываем обновленный список
                $this->sendEventsList($chatId, $user->city, 0);
                break;
        }
    }

    private function sendSimpleMessage(int $chatId, string $text): void
    {
        TelegramRequest::sendMessage(['chat_id' => $chatId, 'text' => $text]);
    }

    private function sendMarkdownMessage(int $chatId, string $text): void
    {
        TelegramRequest::sendMessage(['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown']);
    }
}
