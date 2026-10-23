import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Ban, KeyRound, Pencil, RotateCcw } from 'lucide-react';
import ConfirmDialog from '@/components/app/ConfirmDialog';
import LiveSearch from '@/components/app/LiveSearch';
import PageHeader from '@/components/app/PageHeader';
import Pagination from '@/components/app/Pagination';
import StatusBadge from '@/components/app/StatusBadge';
import TemporaryPasswordBanner from '@/components/app/TemporaryPasswordBanner';
import { cleanParams } from '@/components/app/DossierFilters';

const SELECT = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-900';

function Select({ id, label, value, onChange, options }) {
    return (
        <div>
            <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                {label}
            </label>
            <select id={id} name={id} value={value} onChange={(event) => onChange(event.target.value)} className={SELECT}>
                <option value="">Tous</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

/** Modifier, Désactiver / Réactiver, Réinitialiser le mot de passe — selon les droits calculés par Laravel. */
function RowActions({ user }) {
    const [confirm, setConfirm] = useState(null);
    const [busy, setBusy] = useState(false);
    const post = (url) => {
        setBusy(true);
        router.post(url, {}, { preserveScroll: true, onFinish: () => { setBusy(false); setConfirm(null); } });
    };

    return (
        <div className="flex flex-wrap justify-end gap-2 sm:flex-nowrap">
            <a href={user.editUrl} className="btn-secondary p-2" title="Modifier" aria-label="Modifier">
                <Pencil className="size-4" aria-hidden="true" />
            </a>
            {user.canDeactivate && (
                <button type="button" disabled={busy} onClick={() => setConfirm('deactivate')} className="btn-danger p-2" title="Désactiver" aria-label="Désactiver">
                    <Ban className="size-4" aria-hidden="true" />
                </button>
            )}
            {user.canReactivate && (
                <button type="button" disabled={busy} onClick={() => post(user.reactivateUrl)} className="btn-success p-2" title="Réactiver" aria-label="Réactiver">
                    <RotateCcw className="size-4" aria-hidden="true" />
                </button>
            )}
            {user.canResetPassword && (
                <button type="button" disabled={busy} onClick={() => setConfirm('reset')} className="btn-secondary p-2" title="Réinitialiser le mot de passe" aria-label="Réinitialiser le mot de passe">
                    <KeyRound className="size-4" aria-hidden="true" />
                </button>
            )}

            <ConfirmDialog
                open={confirm === 'deactivate'}
                onOpenChange={(open) => !open && setConfirm(null)}
                danger
                Icon={Ban}
                title="Désactiver le compte"
                question={`Désactiver le compte de ${user.name} ? Son historique est conservé.`}
                confirmLabel="Désactiver"
                onConfirm={() => post(user.deactivateUrl)}
                processing={busy}
            />
            <ConfirmDialog
                open={confirm === 'reset'}
                onOpenChange={(open) => !open && setConfirm(null)}
                Icon={KeyRound}
                title="Réinitialiser le mot de passe"
                question={`Générer un nouveau mot de passe temporaire pour ${user.name} ?`}
                confirmLabel="Générer"
                onConfirm={() => post(user.resetPasswordUrl)}
                processing={busy}
            />
        </div>
    );
}

/**
 * Utilisateurs (React), réservé à l'administrateur : comptes du personnel,
 * recherche avec propositions, filtres département / rôle / statut. Un compte
 * n'est jamais supprimé : il est désactivé.
 */
export default function Index({ filters, departments, roles, users, urls }) {
    const [values, setValues] = useState(filters);
    useEffect(() => setValues(filters), [filters]);
    const set = (name) => (value) => setValues((current) => ({ ...current, [name]: value }));

    const submit = (event) => {
        event.preventDefault();
        router.get(urls.index, cleanParams(values), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Utilisateurs" />

            <PageHeader
                title="Utilisateurs"
                subtitle="Comptes du personnel. Un compte n’est jamais supprimé : il est désactivé."
                actions={
                    <a href={urls.create} className="btn-primary">
                        Créer un utilisateur
                    </a>
                }
            />

            <TemporaryPasswordBanner />

            <form onSubmit={submit} className="card mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <div className="lg:col-span-2">
                    <LiveSearch label="Recherche" placeholder="Nom, prénom ou nom d’utilisateur" value={values.search} onChange={set('search')} suggestUrl={urls.index} params={{ department: values.department, role: values.role, status: values.status }} />
                </div>
                <Select id="department" label="Département" value={values.department} onChange={set('department')} options={departments} />
                <Select id="role" label="Rôle" value={values.role} onChange={set('role')} options={roles} />
                <div className="flex gap-2">
                    <div className="flex-1">
                        <Select id="status" label="Statut" value={values.status} onChange={set('status')} options={[{ value: 'active', label: 'Actifs' }, { value: 'inactive', label: 'Inactifs' }]} />
                    </div>
                    <button type="submit" className="btn-secondary self-end py-2.5">
                        Filtrer
                    </button>
                </div>
            </form>

            <div className="card overflow-x-auto p-0">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Username</th>
                            <th>Département</th>
                            <th>Fonction</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th className="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.data.map((user) => (
                            <tr key={user.id}>
                                <td data-label="Nom" className="font-medium">
                                    {user.lastName}
                                </td>
                                <td data-label="Prénom">{user.firstName}</td>
                                <td data-label="Username" className="font-mono text-xs">
                                    {user.username}
                                </td>
                                <td data-label="Département">{user.department ?? 'Super administrateur (aucun)'}</td>
                                <td data-label="Fonction">{user.jobTitle}</td>
                                <td data-label="Rôle">{user.role}</td>
                                <td data-label="Statut">{user.active ? <StatusBadge color="green">Actif</StatusBadge> : <StatusBadge color="red">Inactif</StatusBadge>}</td>
                                <td>
                                    <RowActions user={user} />
                                </td>
                            </tr>
                        ))}
                        {users.data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="py-8 text-center text-slate-500">
                                    Aucun utilisateur ne correspond à ces critères.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="mt-4">
                <Pagination paginator={users} />
            </div>
        </>
    );
}
