<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'type',
        'channel',
        'relay_channel',
        'message',
        'file_path',
        'provider_message_id',
        'provider_status',
        'provider_error',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toChatArray(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'type' => $this->type,
            'channel' => $this->channel,
            'relay_channel' => $this->relay_channel,
            'message' => $this->message,
            'file_path' => $this->file_path,
            'file_url' => $this->file_path ? asset('storage/'.$this->file_path) : null,
            'provider_message_id' => $this->provider_message_id,
            'provider_status' => $this->provider_status,
            'provider_error' => $this->provider_error,
            'created_at' => $this->created_at?->toISOString(),
            'sender' => $this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
            ] : null,
        ];
    }
}
