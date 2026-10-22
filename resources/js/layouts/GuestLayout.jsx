import ThemeToggle from '@/components/app/ThemeToggle';
import { Toaster } from '@/components/ui/sonner';

const LOGO = '/images/logo-sogediproma.png';

/**
 * Mise en page des visiteurs non connectés (même rendu que l'ancien
 * layout Blade « guest ») : photo de la façade sous un voile bleu, texte de
 * présentation à gauche sur grand écran, carte de contenu à droite.
 */
export default function GuestLayout({ children }) {
    return (
        // Le fond bleu nuit est porté par <body> (inertia-guest.blade.php) : un fond
        // ici masquerait la photo placée en -z-10.
        <div className="min-h-screen">
            {/* Arrière-plan : façade sous un voile bleu roi / bleu nuit */}
            <div className="fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
                <img src="/images/login-facade.jpg" alt="" className="size-full object-cover object-[30%_center]" />
                <div className="absolute inset-0 bg-[linear-gradient(145deg,rgba(12,12,74,0.92),rgba(17,17,111,0.72)_45%,rgba(35,35,184,0.45))]" />
            </div>

            <div className="grid min-h-screen lg:grid-cols-2">
                <section className="hidden p-14 text-white lg:flex lg:flex-col lg:justify-between">
                    <div className="flex flex-col items-start gap-2">
                        <img src={LOGO} alt="Sogediproma" width="330" height="110" className="h-[110px] w-auto drop-shadow-[0_6px_18px_rgba(0,0,0,0.45)]" />
                        <span className="pl-2 text-xs font-extrabold tracking-[0.11em] text-[#c9c9ff] uppercase">Gestion des dossiers</span>
                    </div>

                    <div>
                        <p className="mb-4 text-[11px] font-extrabold tracking-[0.11em] text-[#aaaaff] uppercase">Correspondances · Départements · Archives</p>
                        <h1 className="max-w-lg text-4xl leading-[1.15] font-extrabold tracking-[-0.025em]">Toutes vos correspondances, suivies et tracées.</h1>
                        <p className="mt-4 max-w-md text-[15px] leading-relaxed text-[#dedffd]">
                            Réception, transmission aux départements, réponses et archivage : chaque étape est historisée.
                        </p>
                    </div>

                    <p className="border-t border-white/10 pt-4 text-[13px] text-[#d8d8ef]">© {new Date().getFullYear()} Jean Louis Pro</p>
                </section>

                <main className="relative flex items-center justify-center px-4 py-10">
                    <ThemeToggle className="absolute top-4 right-4 bg-white/90 dark:bg-slate-900/80" />

                    <div className="w-full max-w-md rounded-[14px] border border-[#dde0ee] border-t-4 border-t-brand-500 bg-white p-6 shadow-[0_20px_50px_rgba(0,0,0,0.30)] sm:p-10 dark:border-slate-700 dark:border-t-brand-300 dark:bg-slate-900">
                        {/* Logo blanc recoloré (masque CSS) pour la carte claire sur téléphone */}
                        <div className="mb-6 lg:hidden">
                            <span
                                role="img"
                                aria-label="Sogediproma"
                                className="block h-16 w-48 bg-brand-500 dark:bg-white"
                                style={{ WebkitMask: `url('${LOGO}') left center / contain no-repeat`, mask: `url('${LOGO}') left center / contain no-repeat` }}
                            />
                        </div>
                        {children}
                    </div>
                </main>
            </div>

            <Toaster position="top-right" richColors closeButton />
        </div>
    );
}
