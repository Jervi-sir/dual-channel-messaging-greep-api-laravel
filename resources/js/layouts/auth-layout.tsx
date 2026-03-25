import { Head } from '@inertiajs/react';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    children,
    title,
    description,
    ...props
}: {
    children: React.ReactNode;
    title: string;
    description: string;
}) {
    return (
        <>
            <Head>
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
                `}</style>
            </Head>
            <AuthLayoutTemplate
                title={title}
                description={description}
                {...props}
            >
                {children}
            </AuthLayoutTemplate>
        </>
    );
}
