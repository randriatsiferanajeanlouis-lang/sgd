import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { flashPropsToToasts } from '@/lib/toasts';

/**
 * Point d'entrée React (Inertia.js). Chaque page se trouve dans
 * resources/js/pages et correspond au nom passé à Inertia::render()
 * côté Laravel, par exemple Inertia::render('Dossiers/Trash').
 */
createInertiaApp({
    title: (title) => (title ? `${title} — Sogediproma` : 'Sogediproma'),
    // Chargement à la demande : chaque page n'apporte que son propre code.
    resolve: (name) => {
        const page = import.meta.glob('./pages/**/*.jsx')[`./pages/${name}.jsx`];

        if (!page) {
            throw new Error(`Page React introuvable : ${name}`);
        }

        return page();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#22d3ee' },
});

// Messages de session (props « flash ») affichés en toast à chaque page Inertia.
// « navigate » couvre le premier affichage et les changements de page ; « success »
// couvre aussi les actions qui reviennent sur la même page (redirection vers la
// même adresse : Inertia remplace alors la page sans « navigate »). Chaque visite
// n'est traitée qu'une fois.
const shownVisits = new Set();
const showFlash = (event) => {
    const key = event.detail.visitId ?? event.detail.page;
    if (shownVisits.has(key)) {
        return;
    }
    shownVisits.add(key);
    flashPropsToToasts(event.detail.page.props.flash);
};
router.on('navigate', showFlash);
router.on('success', showFlash);
