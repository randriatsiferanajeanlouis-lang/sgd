import { lazy, Suspense, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Toaster } from '@/components/ui/sonner';
import { flashToToasts } from '@/lib/toasts';

// Chargée seulement à la première ouverture (Ctrl K ou bouton de recherche).
const CommandPalette = lazy(() => import('@/components/CommandPalette'));

/**
 * Composants React présents sur toutes les pages (Blade comme React) :
 * notifications « toast » (sonner) et recherche rapide Ctrl K (cmdk).
 * Les données viennent d'un bloc JSON écrit par le layout Blade.
 */
function readData() {
    try {
        return JSON.parse(document.getElementById('islands-data')?.textContent ?? '{}');
    } catch {
        return {};
    }
}

function PaletteLoader({ groups }) {
    const [requested, setRequested] = useState(false);

    useEffect(() => {
        if (requested) {
            return undefined;
        }

        const onKey = (event) => {
            if (event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                setRequested(true);
            }
        };
        const onOpen = () => setRequested(true);

        window.addEventListener('keydown', onKey);
        window.addEventListener('open-command-palette', onOpen);

        return () => {
            window.removeEventListener('keydown', onKey);
            window.removeEventListener('open-command-palette', onOpen);
        };
    }, [requested]);

    return requested ? (
        <Suspense fallback={null}>
            <CommandPalette groups={groups} initialOpen />
        </Suspense>
    ) : null;
}

function Islands({ data }) {
    return (
        <>
            <Toaster position="top-right" richColors closeButton />
            <PaletteLoader groups={data.commands ?? []} />
        </>
    );
}

const root = document.getElementById('react-islands');

if (root) {
    createRoot(root).render(<Islands data={readData()} />);
    // Messages de session rendus par Blade (<x-flash>) : convertis en toasts.
    queueMicrotask(flashToToasts);
}
