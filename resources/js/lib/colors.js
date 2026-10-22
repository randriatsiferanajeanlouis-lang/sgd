/**
 * Couleur envoyée par Laravel : chaîne CSS, ou paire { light, dark } (couleurs
 * des départements) résolue selon le thème actif.
 */
export function resolveColor(color, theme) {
    if (!color) {
        return null;
    }

    return typeof color === 'string' ? color : color[theme] ?? color.light;
}
