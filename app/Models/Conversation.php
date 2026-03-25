<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'customer_id',
        'tradesperson_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function tradesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tradesperson_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $builder) use ($user): void {
            $builder
                ->where('customer_id', $user->id)
                ->orWhere('tradesperson_id', $user->id);
        });
    }

    public function scopeBetweenUsers(Builder $query, User $firstUser, User $secondUser): Builder
    {
        return $query->where(function (Builder $builder) use ($firstUser, $secondUser): void {
            $builder
                ->where('customer_id', $firstUser->id)
                ->where('tradesperson_id', $secondUser->id);
        })->orWhere(function (Builder $builder) use ($firstUser, $secondUser): void {
            $builder
                ->where('customer_id', $secondUser->id)
                ->where('tradesperson_id', $firstUser->id);
        });
    }

    public function otherParticipantFor(User $user): ?User
    {
        if ($this->customer_id === $user->id) {
            return $this->tradesperson;
        }

        if ($this->tradesperson_id === $user->id) {
            return $this->customer;
        }

        return null;
    }
}
