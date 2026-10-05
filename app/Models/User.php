<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
}
