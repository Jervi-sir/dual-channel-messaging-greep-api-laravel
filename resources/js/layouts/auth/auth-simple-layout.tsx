import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-[#030303] p-6 text-white/90 antialiased selection:bg-emerald-500/30 selection:text-emerald-200 md:p-10">
            {/* Background Glows */}
            <div className="pointer-events-none fixed inset-0 overflow-hidden">
                <div className="absolute -top-[10%] -left-[10%] h-[50%] w-[50%] rounded-full bg-emerald-500/10 blur-[120px]" />
                <div className="absolute -right-[10%] -bottom-[10%] h-[50%] w-[50%] rounded-full bg-purple-500/10 blur-[120px]" />
            </div>

            <div className="relative z-10 w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <div className="mb-2 flex h-20 w-20 items-center justify-center rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-xl transition-transform hover:scale-105">
                                <img
                                    src="/images/green-logo.png"
                                    alt="Green Logo"
                                    className="h-full w-full object-contain"
                                />
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-2xl font-bold tracking-tight">
                                {title}
                            </h1>
                            <p className="text-center text-sm text-white/50">
                                {description}
                            </p>
                        </div>
                    </div>
                    <div className="glass rounded-3xl p-8 shadow-2xl">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
