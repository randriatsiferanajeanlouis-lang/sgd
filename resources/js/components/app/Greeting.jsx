import { useEffect, useState } from 'react';

const capitalize = (text) => text.charAt(0).toUpperCase() + text.slice(1);

/** En-tête de page : « Bonjour / Bonsoir, Prénom », date du jour et horloge. */
export default function Greeting({ name, subtitle, children }) {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const timer = setInterval(() => setNow(new Date()), 15_000);

        return () => clearInterval(timer);
    }, []);

    const hour = now.getHours();
    const hello = hour >= 18 || hour < 5 ? 'Bonsoir' : 'Bonjour';
    const date = capitalize(new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now));
    const time = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(now);

    return (
        <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div className="min-w-0">
                <h1 className="text-2xl tracking-tight sm:text-3xl">
                    <span className="font-light">{hello}, </span>
                    <span className="font-extrabold">{name}</span>
                </h1>
                {subtitle && <p className="mt-1 text-sm text-muted-foreground">{subtitle}</p>}
                {children}
            </div>
            <div className="text-right">
                <p className="text-sm text-muted-foreground">{date}</p>
                <p className="text-3xl leading-tight font-extrabold tabular-nums">{time}</p>
            </div>
        </div>
    );
}
