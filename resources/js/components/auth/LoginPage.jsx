// Centered login screen. The only auth path is Google OAuth, which hands back
// a Sanctum token on /dashboard?token=... (captured by useAuth).
export default function LoginPage() {
    return (
        <div className="flex min-h-full items-center justify-center bg-gradient-to-b from-brand-50 to-slate-100 px-4 py-12">
            <div className="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200 sm:p-10">
                <div className="text-center">
                    <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">
                        Vacante<span className="text-brand-600">Docente</span>
                    </h1>
                    <p className="mx-auto mt-3 max-w-xs text-sm text-slate-500">
                        Organiza tu petición de vacantes docentes
                    </p>
                </div>

                <a
                    href="/auth/google"
                    className="mt-8 flex w-full items-center justify-center gap-3 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2"
                >
                    <svg className="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                        <path
                            fill="#FFC107"
                            d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"
                        />
                        <path
                            fill="#FF3D00"
                            d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"
                        />
                        <path
                            fill="#4CAF50"
                            d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238C29.211 35.091 26.715 36 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"
                        />
                        <path
                            fill="#1976D2"
                            d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"
                        />
                    </svg>
                    Acceder con Google
                </a>

                <p className="mt-6 text-center text-xs text-slate-400">
                    Tus datos son privados y no se comparten con terceros
                </p>
            </div>
        </div>
    );
}
