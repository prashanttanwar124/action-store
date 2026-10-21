<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password', 'email_verified_at', 'reward_points'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'reward_points' => 100,
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->reward_points ??= 100;
        });

        static::created(function (User $user) {
            $user->ensureWelcomeBonus();

            $initialBalance = (int) ($user->reward_points ?? 100);
            if ($initialBalance !== 100) {
                $user->rewardPoints()->create([
                    'order_id' => null,
                    'points' => $initialBalance - 100,
                    'description' => 'Initial balance adjustment',
                ]);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'reward_points' => 'integer',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<RewardPoint, $this>
     */
    public function rewardPoints(): HasMany
    {
        return $this->hasMany(RewardPoint::class);
    }

    /**
     * Ensure the user has a base welcome bonus ledger entry.
     */
    public function ensureWelcomeBonus(): void
    {
        if (! empty($this->id) && ! $this->rewardPoints()->whereNull('order_id')->exists()) {
            $this->rewardPoints()->create([
                'order_id' => null,
                'points' => 100,
                'description' => 'Welcome bonus',
            ]);
        }
    }

    /**
     * Recalculate and persist the authoritative reward points balance from the ledger.
     */
    public function refreshRewardPointsBalance(): int
    {
        if (empty($this->id)) {
            return 100;
        }

        $this->ensureWelcomeBonus();

        $balance = (int) $this->rewardPoints()->sum('points');

        DB::table('users')->where('id', $this->id)->update(['reward_points' => $balance]);
        $this->reward_points = $balance;
        $this->syncOriginalAttribute('reward_points');

        return $balance;
    }
}
