/** Nombre maximal de caractères de l'objet d'un dossier affichés dans les listes. */
export const SUBJECT_LIST_CHARS = 15;

/**
 * Limite un texte à `max` caractères, suivis de « ... » seulement s'il est plus
 * long. La coupe se fait sur le dernier mot entier (un mot plus long que `max`
 * est coupé). Affichage uniquement : le texte d'origine n'est pas modifié.
 * Même règle que Dossier::shortSubject() côté Laravel (pages Blade).
 */
export function shortText(text, max = SUBJECT_LIST_CHARS) {
    const value = String(text ?? '').trim().replace(/\s+/g, ' ');
    const chars = [...value];

    if (chars.length <= max) {
        return value;
    }

    let cut = chars.slice(0, max).join('');
    const space = cut.lastIndexOf(' ');

    // Mot coupé au milieu : on revient au dernier mot entier, s'il y en a un.
    if (!/^[\s,;:.!?)]/u.test(chars[max]) && space !== -1) {
        cut = cut.slice(0, space);
    }

    return `${cut.replace(/[\s,;:.\-–—]+$/u, '')} ...`;
}
