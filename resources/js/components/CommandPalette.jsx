import { useEffect, useState } from 'react';
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from '@/components/ui/command';
import { ArrowRight } from 'lucide-react';

/**
 * Recherche rapide de page (Ctrl K ou ⌘K) : uniquement les pages auxquelles
 * l'utilisateur a droit (liste construite côté serveur par App\Support\Navigation).
 */
export default function CommandPalette({ groups, initialOpen = false }) {
    const [open, setOpen] = useState(initialOpen);

    useEffect(() => {
        const onKey = (event) => {
            if (event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                setOpen((value) => !value);
            }
        };
        const onOpen = () => setOpen(true);

        window.addEventListener('keydown', onKey);
        window.addEventListener('open-command-palette', onOpen);

        return () => {
            window.removeEventListener('keydown', onKey);
            window.removeEventListener('open-command-palette', onOpen);
        };
    }, []);

    const go = (url) => {
        setOpen(false);
        window.location.assign(url);
    };

    return (
        <CommandDialog open={open} onOpenChange={setOpen} title="Rechercher une page" description="Tapez le nom d’une page puis Entrée">
            <CommandInput placeholder="Rechercher une page…" />
            <CommandList>
                <CommandEmpty>Aucune page trouvée.</CommandEmpty>
                {groups.map((group, index) => (
                    <div key={group.label}>
                        {index > 0 && <CommandSeparator />}
                        <CommandGroup heading={group.label}>
                            {group.items.map((item) => (
                                <CommandItem key={item.url} value={`${group.label} ${item.label}`} onSelect={() => go(item.url)}>
                                    <ArrowRight className="text-muted-foreground" />
                                    {item.label}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </div>
                ))}
            </CommandList>
        </CommandDialog>
    );
}
