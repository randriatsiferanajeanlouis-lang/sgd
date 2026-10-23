import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import PageHeader from '@/components/app/PageHeader';
import Field from '@/components/form/Field';

const TABS = [
    ['types', 'Types de demandes'],
    ['urgencies', 'Urgences'],
    ['permissions', 'Rôles et permissions'],
    ['general', 'Réglages généraux'],
];
const INPUT = 'rounded-lg border border-slate-300 bg-white px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900';

/** Message d'erreur sous un champ d'une ligne. */
const RowError = ({ message }) => (message ? <p className="mt-1 text-xs text-red-600 dark:text-red-400">{message}</p> : null);

/** Bouton « Enregistrer » d'une ligne (affiché seulement avec le droit correspondant). */
const SaveButton = ({ form }) => (
    <button type="submit" className="btn-secondary px-3 py-1.5" disabled={form.processing}>
        Enregistrer
    </button>
);

function RequestTypeRow({ type, scopes, canManage }) {
    const form = useForm({ name: type.name, scope: type.scope, requires_complete_form: type.requiresCompleteForm, is_active: type.isActive });
    const id = `request-type-${type.id}`;

    return (
        <tr>
            <td data-label="Type">
                <input form={id} name="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={`${INPUT} w-full`} aria-label="Nom du type" />
                <RowError message={form.errors.name} />
            </td>
            <td data-label="Portée">
                <select form={id} name="scope" value={form.data.scope} onChange={(event) => form.setData('scope', event.target.value)} className={INPUT} aria-label="Portée">
                    {scopes.map((scope) => (
                        <option key={scope.value} value={scope.value}>
                            {scope.label}
                        </option>
                    ))}
                </select>
            </td>
            <td data-label="Formulaire complet">
                <input form={id} type="checkbox" name="requires_complete_form" checked={form.data.requires_complete_form} onChange={(event) => form.setData('requires_complete_form', event.target.checked)} className="size-4" aria-label="Formulaire complet obligatoire" />
            </td>
            <td data-label="Actif">
                <input form={id} type="checkbox" name="is_active" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} className="size-4" aria-label="Actif" />
            </td>
            <td className="text-right">
                {canManage && (
                    <form
                        id={id}
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(type.updateUrl, { preserveScroll: true });
                        }}
                    >
                        <SaveButton form={form} />
                    </form>
                )}
            </td>
        </tr>
    );
}

function NewRequestType({ scopes, storeUrl }) {
    const form = useForm({ name: '', scope: 'both' });

    return (
        <form
            className="card flex flex-wrap items-end gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(storeUrl, { preserveScroll: true, onSuccess: () => form.reset() });
            }}
        >
            <div className="w-full flex-1 sm:min-w-64">
                <Field name="name" label="Nouveau type" required value={form.data.name} onChange={(value) => form.setData('name', value)} error={form.errors.name} />
            </div>
            <div className="w-full sm:w-56">
                <label htmlFor="scope" className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                    Portée
                </label>
                <select id="scope" name="scope" value={form.data.scope} onChange={(event) => form.setData('scope', event.target.value)} className="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:border-slate-700 dark:bg-slate-900">
                    {scopes.map((scope) => (
                        <option key={scope.value} value={scope.value}>
                            {scope.label}
                        </option>
                    ))}
                </select>
            </div>
            <button type="submit" className="btn-primary py-2.5" disabled={form.processing}>
                Ajouter
            </button>
        </form>
    );
}

/** Équivalent lisible d'un délai : « = 3 jour(s) calendaire(s) » ou « = 36 h ». */
const delayLabel = (hours) => (hours % 24 === 0 ? `${hours / 24} jour(s) calendaire(s)` : `${hours} h`);

function UrgencyRow({ level, canManage }) {
    const form = useForm({ name: level.name, response_delay_hours: String(level.responseDelayHours), is_active: level.isActive });
    const id = `urgency-${level.id}`;
    const hours = Number(form.data.response_delay_hours) || 0;

    return (
        <tr>
            <td data-label="Niveau">
                <input form={id} name="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={INPUT} aria-label="Nom" />
                <RowError message={form.errors.name} />
            </td>
            <td data-label="Délai (heures)">
                <input
                    form={id}
                    type="number"
                    min="1"
                    name="response_delay_hours"
                    value={form.data.response_delay_hours}
                    onChange={(event) => form.setData('response_delay_hours', event.target.value)}
                    className={`${INPUT} w-28`}
                    aria-label="Délai en heures"
                />
                <span className="ml-2 text-xs text-slate-500">= {delayLabel(hours)}</span>
                <RowError message={form.errors.response_delay_hours} />
            </td>
            <td data-label="Actif">
                <input form={id} type="checkbox" name="is_active" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} className="size-4" aria-label="Actif" />
            </td>
            <td className="text-right">
                {canManage && (
                    <form
                        id={id}
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(level.updateUrl, { preserveScroll: true });
                        }}
                    >
                        <SaveButton form={form} />
                    </form>
                )}
            </td>
        </tr>
    );
}

function SettingRow({ setting, canManage }) {
    const form = useForm({ value: setting.value ?? '' });
    const id = `setting-${setting.id}`;

    return (
        <tr>
            <td data-label="Paramètre">
                {setting.label ?? setting.key} <div className="font-mono text-xs text-slate-400">{setting.key}</div>
            </td>
            <td data-label="Valeur">
                <input form={id} name="value" value={form.data.value} onChange={(event) => form.setData('value', event.target.value)} className={`${INPUT} w-full`} aria-label="Valeur" />
                <RowError message={form.errors.value} />
            </td>
            <td className="text-right">
                {canManage && (
                    <form
                        id={id}
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(setting.updateUrl, { preserveScroll: true });
                        }}
                    >
                        <SaveButton form={form} />
                    </form>
                )}
            </td>
        </tr>
    );
}

/**
 * Paramètres (React), réservé à l'administrateur : types de demandes,
 * urgences, accès à l'écran des rôles et réglages généraux. Chaque ligne
 * s'enregistre séparément ; toutes les modifications sont tracées.
 */
export default function Index({ initialTab, requestTypes, urgencyLevels, settings, scopes, can, urls }) {
    const [tab, setTab] = useState(TABS.some(([key]) => key === initialTab) ? initialTab : 'types');

    return (
        <>
            <Head title="Paramètres" />

            <PageHeader title="Paramètres" subtitle="Toutes les modifications sont tracées dans le journal d’audit." />

            <div className="mb-6 flex flex-wrap gap-2" role="tablist">
                {TABS.map(([key, label]) => (
                    <button key={key} type="button" role="tab" aria-selected={tab === key} onClick={() => setTab(key)} className={tab === key ? 'btn-primary' : 'btn-secondary'}>
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'types' && (
                <section className="space-y-6">
                    {can.manageRequestTypes && <NewRequestType scopes={scopes} storeUrl={urls.storeRequestType} />}

                    <div className="card overflow-x-auto p-0">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Portée</th>
                                    <th title="Tous les champs du formulaire deviennent obligatoires (ex. Sponsoring)">Formulaire complet</th>
                                    <th>Actif</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {requestTypes.map((type) => (
                                    <RequestTypeRow key={type.id} type={type} scopes={scopes} canManage={can.manageRequestTypes} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

            {tab === 'urgencies' && (
                <section className="card overflow-x-auto p-0">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>Niveau</th>
                                <th>Délai de réponse (heures)</th>
                                <th>Actif</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {urgencyLevels.map((level) => (
                                <UrgencyRow key={level.id} level={level} canManage={can.manageUrgencies} />
                            ))}
                        </tbody>
                    </table>
                </section>
            )}

            {tab === 'permissions' && (
                <section className="card flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-semibold">Rôles et permissions</h2>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Les permissions sont regroupées par domaine, avec une fiche par rôle et une matrice de synthèse.</p>
                    </div>
                    {can.managePermissions && (
                        <a href={urls.roles} className="btn-primary">
                            <ShieldCheck className="size-4" aria-hidden="true" /> Ouvrir l’écran des rôles
                        </a>
                    )}
                </section>
            )}

            {tab === 'general' && (
                <section className="card overflow-x-auto p-0">
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>Paramètre</th>
                                <th>Valeur</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {settings.map((setting) => (
                                <SettingRow key={setting.id} setting={setting} canManage={can.manageSettings} />
                            ))}
                        </tbody>
                    </table>
                </section>
            )}
        </>
    );
}
