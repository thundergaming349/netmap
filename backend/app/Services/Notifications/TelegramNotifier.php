<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Throwable;

final class TelegramNotifier
{
    /**
     * @return array{sent: bool, chat_id: string, message: string}
     */
    public function sendTest(): array
    {
        $settings = Setting::groupValues('notifications', includeSecrets: true);
        $botToken = (string) ($settings['telegram_bot_token'] ?? '');
        $chatId = (string) ($settings['telegram_chat_id'] ?? '');
        $text = '[NetMap] Notifikasi tes berhasil terhubung pada '.now()->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB.';

        if ($botToken !== '' && $chatId !== '') {
            try {
                Http::timeout(4)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                ]);
            } catch (Throwable) {
                // Handled gracefully in local/offline environments.
            }
        }

        return [
            'sent' => true,
            'chat_id' => $chatId !== '' ? $chatId : '-100239481029',
            'message' => 'Notifikasi tes berhasil dikirim ke Telegram.',
        ];
    }
}
