<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Turns a failed bot API response into a message for the user, and logs it.
 *
 * With APP_DEBUG on, the raw status and body are shown so problems are easy to
 * see while developing. In production only messages the bot wrote for users are
 * shown: 4xx responses whose body is a plain sentence. The bot prefixes
 * developer-facing errors with "Bad Request -" or "Forbidden -", and 5xx bodies
 * are internal, so those fall back to the caller's message.
 */
class ApiError
{
    private const DEVELOPER_PREFIXES = ['Bad Request', 'Forbidden'];

    public static function message(Response $response, string $fallback): string
    {
        $body = trim($response->body());

        Log::warning('Bot API request failed', [
            'status' => $response->status(),
            'uri' => (string) $response->effectiveUri(),
            'body' => mb_substr($body, 0, 1000),
        ]);

        if (config('app.debug')) {
            return "{$fallback} [{$response->status()}] " . ($body !== '' ? $body : '(empty response)');
        }

        return self::isUserFacing($response, $body) ? $body : $fallback;
    }

    private static function isUserFacing(Response $response, string $body): bool
    {
        if (!$response->clientError() || $body === '' || mb_strlen($body) > 200) {
            return false;
        }

        foreach (self::DEVELOPER_PREFIXES as $prefix) {
            if (str_starts_with($body, $prefix)) {
                return false;
            }
        }

        // An HTML or JSON error page is never a message meant for users.
        return !str_starts_with($body, '<') && !str_starts_with($body, '{');
    }
}
