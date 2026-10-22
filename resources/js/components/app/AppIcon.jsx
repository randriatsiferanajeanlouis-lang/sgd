import {
    Activity,
    Bell,
    Building2,
    CircleCheck,
    Clock,
    FileText,
    Folder,
    Inbox,
    KeyRound,
    LogIn,
    Send,
    Settings,
    ShieldCheck,
    Trash2,
    UserRound,
    Users,
} from 'lucide-react';

/** Noms d'icônes envoyés par Laravel → icônes lucide (même vocabulaire que <x-icon>). */
const icons = {
    activity: Activity,
    bell: Bell,
    building: Building2,
    check: CircleCheck,
    clock: Clock,
    document: FileText,
    folder: Folder,
    inbox: Inbox,
    key: KeyRound,
    login: LogIn,
    send: Send,
    settings: Settings,
    shield: ShieldCheck,
    trash: Trash2,
    user: UserRound,
    users: Users,
};

export default function AppIcon({ name, className = 'size-5', ...props }) {
    const Icon = icons[name] ?? FileText;

    return <Icon className={className} aria-hidden="true" {...props} />;
}
