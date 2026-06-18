<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\BotService;
use App\Models\User;

class BotController
{
    private BotService $botService;

    public function __construct()
    {
        $this->botService = new BotService();
    }

    public function handle(Request $request, Response $response): Response
    {
        try {
            $input = json_decode($request->getBody()->getContents(), true);

            if (!empty($input['message'])) {
                $this->routeTextMessage($input['message']);
            } elseif (!empty($input['callback_query'])) {
                $this->routeCallbackQuery($input['callback_query']);
            }

        } catch (\Exception $e) {
            // Лог ошибок
        }

        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    private function routeTextMessage(array $message): void
    {
        $chatId = $message['chat']['id'];
        $text = trim($message['text'] ?? '');
        $from = $message['from'];

        // 1. Проверяем состояние (шаг) пользователя из базы данных
        $user = User::where('telegram_id', $from['id'])->first();

        // 2. Если пользователь находится в процессе создания — отправляем в FSM-обработчик
        if ($user && $user->step !== 'idle' && $text !== '/start') {
            $this->botService->handleFsmStep($chatId, $text, $user);
            return;
        }

        // 3. Стандартные команды для режима ожидания (idle)
        if ($text === '/start') {
            $this->botService->handleStartCommand($chatId);
            return;
        }

        if (is_numeric($text)) {
            $this->botService->handleEventDetailCommand($chatId, (int)$text, $from['id']);
            return;
        }
    }

    private function routeCallbackQuery(array $callbackQuery): void
    {
        $callbackData = $callbackQuery['data'];
        $chatId = $callbackQuery['message']['chat']['id'];
        $from = $callbackQuery['from'];

        if (str_starts_with($callbackData, 'set_city:')) {
            $city = str_replace('set_city:', '', $callbackData);
            $this->botService->handleSetCityCallback($callbackQuery['id'], $chatId, $city, $from);
            return;
        }

        if (str_starts_with($callbackData, 'more_events:')) {
            $offset = (int)str_replace('more_events:', '', $callbackData);
            $this->botService->handleMoreEventsCallback($callbackQuery['id'], $chatId, $offset, $from['id']);
            return;
        }

        // Обработка кнопки «Создать»
        if ($callbackData === 'add_event') {
            $this->botService->handleStartCreateEventCallback($callbackQuery['id'], $chatId, $from['id']);
            return;
        }
    }
}
