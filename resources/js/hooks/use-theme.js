import { useEffect, useState } from 'react';

const current = () => (document.documentElement.classList.contains('dark') ? 'dark' : 'light');

/**
 * Thème actif (« light » ou « dark »). Le thème est géré par la classe
 * « dark » de <html> (bouton de thème du layout) : on suit ses changements.
 */
export function useTheme() {
    const [theme, setTheme] = useState(current);

    useEffect(() => {
        const observer = new MutationObserver(() => setTheme(current()));
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        return () => observer.disconnect();
    }, []);

    return { theme };
}
