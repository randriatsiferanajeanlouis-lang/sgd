import { Paperclip } from 'lucide-react';

/** Nom d'une pièce : lien de téléchargement si l'utilisateur y a droit, sinon simple texte. */
export default function DocumentLink({ document, withIcon = true }) {
    const icon = withIcon && <Paperclip className="size-4" aria-hidden="true" />;

    if (!document.url) {
        return (
            <span className="inline-flex items-center gap-1">
                {icon} {document.name}
            </span>
        );
    }

    return (
        <a href={document.url} className="inline-flex items-center gap-1 font-semibold text-brand-600 hover:underline dark:text-brand-300">
            {icon} {document.name}
        </a>
    );
}
