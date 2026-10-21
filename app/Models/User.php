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
            if (! $user->rewardPoints()->whereNull('order_id')->exists()) {
                $user->rewardPoints()->create([
                    'order_id' => null,
                    'points' => 100,
                    'description' => 'Welcome bonus',
                ]);

                $initialBalance = (int) ($user->reward_points ?? 100);
                if ($initialBalance !== 100) {
                    $diff = $initialBalance - 100;
                    $user->rewardPoints()->create([
                        'order_id' => null,
                        'points' => $diff,
                        'description' => 'Initial balance adjustment',
                    ]);
                }
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
     * Recalculate and persist the authoritative reward points balance from the ledger.
     */
    public function refreshRewardPointsBalance(): int
    {
        if (empty($this->id)) {
            return 100;
        }

        if (! $this->rewardPoints()->whereNull('order_id')->exists()) {
            $this->rewardPoints()->create([
                'order_id' => null,
                'points' => 100,
                'description' => 'Welcome bonus',
            ]);
        }

        $balance = (int) $this->rewardPoints()->sum('points');

        DB::table('users')->where('id', $this->id)->update(['reward_points' => $balance]);
        $this->reward_points = $balance;
        $this->syncOriginalAttribute('reward_points');

        return $balance;
    }

    /**
     * Synchronize all reward point ledger entries for this user and refresh balance.
     */
    public function syncAllRewardPoints(): int
    {
        if (empty($this->id)) {
            return 100;
        }

        if (! $this->rewardPoints()->whereNull('order_id')->exists()) {
            $this->rewardPoints()->create([
                'order_id' => null,
                'points' => 100,
                'description' => 'Welcome bonus',
            ]);
        }

        // Clean up any ledger records pointing to orders that no longer belong to this user
        $userOrderIds = $this->orders()->pluck('id')->all();
        $this->rewardPoints()
            ->whereNotNull('order_id')
            ->whereNotIn('order_id', $userOrderIds)
            ->delete();

        $this->orders()->each(fn (Order $order) => $order->syncRewardPoints());

        return $this->refreshRewardPointsBalance();
    }
}
