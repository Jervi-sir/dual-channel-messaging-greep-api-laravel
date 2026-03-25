import { Head, Link, usePage } from '@inertiajs/react';
import { login, register } from '@/routes';
import { MessageSquare, Shield, Zap, Globe, Cpu, Users } from 'lucide-react';
import conversations from '@/routes/conversations';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;

    return (
        <div className="min-h-screen bg-[#030303] font-sans text-white/90 antialiased selection:bg-emerald-500/30 selection:text-emerald-200">
            <Head title="Green - Dual-Channel Messaging">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link
                    rel="preconnect"
                    href="https://fonts.gstatic.com"
                    crossOrigin="anonymous"
                />
                <link
                    href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap"
                    rel="stylesheet"
                />
                <style>{`
                    body { font-family: 'Outfit', sans-serif; }
                    .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
                    .gradient-text { background: linear-gradient(135deg, #10b981 0%, #a855f7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
                    .animate-float { animation: float 6s ease-in-out infinite; }
                    @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }
                `}</style>
            </Head>

            {/* Background Glows */}
            <div className="pointer-events-none fixed inset-0 overflow-hidden">
                <div className="absolute -top-[10%] -left-[10%] h-[50%] w-[50%] rounded-full bg-emerald-500/10 blur-[120px]" />
                <div className="absolute -right-[10%] -bottom-[10%] h-[50%] w-[50%] rounded-full bg-purple-500/10 blur-[120px]" />
            </div>

            {/* Navigation */}
            <header className="fixed top-0 z-50 w-full border-b border-white/5 bg-black/20 backdrop-blur-md">
                <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-6">
                    <div className="flex items-center gap-3">
                        <img
                            src="/images/green-logo.png"
                            alt="Green Logo"
                            className="h-10 w-10 rounded-xl object-contain"
                        />
                        <span className="text-xl font-bold tracking-tight">
                            Green
                        </span>
                    </div>
                    <nav className="flex items-center gap-6">
                        {auth.user ? (
                            <Link
                                href={conversations.index()}
                                className="glass rounded-full px-6 py-2.5 text-sm font-medium transition-all hover:bg-white/10"
                            >
                                Go to Conversations
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="text-sm font-medium text-white/60 transition-colors hover:text-white"
                                >
                                    Log in
                                </Link>
                                {canRegister && (
                                    <Link
                                        href={register()}
                                        className="rounded-full bg-white px-6 py-2.5 text-sm font-semibold text-black transition-all hover:scale-105 hover:bg-emerald-50"
                                    >
                                        Get Started
                                    </Link>
                                )}
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main className="relative pt-32">
                {/* Hero Section */}
                <section className="mx-auto max-w-7xl px-6 py-20 text-center lg:py-32">
                    <div className="animate-float mb-8 inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/5 px-4 py-1.5 text-sm font-medium text-emerald-400">
                        <Zap size={16} />
                        <span>The Future of Dual-Channel Messaging</span>
                    </div>
                    <h1 className="mx-auto max-w-4xl text-5xl leading-tight font-bold tracking-tight sm:text-7xl">
                        Connect with{' '}
                        <span className="gradient-text">Green</span>.
                        <br />
                        Dual-stream intelligence.
                    </h1>
                    <p className="mx-auto mt-8 max-w-2xl text-lg leading-relaxed text-white/50 sm:text-xl">
                        The world's first dual-channel messaging system designed
                        for high-performance scale, secure direct communication,
                        and managed broadcast streams.
                    </p>
                    <div className="mt-12 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <Link
                            href={register()}
                            className="w-full rounded-2xl bg-white px-10 py-4 text-center text-lg font-bold text-black transition-all hover:scale-105 sm:w-auto"
                        >
                            Start Messaging Free
                        </Link>
                        <a
                            href="#features"
                            className="glass w-full rounded-2xl px-10 py-4 text-center text-lg font-medium transition-all hover:bg-white/5 sm:w-auto"
                        >
                            Explore Features
                        </a>
                    </div>

                    {/* Dashboard Preview mockup -> Now a Video Player */}
                    <div className="group mt-20 overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-b from-white/5 to-transparent p-2 shadow-2xl transition-all duration-700 hover:border-emerald-500/20 hover:shadow-emerald-500/10 lg:mt-32">
                        <div className="relative aspect-[16/9] overflow-hidden rounded-2xl bg-[#0a0a0a]">
                            <iframe
                                src="https://player.vimeo.com/video/1177087177?badge=0&autopause=0&quality=1080p&title=0&byline=0&portrait=0"
                                className="absolute inset-0 h-full w-full"
                                allow="autoplay; fullscreen; picture-in-picture"
                                allowFullScreen
                                title="Green API Platform Preview"
                                loading="lazy"
                            ></iframe>
                            
                            {/* Static Placeholder (Shown while iframe loads) */}
                            <div className="pointer-events-none absolute inset-0 -z-10 flex flex-col items-center justify-center">
                                <img
                                    src="/images/green-logo.png"
                                    alt="Platform Logo"
                                    className="mb-8 w-32 animate-pulse opacity-20"
                                />
                            </div>

                            {/* Decorative Overlays */}
                            <div className="pointer-events-none absolute inset-0 ring-1 ring-inset ring-white/10 rounded-2xl"></div>
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section
                    id="features"
                    className="mx-auto max-w-7xl px-6 py-24 lg:py-40"
                >
                    <div className="mb-20 text-center">
                        <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
                            Everything you need to communicate at scale
                        </h2>
                        <p className="mt-4 text-white/40">
                            Built on the most advanced Laravel & React
                            ecosystem.
                        </p>
                    </div>

                    <div className="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                        {[
                            {
                                icon: (
                                    <MessageSquare className="text-emerald-400" />
                                ),
                                title: 'Dual-Channel Protocol',
                                desc: 'Split-stream architecture for separate administrative and peer-to-peer data channels.',
                            },
                            {
                                icon: <Shield className="text-purple-400" />,
                                title: 'End-to-End Security',
                                desc: 'Military-grade encryption for every message, ensuring your data remains yours alone.',
                            },
                            {
                                icon: <Zap className="text-yellow-400" />,
                                title: 'Real-time Precision',
                                desc: 'Powered by Laravel Reverb for sub-millisecond latency in every interaction.',
                            },
                            {
                                icon: <Globe className="text-blue-400" />,
                                title: 'Global Infrastructure',
                                desc: 'Scalable messaging nodes deployed globally to ensure uptime and speed.',
                            },
                            {
                                icon: <Cpu className="text-orange-400" />,
                                title: 'API First',
                                desc: 'A robust API that lets you integrate Green messaging into any existing platform.',
                            },
                            {
                                icon: <Users className="text-pink-400" />,
                                title: 'Presence Management',
                                desc: 'Advanced presence channels and user status tracking out of the box.',
                            },
                        ].map((feature, i) => (
                            <div
                                key={i}
                                className="glass group rounded-3xl p-8 transition-all hover:-translate-y-2 hover:bg-white/5"
                            >
                                <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white/5 transition-all group-hover:scale-110">
                                    {feature.icon}
                                </div>
                                <h3 className="mb-3 text-xl font-semibold">
                                    {feature.title}
                                </h3>
                                <p className="leading-relaxed text-white/40">
                                    {feature.desc}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>
            </main>

            {/* Footer */}
            <footer className="border-t border-white/5 py-12">
                <div className="mx-auto max-w-7xl px-6 text-center">
                    <div className="mb-6 flex items-center justify-center gap-2 opacity-60">
                        <img
                            src="/images/green-logo.png"
                            alt="Green Logo"
                            className="h-6 w-6 grayscale"
                        />
                        <span className="font-bold">Green</span>
                    </div>
                    <p className="text-sm text-white/20">
                        &copy; {new Date().getFullYear()} Green Messaging. All
                        rights reserved.
                    </p>
                    <div className="mt-6 flex justify-center gap-6 text-xs text-white/30">
                        <a href="#" className="hover:text-emerald-400">
                            Documentation
                        </a>
                        <a href="#" className="hover:text-emerald-400">
                            API
                        </a>
                        <a href="#" className="hover:text-emerald-400">
                            Status
                        </a>
                    </div>
                </div>
            </footer>
        </div>
    );
}
