<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosCashMovement extends Model
{
    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_WITHDRAWAL = 'withdrawal';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pos_shift_id',
        'admin_id',
        'type',
        'amount',
        'reason',
        'notes',
        'balance_after',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PosShift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'pos_shift_id');
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
