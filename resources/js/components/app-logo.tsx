import { MessageSquare } from 'lucide-react';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-emerald-600 text-white">
                <MessageSquare className="size-5 fill-current" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    Green
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    Dual-Channel Messaging
                </span>
            </div>
        </>
    );
}
