import { Head, router, usePage } from '@inertiajs/react';
import { echo } from '@laravel/echo-react';
import {
    Search,
    Image,
    LoaderCircle,
    Paperclip,
    Send,
    ChevronLeft,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import {
    index as conversationsIndex,
    searchUsers,
    store as createConversation,
    show as showConversation,
    messages as conversationMessages,
} from '@/routes/conversations';
import { store as storeMessage } from '@/routes/messages';
import type { BreadcrumbItem, User } from '@/types';
import { SidebarTrigger } from '@/components/ui/sidebar';

type ConversationListUser = {
    id: number;
    name: string;
    email: string;
};

type SearchedUser = ConversationListUser & {
    role: string;
};

type ChatMessage = {
    id: number;
    conversation_id: number;
    sender_id: number | null;
    type: string;
    channel: string;
    message: string;
    file_path: string | null;
    file_url: string | null;
    created_at: string | null;
    sender: {
        id: number;
        name: string;
    } | null;
};

type ConversationItem = {
    id: number;
    other_user: ConversationListUser | null;
    messages_count: number;
    latest_message: ChatMessage | null;
    updated_at: string | null;
};

type PageProps = {
    conversations: ConversationItem[];
    selectedConversationId: number | null;
    messages: ChatMessage[];
    hasMoreMessages: boolean;
};

type MessageSentPayload = {
    message: ChatMessage;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Conversations',
        href: '/conversations',
    },
];

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
});

function formatTimestamp(value: string | null): string {
    if (!value) {
        return '';
    }

    return dateFormatter.format(new Date(value));
}

function initials(name: string | undefined): string {
    if (!name) {
        return '?';
    }

    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function isImage(url: string | null): boolean {
    return Boolean(url?.match(/\.(jpg|jpeg|png|gif|webp)$/i));
}

function isVideo(url: string | null): boolean {
    return Boolean(url?.match(/\.(mp4|mov|webm)$/i));
}

function isAudio(url: string | null): boolean {
    return Boolean(url?.match(/\.(mp3|wav|ogg|m4a)$/i));
}

function updateConversationList(
    conversations: ConversationItem[],
    incomingMessage: ChatMessage,
): ConversationItem[] {
    const currentConversation = conversations.find(
        (conversation) => conversation.id === incomingMessage.conversation_id,
    );

    if (!currentConversation) {
        return conversations;
    }

    const updatedConversation: ConversationItem = {
        ...currentConversation,
        latest_message: incomingMessage,
        messages_count: currentConversation.messages_count + 1,
        updated_at: incomingMessage.created_at,
    };

    return [
        updatedConversation,
        ...conversations.filter(
            (conversation) =>
                conversation.id !== incomingMessage.conversation_id,
        ),
    ];
}

function MessageBubble({
    message,
    isOwnMessage,
}: {
    message: ChatMessage;
    isOwnMessage: boolean;
}) {
    return (
        <div
            className={`flex ${isOwnMessage ? 'justify-end' : 'justify-start'}`}
        >
            <div
                className={`max-w-[85%] rounded-3xl px-4 py-3 shadow-sm md:max-w-[70%] ${isOwnMessage
                    ? 'rounded-br-md bg-primary text-primary-foreground'
                    : 'rounded-bl-md border bg-card text-card-foreground'
                    }`}
            >
                {!isOwnMessage && message.sender ? (
                    <p className="mb-1 text-xs font-medium opacity-70">
                        {message.sender.name}
                    </p>
                ) : null}

                {message.message ? (
                    <p className="text-sm leading-6 break-words whitespace-pre-wrap">
                        {message.message}
                    </p>
                ) : null}

                {message.file_url ? (
                    <div className={message.message ? 'mt-3' : ''}>
                        {isImage(message.file_url) ? (
                            <a
                                href={message.file_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <img
                                    src={message.file_url}
                                    alt="Shared media"
                                    className="max-h-80 rounded-2xl object-cover"
                                />
                            </a>
                        ) : null}

                        {isVideo(message.file_url) ? (
                            <video
                                controls
                                className="max-h-80 rounded-2xl"
                                src={message.file_url}
                            />
                        ) : null}

                        {isAudio(message.file_url) ? (
                            <audio
                                controls
                                className="w-full"
                                src={message.file_url}
                            />
                        ) : null}

                        {!isImage(message.file_url) &&
                            !isVideo(message.file_url) &&
                            !isAudio(message.file_url) ? (
                            <a
                                href={message.file_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-2 rounded-2xl border px-3 py-2 text-sm"
                            >
                                <Paperclip className="size-4" />
                                Open attachment
                            </a>
                        ) : null}
                    </div>
                ) : null}

                <p className="mt-2 text-right text-[11px] opacity-70">
                    {formatTimestamp(message.created_at)}
                </p>
            </div>
        </div>
    );
}

export default function ConversationsPage({
    conversations,
    selectedConversationId,
    messages,
    hasMoreMessages,
}: PageProps) {
    const { auth } = usePage<{ auth: { user: User } }>().props;
    const [conversationItems, setConversationItems] = useState(conversations);
    const [messageItems, setMessageItems] = useState(messages);
    const [hasMore, setHasMore] = useState(hasMoreMessages);
    const [draft, setDraft] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<SearchedUser[]>([]);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [isSending, setIsSending] = useState(false);
    const [isLoadingOlder, setIsLoadingOlder] = useState(false);
    const [isSearchingUsers, setIsSearchingUsers] = useState(false);
    const [isCreatingConversation, setIsCreatingConversation] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const scrollContainerRef = useRef<HTMLDivElement | null>(null);
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const initialConversationRef = useRef<number | null>(null);

    useEffect(() => {
        setConversationItems(conversations);
    }, [conversations]);

    useEffect(() => {
        const trimmedQuery = searchQuery.trim();

        if (trimmedQuery.length < 2) {
            setSearchResults([]);
            setIsSearchingUsers(false);

            return;
        }

        const timeout = window.setTimeout(async () => {
            setIsSearchingUsers(true);

            try {
                const response = await fetch(
                    searchUsers.url({
                        query: {
                            query: trimmedQuery,
                        },
                    }),
                    {
                        headers: {
                            Accept: 'application/json',
                        },
                    },
                );

                if (!response.ok) {
                    throw new Error('Unable to search users.');
                }

                const payload: { users: SearchedUser[] } =
                    await response.json();
                setSearchResults(payload.users);
            } catch {
                setSearchResults([]);
            } finally {
                setIsSearchingUsers(false);
            }
        }, 250);

        return () => {
            window.clearTimeout(timeout);
        };
    }, [searchQuery]);

    useEffect(() => {
        setMessageItems(messages);
        setHasMore(hasMoreMessages);
        setError(null);

        if (selectedConversationId !== initialConversationRef.current) {
            requestAnimationFrame(() => {
                const container = scrollContainerRef.current;

                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });

            initialConversationRef.current = selectedConversationId;
        }
    }, [messages, hasMoreMessages, selectedConversationId]);

    const selectedConversation = useMemo(
        () =>
            conversationItems.find(
                (conversation) => conversation.id === selectedConversationId,
            ) ?? null,
        [conversationItems, selectedConversationId],
    );

    const appendIncomingMessage = useCallback(
        (incomingMessage: ChatMessage) => {
            setConversationItems((current) =>
                updateConversationList(current, incomingMessage),
            );

            if (incomingMessage.conversation_id !== selectedConversationId) {
                return;
            }

            setMessageItems((current) => {
                if (
                    current.some((message) => message.id === incomingMessage.id)
                ) {
                    return current;
                }

                return [...current, incomingMessage];
            });

            requestAnimationFrame(() => {
                const container = scrollContainerRef.current;

                if (!container) {
                    return;
                }

                const isNearBottom =
                    container.scrollHeight -
                    container.scrollTop -
                    container.clientHeight <
                    160;

                if (
                    incomingMessage.sender_id === auth.user.id ||
                    isNearBottom
                ) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },
        [auth.user.id, selectedConversationId],
    );

    useEffect(() => {
        const channelIds = conversationItems.map(
            (conversation) => conversation.id,
        );

        if (channelIds.length === 0) {
            return;
        }

        const instance = echo();

        channelIds.forEach((channelId) => {
            instance
                .private(`conversation.${channelId}`)
                .listen('MessageSent', (payload: MessageSentPayload) => {
                    appendIncomingMessage(payload.message);
                });
        });

        return () => {
            channelIds.forEach((channelId) => {
                instance.leave(`conversation.${channelId}`);
            });
        };
    }, [appendIncomingMessage, conversationItems]);

    const loadOlderMessages = useCallback(async () => {
        if (
            !selectedConversation ||
            !hasMore ||
            isLoadingOlder ||
            messageItems.length === 0
        ) {
            return;
        }

        const oldestMessage = messageItems[0];
        const container = scrollContainerRef.current;
        const previousScrollHeight = container?.scrollHeight ?? 0;
        const previousScrollTop = container?.scrollTop ?? 0;

        setIsLoadingOlder(true);

        try {
            const response = await fetch(
                conversationMessages.url(selectedConversation.id, {
                    query: {
                        before_id: oldestMessage.id,
                    },
                }),
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );

            if (!response.ok) {
                throw new Error('Failed to load older messages.');
            }

            const payload: { messages: ChatMessage[]; has_more: boolean } =
                await response.json();

            setMessageItems((current) => [...payload.messages, ...current]);
            setHasMore(payload.has_more);

            requestAnimationFrame(() => {
                const currentContainer = scrollContainerRef.current;

                if (!currentContainer) {
                    return;
                }

                currentContainer.scrollTop =
                    currentContainer.scrollHeight -
                    previousScrollHeight +
                    previousScrollTop;
            });
        } catch {
            setError('Unable to load older messages.');
        } finally {
            setIsLoadingOlder(false);
        }
    }, [hasMore, isLoadingOlder, messageItems, selectedConversation]);

    const handleMessageScroll = useCallback(async () => {
        const container = scrollContainerRef.current;

        if (!container) {
            return;
        }

        if (container.scrollTop <= 80) {
            await loadOlderMessages();
        }
    }, [loadOlderMessages]);

    const handleConversationOpen = (conversationId: number) => {
        router.get(showConversation.url(conversationId), undefined, {
            preserveScroll: true,
            preserveState: false,
        });
    };

    const handleConversationCreate = async (userId: number) => {
        if (isCreatingConversation) {
            return;
        }

        setIsCreatingConversation(true);

        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');

        try {
            const response = await fetch(createConversation.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify({
                    user_id: userId,
                }),
            });

            if (!response.ok) {
                throw new Error('Unable to create conversation.');
            }

            const payload: { conversation_id: number } = await response.json();

            setSearchQuery('');
            setSearchResults([]);

            router.get(
                showConversation.url(payload.conversation_id),
                undefined,
                {
                    preserveScroll: true,
                    preserveState: false,
                },
            );
        } catch {
            setError('Unable to create conversation.');
        } finally {
            setIsCreatingConversation(false);
        }
    };

    const handleSendMessage = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!selectedConversation || isSending) {
            return;
        }

        if (draft.trim() === '' && !selectedFile) {
            return;
        }

        setIsSending(true);
        setError(null);

        const formData = new FormData();
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');

        formData.append('message', draft.trim());

        if (selectedFile) {
            formData.append('file', selectedFile);
        }

        try {
            const response = await fetch(
                storeMessage.url(selectedConversation.id),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        ...(echo().socketId()
                            ? { 'X-Socket-ID': echo().socketId() as string }
                            : {}),
                    },
                    body: formData,
                },
            );

            if (response.status === 422) {
                const payload: { errors?: Record<string, string[]> } =
                    await response.json();

                const firstError = Object.values(payload.errors ?? {})[0]?.[0];
                setError(firstError ?? 'Unable to send the message.');

                return;
            }

            if (!response.ok) {
                throw new Error('Unable to send the message.');
            }

            const payload: { message: ChatMessage } = await response.json();

            appendIncomingMessage(payload.message);
            setDraft('');
            setSelectedFile(null);

            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        } catch {
            setError('Unable to send the message.');
        } finally {
            setIsSending(false);
        }
    };

    return (
        <AppLayout>
            <Head title="Conversations" />

            <div className="flex h-screen flex-col overflow-hidden p-4">
                <div className="grid flex-1 gap-4 overflow-hidden lg:grid-cols-[340px_minmax(0,1fr)]">
                    <section
                        className={`min-h-0 overflow-hidden rounded-3xl border bg-card ${selectedConversation ? 'hidden lg:flex' : 'flex'} flex-col`}
                    >
                        <div className="border-b px-5 py-4">
                            <div className='flex flex-row items-start'>
                                <SidebarTrigger />
                                <div>
                                    <h1 className="text-lg font-semibold">
                                        Conversations
                                    </h1>
                                </div>
                            </div>

                            <div className="mt-4 space-y-3">
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={searchQuery}
                                        onChange={(event) =>
                                            setSearchQuery(event.target.value)
                                        }
                                        placeholder="Search users to start a chat"
                                        className="pl-9"
                                    />
                                </div>

                                {searchQuery.trim().length >= 2 ? (
                                    <div className="rounded-2xl border bg-background">
                                        {isSearchingUsers ? (
                                            <div className="flex items-center gap-2 px-3 py-3 text-sm text-muted-foreground">
                                                <LoaderCircle className="size-4 animate-spin" />
                                                Searching users...
                                            </div>
                                        ) : searchResults.length > 0 ? (
                                            <div className="divide-y">
                                                {searchResults.map((user) => (
                                                    <button
                                                        key={user.id}
                                                        type="button"
                                                        onClick={() => {
                                                            void handleConversationCreate(
                                                                user.id,
                                                            );
                                                        }}
                                                        className="flex w-full items-center justify-between gap-3 px-3 py-3 text-left transition hover:bg-accent/60"
                                                        disabled={
                                                            isCreatingConversation
                                                        }
                                                    >
                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-medium">
                                                                {user.name}
                                                            </p>
                                                            <p className="truncate text-xs text-muted-foreground">
                                                                {user.email} ·{' '}
                                                                {user.role}
                                                            </p>
                                                        </div>

                                                        {isCreatingConversation ? (
                                                            <LoaderCircle className="size-4 animate-spin" />
                                                        ) : null}
                                                    </button>
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="px-3 py-3 text-sm text-muted-foreground">
                                                No matching users found.
                                            </div>
                                        )}
                                    </div>
                                ) : null}
                            </div>
                        </div>

                        <div className="flex-1 overflow-hidden">
                            {conversationItems.length === 0 ? (
                                <div className="flex h-full items-center justify-center px-6 text-center text-sm text-muted-foreground">
                                    No conversations available yet.
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {conversationItems.map((conversation) => {
                                        const isActive =
                                            conversation.id ===
                                            selectedConversationId;

                                        return (
                                            <button
                                                key={conversation.id}
                                                type="button"
                                                onClick={() =>
                                                    handleConversationOpen(
                                                        conversation.id,
                                                    )
                                                }
                                                className={`flex w-full items-center gap-3 px-4 py-4 text-left transition hover:bg-accent/60 ${isActive ? 'bg-accent' : ''
                                                    }`}
                                            >
                                                <Avatar className="size-11">
                                                    <AvatarFallback>
                                                        {initials(
                                                            conversation
                                                                .other_user
                                                                ?.name,
                                                        )}
                                                    </AvatarFallback>
                                                </Avatar>

                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center justify-between gap-3">
                                                        <p className="truncate font-medium">
                                                            {
                                                                conversation
                                                                    .other_user
                                                                    ?.name
                                                            }
                                                        </p>
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatTimestamp(
                                                                conversation
                                                                    .latest_message
                                                                    ?.created_at ??
                                                                conversation.updated_at,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <p className="truncate text-sm text-muted-foreground">
                                                        {conversation
                                                            .latest_message
                                                            ?.message ||
                                                            (conversation
                                                                .latest_message
                                                                ?.file_url
                                                                ? 'Shared an attachment'
                                                                : 'No messages yet')}
                                                    </p>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </section>

                    <section
                        className={`min-h-0 overflow-hidden rounded-3xl border bg-background ${selectedConversation ? 'flex' : 'hidden lg:flex'} flex-col`}
                    >
                        {selectedConversation ? (
                            <>
                                <div className="flex items-center gap-3 border-b px-4 py-4 md:px-5">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="lg:hidden"
                                        onClick={() =>
                                            router.get(conversationsIndex.url())
                                        }
                                    >
                                        <ChevronLeft className="size-5" />
                                    </Button>

                                    <Avatar className="size-11">
                                        <AvatarFallback>
                                            {initials(
                                                selectedConversation.other_user
                                                    ?.name,
                                            )}
                                        </AvatarFallback>
                                    </Avatar>

                                    <div>
                                        <h2 className="font-semibold">
                                            {
                                                selectedConversation.other_user
                                                    ?.name
                                            }
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            {
                                                selectedConversation.other_user
                                                    ?.email
                                            }
                                        </p>
                                    </div>
                                </div>

                                <div
                                    ref={scrollContainerRef}
                                    onScroll={() => {
                                        void handleMessageScroll();
                                    }}
                                    className="min-h-0 flex-1 space-y-4 overflow-y-auto bg-muted/30 px-4 py-5 md:px-6"
                                >
                                    {isLoadingOlder ? (
                                        <div className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
                                            <LoaderCircle className="size-4 animate-spin" />
                                            Loading older messages...
                                        </div>
                                    ) : null}

                                    {messageItems.length === 0 ? (
                                        <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                                            No messages yet. Start the
                                            conversation.
                                        </div>
                                    ) : (
                                        messageItems.map((message) => (
                                            <MessageBubble
                                                key={message.id}
                                                message={message}
                                                isOwnMessage={
                                                    message.sender_id ===
                                                    auth.user.id
                                                }
                                            />
                                        ))
                                    )}
                                </div>

                                <div className="border-t bg-background px-4 py-4 md:px-5">
                                    <form
                                        onSubmit={handleSendMessage}
                                        className="space-y-3"
                                    >
                                        {selectedFile ? (
                                            <div className="flex items-center justify-between rounded-2xl border bg-muted/50 px-3 py-2 text-sm">
                                                <div className="flex items-center gap-2 truncate">
                                                    <Image className="size-4" />
                                                    <span className="truncate">
                                                        {selectedFile.name}
                                                    </span>
                                                </div>
                                                <button
                                                    type="button"
                                                    className="text-muted-foreground"
                                                    onClick={() => {
                                                        setSelectedFile(null);

                                                        if (
                                                            fileInputRef.current
                                                        ) {
                                                            fileInputRef.current.value =
                                                                '';
                                                        }
                                                    }}
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        ) : null}

                                        {error ? (
                                            <p className="text-sm text-destructive">
                                                {error}
                                            </p>
                                        ) : null}

                                        <div className="flex items-end gap-3">
                                            <label className="flex size-11 cursor-pointer items-center justify-center rounded-full border bg-background transition hover:bg-accent">
                                                <Paperclip className="size-4" />
                                                <input
                                                    ref={fileInputRef}
                                                    type="file"
                                                    className="hidden"
                                                    accept="image/*,video/*,audio/*,.pdf"
                                                    onChange={(event) => {
                                                        setSelectedFile(
                                                            event.target
                                                                .files?.[0] ??
                                                            null,
                                                        );
                                                    }}
                                                />
                                            </label>

                                            <Input
                                                value={draft}
                                                onChange={(event) =>
                                                    setDraft(event.target.value)
                                                }
                                                placeholder="Write a message"
                                                className="h-12 rounded-full bg-background"
                                            />

                                            <Button
                                                type="submit"
                                                size="icon"
                                                className="size-11 rounded-full"
                                                disabled={isSending}
                                            >
                                                {isSending ? (
                                                    <LoaderCircle className="size-4 animate-spin" />
                                                ) : (
                                                    <Send className="size-4" />
                                                )}
                                            </Button>
                                        </div>
                                    </form>
                                </div>
                            </>
                        ) : (
                            <div className="flex h-full flex-col items-center justify-center gap-3 px-6 text-center">
                                <h2 className="text-xl font-semibold">
                                    Pick a conversation
                                </h2>
                                <p className="max-w-md text-sm text-muted-foreground">
                                    Choose any conversation from the list to
                                    open the live chat, view recent messages,
                                    and keep loading older ones as you scroll
                                    upward.
                                </p>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
