<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $conversations = $this->conversationPayloads($user);

        return Inertia::render('conversations/page', [
            'conversations' => $conversations,
            'selectedConversationId' => null,
            'messages' => [],
            'hasMoreMessages' => false,
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $user = $request->user();

        $this->authorizeConversation($conversation, $user);

        $conversations = $this->conversationPayloads($user);
        [$messages, $hasMoreMessages] = $this->messagePayloads($conversation);

        return Inertia::render('conversations/page', [
            'conversations' => $conversations,
            'selectedConversationId' => $conversation->id,
            'messages' => $messages,
            'hasMoreMessages' => $hasMoreMessages,
        ]);
    }

    public function loadMessages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        $this->authorizeConversation($conversation, $user);

        $beforeId = $request->integer('before_id');

        [$messages, $hasMoreMessages] = $this->messagePayloads($conversation, $beforeId);

        return response()->json([
            'messages' => $messages,
            'has_more' => $hasMoreMessages,
        ]);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = trim((string) $request->string('query'));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'users' => [],
            ]);
        }

        $users = User::query()
            ->whereKeyNot($user->id)
            ->where(function ($builder) use ($query): void {
                $builder
                    ->whereLike('name', "%{$query}%")
                    ->orWhereLike('email', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn (User $searchedUser): array => Arr::only($searchedUser->toArray(), [
                'id',
                'name',
                'email',
                'role',
            ]));

        return response()->json([
            'users' => $users,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $recipient = User::query()
            ->whereKey($validated['user_id'])
            ->whereKeyNot($user->id)
            ->firstOrFail();

        [$customerId, $tradespersonId] = $this->resolveParticipantIds($user, $recipient);

        $conversation = Conversation::query()
            ->betweenUsers($user, $recipient)
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'customer_id' => $customerId,
                'tradesperson_id' => $tradespersonId,
            ]);
        }

        return response()->json([
            'conversation_id' => $conversation->id,
        ], 201);
    }

    protected function authorizeConversation(Conversation $conversation, User $user): void
    {
        abort_unless(
            $conversation->customer_id === $user->id || $conversation->tradesperson_id === $user->id,
            403
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function conversationPayloads(User $user): Collection
    {
        return Conversation::query()
            ->forUser($user)
            ->with([
                'customer:id,name,email',
                'tradesperson:id,name,email',
                'latestMessage.sender:id,name',
            ])
            ->withCount('messages')
            ->latest('updated_at')
            ->get()
            ->map(function (Conversation $conversation) use ($user): array {
                $otherUser = $conversation->otherParticipantFor($user);

                return [
                    'id' => $conversation->id,
                    'other_user' => $otherUser ? [
                        'id' => $otherUser->id,
                        'name' => $otherUser->name,
                        'email' => $otherUser->email,
                    ] : null,
                    'messages_count' => $conversation->messages_count,
                    'latest_message' => $conversation->latestMessage?->toChatArray(),
                    'updated_at' => $conversation->updated_at?->toISOString(),
                ];
            });
    }

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: bool}
     */
    protected function messagePayloads(Conversation $conversation, ?int $beforeId = null): array
    {
        $messages = $conversation->messages()
            ->with('sender:id,name')
            ->when($beforeId, fn ($query) => $query->where('id', '<', $beforeId))
            ->latest('id')
            ->take(31)
            ->get();

        $hasMoreMessages = $messages->count() > 30;

        return [
            $messages
                ->take(30)
                ->reverse()
                ->values()
                ->map(fn (Message $message): array => $message->toChatArray()),
            $hasMoreMessages,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function resolveParticipantIds(User $user, User $recipient): array
    {
        if ($user->role === 'tradesperson' && $recipient->role !== 'tradesperson') {
            return [$recipient->id, $user->id];
        }

        if ($recipient->role === 'tradesperson') {
            return [$user->id, $recipient->id];
        }

        if ($user->id < $recipient->id) {
            return [$user->id, $recipient->id];
        }

        return [$recipient->id, $user->id];
    }
}
