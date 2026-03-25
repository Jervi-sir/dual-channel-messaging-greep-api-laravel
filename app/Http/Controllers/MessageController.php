<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $conversation->customer_id === $user->id || $conversation->tradesperson_id === $user->id,
            403
        );

        $request->validate([
            'message' => 'required_without:file|string|max:5000',
            'file' => [
                'nullable',
                File::types([
                    'jpg',
                    'jpeg',
                    'png',
                    'gif',
                    'webp',
                    'mp4',
                    'mov',
                    'webm',
                    'mp3',
                    'wav',
                    'ogg',
                    'm4a',
                    'pdf',
                ])->max(10 * 1024),
            ],
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('chat-media', 'public');
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'type' => 'user',
            'channel' => 'in_app',
            'message' => trim((string) $request->input('message', '')),
            'file_path' => $filePath,
        ]);

        $message->load('sender:id,name');

        $conversation->touch();

        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'message' => $message->toChatArray(),
        ], 201);
    }
}
