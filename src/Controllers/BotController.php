<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request as TelegramRequest;
use Longman\TelegramBot\Entities\InlineKeyboard;
use App\Models\User;

class BotController
{
    public function handle(Request $request, Response $response): Response
    {
        try 
        {
            $token = getenv('TG_TOKEN');
            $telegram = new Telegram($token);

            $input = json_decode($request->getBody()->getContents(), true);

            if (!empty($input['message'])) 
            {
                $chatId = $input['message']['chat']['id'];
                $text = $input['message']['text'] ?? '';
                $from = $input['message']['from'];

                if ($text === '/start') 
                {
                    $inline_keyboard = new InlineKeyboard([
                        ['text' => 'Москва', 'callback_data' => 'set_city:Москва'],
                        ['text' => 'Санкт-Петербург', 'callback_data' => 'set_city:СПб'],
                    ], [
                        ['text' => 'Новосибирск', 'callback_data' => 'set_city:Новосибирск'],
                        ['text' => 'Екатеринбург', 'callback_data' => 'set_city:Екатеринбург'],
                    ]);

                    TelegramRequest::initialize($telegram);
                    TelegramRequest::sendMessage([
                        'chat_id'      => $chatId,
                        'text'         => "Привет! Добро пожаловать в Афишу. Выберите ваш город из списка ниже:",
                        'reply_markup' => $inline_keyboard,
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

                    User::updateOrCreate(
                        ['telegram_id' => $from['id']],
                        [
                            'username' => $from['username'] ?? null,
                            'city'     => $chosenCity 
                        ]
                    );

                    TelegramRequest::initialize($telegram);
                    
                    TelegramRequest::answerCallbackQuery([
                        'callback_query_id' => $callbackQuery['id'],
                        'text'              => "Город сохранен: $chosenCity",
                    ]);

                    TelegramRequest::sendMessage([
                        'chat_id' => $chatId,
                        'text'    => "Отлично! Ваш город — **$chosenCity**. Теперь вы можете искать или добавлять мероприятия.",
                        'parse_mode' => 'Markdown',
                    ]);
                }
            }

        } 
        catch (\Exception $e) 
        {
            // ...
        }

        $response->getBody()->write(json_encode(['status' => 'ok']));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
