import { useEffect, useState } from 'react';
import { Download } from 'lucide-react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

const isInstalled = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

/**
 * Bouton « Installer l'application » (PWA), même rôle que <x-pwa-install>.
 * L'évènement d'installation est capturé tôt par resources/js/app.js
 * (window.__pwaPrompt) ; sans lui, une aide explique la marche à suivre.
 */
export default function PwaInstall({ variant = 'icon', className }) {
    const [prompt, setPrompt] = useState(() => window.__pwaPrompt ?? null);
    const [installed, setInstalled] = useState(isInstalled);
    const [help, setHelp] = useState(false);

    useEffect(() => {
        const onInstallable = () => setPrompt(window.__pwaPrompt);
        const onInstalled = () => {
            setInstalled(true);
            setPrompt(null);
        };
        window.addEventListener('pwa-installable', onInstallable);
        window.addEventListener('appinstalled', onInstalled);

        return () => {
            window.removeEventListener('pwa-installable', onInstallable);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    if (installed) {
        return null;
    }

    const install = async () => {
        if (!prompt) {
            setHelp(true);

            return;
        }

        prompt.prompt();
        const { outcome } = await prompt.userChoice;
        setPrompt(null);
        window.__pwaPrompt = null;
        if (outcome === 'accepted') {
            setInstalled(true);
        }
    };

    const ios = isIos();
    const secure = window.isSecureContext;

    return (
        <div className={cn('relative', className)}>
            {variant === 'full' ? (
                <button type="button" onClick={install} className="btn-secondary w-full" data-testid="pwa-install">
                    <Download className="size-4" aria-hidden="true" /> Installer l’application
                </button>
            ) : (
                <button
                    type="button"
                    onClick={install}
                    data-testid="pwa-install"
                    title="Installer l’application"
                    aria-label="Installer l’application"
                    className="inline-flex size-9 items-center justify-center rounded-full text-slate-600 hover:bg-brand-50 hover:text-brand-600 dark:text-slate-300 dark:hover:bg-slate-800"
                >
                    <Download className="size-5" aria-hidden="true" />
                </button>
            )}

            <Dialog open={help} onOpenChange={setHelp}>
                <DialogContent className="border-t-4 border-t-brand-500 text-sm sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-3">
                            <img src="/icons/icon-192.png" alt="" className="size-12 rounded-xl" />
                            <DialogTitle className="text-lg font-extrabold">Installer SOGEDIPROMA</DialogTitle>
                        </div>
                        <DialogDescription className="sr-only">Marche à suivre pour installer l’application</DialogDescription>
                    </DialogHeader>

                    {ios && (
                        <ol className="list-decimal space-y-2 pl-5 text-univ-muted dark:text-slate-300">
                            <li>Ouvrez cette page dans <strong>Safari</strong>.</li>
                            <li>Touchez le bouton <strong>Partager</strong> (carré avec une flèche vers le haut).</li>
                            <li>Choisissez <strong>« Sur l’écran d’accueil »</strong>, puis <strong>Ajouter</strong>.</li>
                        </ol>
                    )}

                    {!ios && !secure && (
                        <div className="space-y-3 text-univ-muted dark:text-slate-300">
                            <p>L’installation complète exige une <strong>connexion sécurisée (HTTPS)</strong>, ce que le serveur de test n’a pas encore.</p>
                            <p className="font-bold text-univ-ink dark:text-white">Pour tester sur Android avec Chrome :</p>
                            <ol className="list-decimal space-y-1 pl-5">
                                <li>Ouvrez <code className="rounded bg-brand-50 px-1 text-brand-500">chrome://flags</code> et cherchez <strong>« Insecure origins treated as secure »</strong>.</li>
                                <li>Saisissez l’adresse <code className="rounded bg-brand-50 px-1 text-brand-500">{window.location.origin}</code>, choisissez <strong>Enabled</strong>, puis <strong>Relaunch</strong>.</li>
                                <li>Revenez ici et touchez de nouveau <strong>Installer l’application</strong>.</li>
                            </ol>
                            <p>Sinon : menu <strong>⋮</strong> de Chrome → <strong>« Ajouter à l’écran d’accueil »</strong> (raccourci simple).</p>
                        </div>
                    )}

                    {!ios && secure && (
                        <p className="text-univ-muted dark:text-slate-300">
                            Ouvrez le menu du navigateur (<strong>⋮</strong> ou <strong>…</strong>) puis choisissez <strong>« Installer l’application »</strong> ou <strong>« Ajouter à l’écran d’accueil »</strong>.
                        </p>
                    )}

                    <button type="button" className="btn-primary mt-2 w-full" onClick={() => setHelp(false)}>
                        Compris
                    </button>
                </DialogContent>
            </Dialog>
        </div>
    );
}
