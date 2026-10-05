<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use MongoDB\Laravel\Relations\HasOne;
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

    public function dashboardUser(): HasOne
    {
        return $this->hasOne(DashboardUser::class, 'userID', 'userID');
    }

    /**
     * The remember-me token lives in the dashboard's own collection, so login
     * and logout never write to the bot's `users` documents. Laravel still
     * calls save() after setRememberToken(), but no attribute here is dirty.
     */
    public function getRememberToken()
    {
        return $this->dashboardUser?->remember_token;
    }

    public function setRememberToken($value)
    {
        DashboardUser::put($this->userID, ['remember_token' => $value]);
        $this->unsetRelation('dashboardUser');
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
     * with the bot token and cached for 30 days; a failed lookup is cached for
     * ten minutes only and never throws, so a slow Discord can't take pages down.
     */
    public function avatarUrl(): string
    {
        $key = 'discord_avatar_' . $this->userID;
        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $default = self::defaultAvatarUrl($this->userID);

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
            Cache::put($key, $default, $response->notFound() ? now()->addDays(30) : 600);
            return $default;
        }

        $hash = $response->json('avatar');
        $url = $default;
        if (!empty($hash)) {
            $ext = str_starts_with($hash, 'a_') ? 'gif' : 'png';
            $url = "https://cdn.discordapp.com/avatars/{$this->userID}/{$hash}.{$ext}?size=256";
        }
        Cache::put($key, $url, now()->addDays(30));
        return $url;
    }

    /**
     * Discord's built-in default avatar for a user. Also the <img onerror>
     * fallback for a cached avatar URL that has gone stale on Discord's CDN.
     */
    public static function defaultAvatarUrl(?string $userID): string
    {
        $index = is_numeric($userID) ? (substr($userID, -1) % 6) : 0;
        return "https://cdn.discordapp.com/embed/avatars/{$index}.png";
    }
}
