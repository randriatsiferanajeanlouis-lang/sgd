import { useEffect, useRef, useState } from 'react';
import { Search } from 'lucide-react';

const normalize = (text) => String(text ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/** Découpe le libellé pour surligner la partie qui correspond à la saisie (même règle que la version Blade). */
function highlight(text, term) {
    const needle = normalize(term.trim());
    const chars = [...String(text)];
    const folded = chars.map((char) => normalize(char));
    let start = -1;

    for (let i = 0; i + needle.length <= chars.length && needle !== ''; i++) {
        const wordStart = i === 0 || !/[a-z0-9]/.test(folded[i - 1]);

        if ((needle.length > 1 || wordStart) && folded.slice(i, i + needle.length).join('') === needle) {
            start = i;
            break;
        }
    }

    if (start < 0) {
        return [{ text, match: false }];
    }

    return [
        { text: chars.slice(0, start).join(''), match: false },
        { text: chars.slice(start, start + needle.length).join(''), match: true },
        { text: chars.slice(start + needle.length).join(''), match: false },
    ].filter((part) => part.text !== '');
}

/**
 * Barre de recherche avec propositions instantanées (équivalent de
 * <x-form.search-field>) : à chaque frappe, la liste renvoie ses premiers
 * résultats (mêmes filtres, mêmes droits). Entrée sans proposition choisie :
 * onSubmit filtre toute la liste.
 */
export default function LiveSearch({ name = 'search', label = 'Rechercher', placeholder, value, onChange, suggestUrl, params = {} }) {
    const [results, setResults] = useState([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(-1);
    const root = useRef(null);
    const request = useRef(null);
    const timer = useRef(null);

    useEffect(() => {
        const outside = (event) => root.current && !root.current.contains(event.target) && close();
        document.addEventListener('mousedown', outside);

        return () => {
            document.removeEventListener('mousedown', outside);
            clearTimeout(timer.current);
            request.current?.abort();
        };
    }, []);

    const close = () => {
        setOpen(false);
        setActive(-1);
    };

    const lookup = (term) => {
        clearTimeout(timer.current);

        if (term.trim() === '') {
            setResults([]);
            close();

            return;
        }

        setOpen(true);
        setLoading(true);
        timer.current = setTimeout(() => fetchResults(term.trim()), 200);
    };

    const fetchResults = async (term) => {
        const query = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== null && v !== undefined && v !== ''));
        query.set('search', term);
        query.set('suggest', '1');

        request.current?.abort();
        request.current = new AbortController();

        try {
            const response = await fetch(`${suggestUrl}?${query}`, { headers: { Accept: 'application/json' }, signal: request.current.signal });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            setResults((await response.json()).results);
            setActive(-1);
            setLoading(false);
        } catch (error) {
            if (error.name !== 'AbortError') {
                setResults([]);
                setLoading(false);
            }
        }
    };

    const onKeyDown = (event) => {
        if (event.key === 'Escape') {
            close();
        } else if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && open && results.length > 0) {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActive((current) => (current + step + results.length) % results.length);
        } else if (event.key === 'Enter' && open && results[active]) {
            // Proposition choisie : ouverture du dossier (sinon le formulaire filtre la liste).
            event.preventDefault();
            window.location.href = results[active].url;
        }
    };

    return (
        <div ref={root} className="relative">
            <label htmlFor={name} className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                {label}
            </label>

            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                <input
                    id={name}
                    name={name}
                    type="search"
                    autoComplete="off"
                    placeholder={placeholder}
                    value={value}
                    onChange={(event) => {
                        onChange(event.target.value);
                        lookup(event.target.value);
                    }}
                    onFocus={() => lookup(value)}
                    onKeyDown={onKeyDown}
                    role="combobox"
                    aria-autocomplete="list"
                    aria-controls={`${name}-suggestions`}
                    aria-expanded={open}
                    className="block w-full rounded-lg border border-slate-300 bg-white py-2.5 pr-3 pl-9 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-900"
                />
            </div>

            {open && (
                <div className="absolute inset-x-0 z-30 mt-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg sm:min-w-96 dark:border-slate-700 dark:bg-slate-900">
                    {loading && results.length === 0 && <p className="px-3 py-2.5 text-sm text-slate-500">Recherche…</p>}
                    {!loading && results.length === 0 && <p className="px-3 py-2.5 text-sm text-slate-500">Aucun résultat pour « {value} ».</p>}

                    <ul id={`${name}-suggestions`} role="listbox" className="max-h-80 overflow-y-auto">
                        {results.map((result, index) => (
                            <li key={result.url} role="option" aria-selected={index === active}>
                                <a
                                    href={result.url}
                                    onMouseEnter={() => setActive(index)}
                                    className={`block px-3 py-2 text-sm ${index === active ? 'bg-brand-50 dark:bg-brand-900/40' : ''}`}
                                >
                                    <span className="block font-medium text-slate-800 dark:text-slate-100">
                                        {highlight(result.label, value).map((part, i) => (
                                            <span key={i} className={part.match ? 'rounded bg-amber-200 text-slate-900 dark:bg-amber-500/40 dark:text-white' : ''}>
                                                {part.text}
                                            </span>
                                        ))}
                                    </span>
                                    {result.detail && <span className="block text-xs text-slate-500 dark:text-slate-400">{result.detail}</span>}
                                </a>
                            </li>
                        ))}
                    </ul>

                    {results.length > 0 && (
                        <p className="border-t border-slate-100 px-3 py-1.5 text-xs text-slate-400 dark:border-slate-800">Entrée pour filtrer toute la liste · ↑ ↓ pour choisir</p>
                    )}
                </div>
            )}
        </div>
    );
}
