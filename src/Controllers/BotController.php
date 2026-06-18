<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request as TelegramRequest;
use Longman\TelegramBot\Entities\InlineKeyboard;
use App\Models\User;
use App\Models\Event;

class BotController
{
    public function handle(Request $request, Response $response): Response
    {
        try {
            $token = getenv('TG_TOKEN');
            $telegram = new Telegram($token);

            $input = json_decode($request->getBody()->getContents(), true);
            TelegramRequest::initialize($telegram);

            // 1. ОБРАБОТКА ОБЫЧНЫХ СООБЩЕНИЙ (Текст и Цифры)
            if (!empty($input['message'])) {
                $chatId = $input['message']['chat']['id'];
                $text = trim($input['message']['text'] ?? '');
                $from = $input['message']['from'];

                if ($text === '/start') {
                    $inline_keyboard = new InlineKeyboard([
                        ['text' => 'Москва', 'callback_data' => 'set_city:Москва'],
                        ['text' => 'Санкт-Петербург', 'callback_data' => 'set_city:СПб'],
                    ]);

                    TelegramRequest::sendMessage([
                        'chat_id'      => $chatId,
                        'text'         => "Привет! Добро пожаловать в Афишу. Выберите ваш город:",
                        'reply_markup' => $inline_keyboard,
                    ]);
                    
                    return $this->jsonResponse($response);
                }

                if (is_numeric($text)) 
                {
                    $index = (int)$text - 1;

                    $user = User::where('telegram_id', $from['id'])->first();
                    if ($user && $user->city) 
                    {
                        $events = Event::where('city', $user->city)->orderBy('id', 'asc')->take(10)->get();

                        if (isset($events[$index])) 
                        {
                            $event = $events[$index];
                            $messageText = "📌 **{$event->title}**\n\n{$event->description}";
                        } 
                        else 
                        {
                            $messageText = "❌ Мероприятия под номером $text не найдено в текущем списке.";
                        }
                    } 
                    else 
                    {
                        $messageText = "🛑 Сначала выберите город с помощью команды /start";
                    }

                    TelegramRequest::sendMessage([
                        'chat_id'    => $chatId,
                        'text'       => $messageText,
                        'parse_mode' => 'Markdown',
                    ]);
                }
            }

            if (!empty($input['callback_query'])) 
            {
                $callbackQuery = $input['callback_query'];
                $callbackData = $callbackQuery['data'];
                $chatId = $callbackQuery['message']['chat']['id'];
                $from = $callbackQuery['from'];

                if (str_starts_with($callbackData, 'set_city:')) 
                {
                    $chosenCity = str_replace('set_city:', '', $callbackData);

                    $user = User::updateOrCreate(
                        ['telegram_id' => $from['id']],
                        ['username' => $from['username'] ?? null, 'city' => $chosenCity]
                    );

                    TelegramRequest::answerCallbackQuery([
                        'callback_query_id' => $callbackQuery['id'],
                        'text'              => "Город сохранен!",
                    ]);

                    $this->sendEventsList($chatId, $chosenCity, 0);
                }

                if (str_starts_with($callbackData, 'more_events:')) 
                {
                    $offset = (int)str_replace('more_events:', '', $callbackData);
                    
                    $user = User::where('telegram_id', $from['id'])->first();

                    if ($user && $user->city) 
                    {
                        TelegramRequest::answerCallbackQuery(['callback_query_id' => $callbackQuery['id']]);
                        $this->sendEventsList($chatId, $user->city, $offset);
                    }
                }
            }

        } catch (\Exception $e) 
        {
            // ...
        }

        return $this->jsonResponse($response);
    }

    private function sendEventsList(int $chatId, string $city, int $offset): void
    {
        $events = Event::where('city', $city)
            ->orderBy('id', 'asc')
            ->skip($offset)
            ->take(10)
            ->get();

        if ($events->isEmpty()) 
        {
            TelegramRequest::sendMessage([
                'chat_id' => $chatId,
                'text'    => "🎭 Больше мероприятий в городе $city пока нет.",
            ]);

            return;
        }

        $textOutput = "📅 **Мероприятия в городе $city (Показано " . ($offset + 1) . "-" . ($offset + $events->count()) . "):**\n\n";
        
        $number = 1;

        foreach ($events as $event) 
        {
            $textOutput .= "$number **{$event->title}**\n";
            $number++;
        }
        
        $textOutput .= "\n👉 *Введите цифру (1-10), чтобы открыть описание подробнее.*";

        $nextOffset = $offset + 10;
        $keyboard = new InlineKeyboard([
            [
                ['text' => '🔄 Показать еще', 'callback_data' => "more_events:$nextOffset"],
                ['text' => '🔍 Фильтры', 'callback_data' => 'filters'],
            ],
            [
                ['text' => '➕ Добавить новое', 'callback_data' => 'add_event'],
            ]
        ]);

        TelegramRequest::sendMessage([
            'chat_id'      => $chatId,
            'text'         => $textOutput,
            'parse_mode'   => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }

    private function jsonResponse(Response $response): Response
    {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
