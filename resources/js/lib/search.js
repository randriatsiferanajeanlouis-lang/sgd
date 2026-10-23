/**
 * Règle de recherche commune (identique au serveur, App\Support\Search) :
 * une seule lettre → un mot qui commence par cette lettre ; deux caractères
 * ou plus → le texte contient la saisie. Casse et accents sont ignorés.
 */
export const normalize = (text) => String(text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

export function matchesSearch(text, term) {
    const needle = normalize(term.trim());

    if (needle === '') {
        return true;
    }

    const haystack = normalize(text);

    return needle.length === 1 ? haystack.split(/[^a-z0-9]+/).some((word) => word.startsWith(needle)) : haystack.includes(needle);
}
