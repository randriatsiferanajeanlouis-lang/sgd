import { toast } from 'sonner';

const kinds = { success: toast.success, warning: toast.warning, error: toast.error, info: toast.info };

/** Affiche un message sous forme de toast (success, warning, error ou info). */
export function notify(kind, message) {
    if (message) {
        (kinds[kind] ?? toast)(message);
    }
}

/**
 * Les bandeaux Blade marqués data-toast sont remplacés par un toast. Sans
 * JavaScript, le bandeau reste affiché : le message n'est jamais perdu.
 */
export function flashToToasts() {
    document.querySelectorAll('[data-toast]').forEach((banner) => {
        notify(banner.dataset.toast, banner.textContent.trim());
        banner.remove();
    });
}

/** Messages partagés par Inertia (props « flash ») après une navigation. */
export function flashPropsToToasts(flash) {
    notify('success', flash?.status);
    notify('warning', flash?.warning);
    notify('error', flash?.error);
}
