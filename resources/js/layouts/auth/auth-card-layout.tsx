import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export default function AuthCardLayout({
    children,
    title,
    description,
}: PropsWithChildren<{
    name?: string;
    title?: string;
    description?: string;
}>) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-[#030303] p-6 text-white/90 antialiased selection:bg-emerald-500/30 selection:text-emerald-200 md:p-10">
            {/* Background Glows */}
            <div className="pointer-events-none fixed inset-0 overflow-hidden">
                <div className="absolute -top-[10%] -left-[10%] h-[50%] w-[50%] rounded-full bg-emerald-500/10 blur-[120px]" />
                <div className="absolute -right-[10%] -bottom-[10%] h-[50%] w-[50%] rounded-full bg-purple-500/10 blur-[120px]" />
            </div>

            <div className="relative z-10 flex w-full max-w-md flex-col gap-6">
                <Link
                    href={home()}
                    className="flex flex-col items-center gap-4 self-center font-medium"
                >
                    <div className="flex h-20 w-20 items-center justify-center rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-xl transition-transform hover:scale-105">
                        <img
                            src="/images/green-logo.png"
                            alt="Green Logo"
                            className="h-full w-full object-contain"
                        />
                    </div>
                </Link>

                <div className="flex flex-col gap-6">
                    <Card className="glass rounded-3xl border-white/5 bg-white/5 shadow-2xl">
                        <CardHeader className="px-10 pt-8 pb-0 text-center">
                            <CardTitle className="text-2xl font-bold tracking-tight">
                                {title ?? ''}
                            </CardTitle>
                            <CardDescription className="text-white/50">
                                {description ?? ''}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-10 py-8 text-white/90">
                            {children}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}
