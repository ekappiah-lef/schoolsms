import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { CircleAlert, Eye, EyeOff, LoaderCircle, LockKeyhole, UserRound } from 'lucide-react';

/*
 * Login — implements stitch_minimalist_logo_login_page/teal_gradient_login_page:
 * Calibri (Carlito where Calibri is not installed), teal radial-gradient canvas inside a dark frame, frosted card,
 * icon inputs and the mint "LOG IN" button. Posts to the existing Laravel
 * login (username or email via the `identity` field).
 */
export default function Login({ urls }) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ identity: '', password: '' });

    // Laravel reports login failures under the resolved field (email/username).
    const error = errors.identity || errors.username || errors.email || errors.password;

    const submit = (e) => {
        e.preventDefault();
        post(urls.login, { onFinish: () => setData('password', '') });
    };

    const input =
        'w-full rounded-sm border-0 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-800 placeholder:text-[0.85rem] placeholder:font-light placeholder:text-slate-400 transition focus:outline-none focus:ring-2 focus:ring-[#12d1a5]/60';

    return (
        <div className="min-h-screen" style={{ fontFamily: "Calibri, Carlito, 'Segoe UI', system-ui, sans-serif" }}>
            <Head title="Log in">
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link href="https://fonts.googleapis.com/css2?family=Carlito:wght@400;700&display=swap" rel="stylesheet" />
            </Head>

            <main
                className="relative flex min-h-screen w-full flex-col justify-between overflow-hidden"
                style={{
                    background: 'radial-gradient(circle at 88% 28%, #2fe0b8 0%, #0c8a87 38%, #07525a 62%, #063442 100%)',
                }}
            >
                <header className="z-10 flex w-full items-center justify-between px-8 py-7 md:px-12 md:py-9">
                    <span className="flex items-baseline gap-2.5 text-white">
                        <span className="text-base font-semibold uppercase tracking-[0.3em] md:text-lg">EduNetwork</span>
                        <span className="text-[11px] font-medium uppercase tracking-[0.35em] text-[#7cf3d5]">Portal</span>
                    </span>
                </header>

                <div className="z-10 flex w-full flex-1 items-center justify-center px-4 py-8">
                    <div
                        className="relative flex w-full max-w-[420px] flex-col items-center rounded-lg p-8 sm:p-10"
                        style={{
                            background: 'rgba(255, 255, 255, 0.08)',
                            backdropFilter: 'blur(16px)',
                            WebkitBackdropFilter: 'blur(16px)',
                            border: '1px solid rgba(255, 255, 255, 0.12)',
                            boxShadow: '0 20px 45px rgba(2, 28, 33, 0.35)',
                        }}
                    >
                        <p className="mt-1 text-[11px] font-medium uppercase tracking-[0.35em] text-[#7cf3d5]">EduNetwork Portal</p>
                        <h1 className="mb-7 mt-2 text-lg font-semibold uppercase tracking-[0.25em] text-white">Log In</h1>

                        {error && (
                            <div role="alert" className="mb-4 flex w-full items-start gap-2 rounded-sm bg-white/95 px-3 py-2 text-xs font-medium text-red-700">
                                <CircleAlert className="mt-px size-4 shrink-0" />
                                <span>{error}</span>
                            </div>
                        )}

                        <form onSubmit={submit} className="w-full space-y-4" noValidate>
                            <div className="relative">
                                <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <UserRound className="size-4" strokeWidth={1.8} />
                                </span>
                                <input
                                    id="identity"
                                    name="identity"
                                    autoComplete="username"
                                    autoFocus
                                    required
                                    aria-label="Username or email"
                                    placeholder="Username or email"
                                    value={data.identity}
                                    onChange={(e) => setData('identity', e.target.value)}
                                    className={input}
                                />
                            </div>

                            <div className="relative">
                                <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <LockKeyhole className="size-4" strokeWidth={1.8} />
                                </span>
                                <input
                                    id="password"
                                    name="password"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="current-password"
                                    required
                                    aria-label="Password"
                                    placeholder="Password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className={`${input} pr-10`}
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((s) => !s)}
                                    className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                                >
                                    {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                </button>
                            </div>


                            <div className="pt-3">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex w-full items-center justify-center gap-2 rounded-sm bg-[#12d1a5] px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white shadow-md transition-all duration-200 hover:bg-[#0fb892] focus:outline-none focus:ring-2 focus:ring-teal-300 focus:ring-offset-2 focus:ring-offset-[#07525a] active:scale-[0.99] active:bg-[#0da07f] disabled:opacity-70"
                                >
                                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                                    {processing ? 'Logging in' : 'Log In'}
                                </button>
                            </div>

                            <div className="space-y-1.5 pt-4 text-center">
                                <p className="text-xs font-light text-white/75">
                                    No account yet? Contact{' '}
                                    <a href="mailto:info@lefsignature.com" className="font-medium hover:text-white">
                                        info@lefsignature.com
                                    </a>{' '}
                                    or{' '}
                                    <a href="tel:+233256111811" className="font-medium hover:text-white">
                                        0256111811
                                    </a>
                                </p>
                                <a href={urls.forgot} className="block text-[11px] font-light text-white/65 transition-colors hover:text-white/90">
                                    Forgot password?
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <footer className="z-10 flex w-full items-center justify-center px-8 pb-6 text-[11px] font-light text-white/55">
                    © {new Date().getFullYear()} EduNetwork. All rights reserved.
                </footer>
            </main>
        </div>
    );
}
