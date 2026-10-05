<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class User extends Authenticatable
{
    use Notifiable;

    protected $connection = 'mongodb';
    protected $table = 'users';

    // In Amusement Club, the Discord user ID is stored as "userID"
    protected $fillable = [
        'userID',
        'username',
        'tomatoes',
        'vials',
        'lemons',
        'xp',
        'preferences',
        'roles',
        'achievements',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            //
        ];
    }

    public function canWrite(): bool
    {
        if (empty($this->roles)) {
            return false;
        }
        $r = array_map('strtolower', $this->roles);
        return count(array_intersect($r, ['amuplus', 'admin', 'metamod', 'tagmod', 'auditor'])) > 0;
    }

    // Admins and auditors may look at other users' transactions and claims.
    public function canAudit(): bool
    {
        if (empty($this->roles)) {
            return false;
        }
        $r = array_map('strtolower', $this->roles);
        return count(array_intersect($r, ['admin', 'auditor'])) > 0;
    }

    public function canViewTransaction($tx): bool
    {
        return in_array($this->userID, [$tx->fromID, $tx->toID], true) || $this->canAudit();
    }

    public function canViewClaim($claim): bool
    {
        return $claim->userID === $this->userID || $this->canAudit();
    }

    /**
     * Discord avatar URL, falling back to Discord's default avatar. Looked up
     * with the bot token and cached for a day; a failed lookup is cached for
     * ten minutes only and never throws, so a slow Discord can't take pages down.
     */
    public function avatarUrl(): string
    {
        $key = 'discord_avatar_' . $this->userID;
        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $index = is_numeric($this->userID) ? (substr($this->userID, -1) % 6) : 0;
        $default = "https://cdn.discordapp.com/embed/avatars/{$index}.png";

        $botToken = config('services.discord.bot_token');
        if (!$botToken) {
            return $default;
        }

        try {
            $response = Http::withToken($botToken, 'Bot')
                ->connectTimeout(2)
                ->timeout(3)
                ->get("https://discord.com/api/v10/users/{$this->userID}");
        } catch (\Exception $e) {
            Cache::put($key, $default, 600);
            return $default;
        }

        if (!$response->successful()) {
            // 404 means the account is gone; anything else is worth retrying soon.
            Cache::put($key, $default, $response->notFound() ? 86400 : 600);
            return $default;
        }

        $hash = $response->json('avatar');
        $url = $default;
        if (!empty($hash)) {
            $ext = str_starts_with($hash, 'a_') ? 'gif' : 'png';
            $url = "https://cdn.discordapp.com/avatars/{$this->userID}/{$hash}.{$ext}?size=256";
        }
        Cache::put($key, $url, 86400);
        return $url;
    }
}
