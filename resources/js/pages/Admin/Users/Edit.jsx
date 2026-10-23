import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Ban, Building2, Clock, KeyRound, Mail } from 'lucide-react';
import ConfirmDialog from '@/components/app/ConfirmDialog';
import StatusBadge from '@/components/app/StatusBadge';
import TemporaryPasswordBanner from '@/components/app/TemporaryPasswordBanner';
import Field from '@/components/form/Field';
import FormPage from '@/components/form/FormPage';
import FormSection from '@/components/form/FormSection';

const SELECT = 'block w-full rounded-lg border bg-white px-3 py-2.5 outline-none focus:ring-2 focus:ring-brand-500/30 dark:bg-slate-900';

/** Liste déroulante (même rendu que <x-form.select>). */
function SelectField({ name, label, value, onChange, options, placeholder = null, required = false, error }) {
    return (
        <div>
            <label htmlFor={name} className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                {label}
                {required && (
                    <span className="text-red-600 dark:text-red-400" aria-hidden="true">
                        {' '}*
                    </span>
                )}
            </label>
            <select id={name} name={name} required={required} value={value} onChange={(event) => onChange(event.target.value)} className={`${SELECT} ${error ? 'border-red-400' : 'border-slate-300 focus:border-brand-500 dark:border-slate-700'}`}>
                {placeholder !== null && <option value="">{placeholder}</option>}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {error && <p className="mt-1.5 text-sm text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
}

/** Bloc « Compte » : désactiver, réactiver, réinitialiser le mot de passe (selon les droits). */
function AccountActions({ user, can, urls }) {
    const [confirm, setConfirm] = useState(null);
    const [busy, setBusy] = useState(false);
    const post = (url) => {
        setBusy(true);
        router.post(url, {}, { preserveScroll: true, onFinish: () => { setBusy(false); setConfirm(null); } });
    };

    return (
        <FormSection title="Compte" Icon={KeyRound} defaultOpen={false}>
            <div className="flex flex-wrap gap-3">
                {can.deactivate && (
                    <button type="button" className="btn-danger" disabled={busy} onClick={() => setConfirm('deactivate')}>
                        Désactiver
                    </button>
                )}
                {can.reactivate && (
                    <button type="button" className="btn-secondary" disabled={busy} onClick={() => post(urls.reactivate)}>
                        Réactiver
                    </button>
                )}
                {can.resetPassword && (
                    <button type="button" className="btn-secondary" disabled={busy} onClick={() => setConfirm('reset')}>
                        Réinitialiser le mot de passe
                    </button>
                )}
            </div>
            <ConfirmDialog
                open={confirm === 'deactivate'}
                onOpenChange={(open) => !open && setConfirm(null)}
                danger
                Icon={Ban}
                title="Désactiver le compte"
                question={`Désactiver le compte de ${user.name} ? Son historique est conservé.`}
                confirmLabel="Désactiver"
                onConfirm={() => post(urls.deactivate)}
                processing={busy}
            />
            <ConfirmDialog
                open={confirm === 'reset'}
                onOpenChange={(open) => !open && setConfirm(null)}
                Icon={KeyRound}
                title="Réinitialiser le mot de passe"
                question={`Générer un nouveau mot de passe temporaire pour ${user.name} ?`}
                confirmLabel="Générer"
                onConfirm={() => post(urls.resetPassword)}
                processing={busy}
            />
        </FormSection>
    );
}

/** Bloc « Changer de département » : l'ancien compte est désactivé et un nouveau compte créé. */
function ChangeDepartment({ departments, urls }) {
    const form = useForm({ department_id: '', username: '', job_title: '' });
    const [confirming, setConfirming] = useState(false);

    const send = () => form.post(urls.changeDepartment, { onFinish: () => setConfirming(false) });

    return (
        <FormSection title="Changer de département" Icon={Building2} defaultOpen={false} hasErrors={Boolean(form.errors.department_id || form.errors.username || form.errors.job_title)}>
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    setConfirming(true);
                }}
            >
                <p className="text-xs font-medium text-slate-600 dark:text-slate-400">NB : l’ancien compte est désactivé (historique conservé) et un nouveau compte est créé.</p>
                <div className="grid gap-5 sm:grid-cols-2">
                    <SelectField name="department_id" label="Nouveau département" required placeholder="Sélectionner" options={departments} value={form.data.department_id} onChange={(value) => form.setData('department_id', value)} error={form.errors.department_id} />
                    <Field name="new_username" label="Nouveau nom d’utilisateur" required autoComplete="off" value={form.data.username} onChange={(value) => form.setData('username', value)} error={form.errors.username} />
                </div>
                <Field name="new_job_title" label="Nouvelle fonction" value={form.data.job_title} onChange={(value) => form.setData('job_title', value)} error={form.errors.job_title} />
                <button type="submit" className="btn-secondary" disabled={form.processing}>
                    Créer le nouveau compte
                </button>
            </form>
            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                Icon={Building2}
                title="Changer de département"
                question="L’ancien compte sera désactivé et un nouveau compte créé. Continuer ?"
                confirmLabel="Créer le nouveau compte"
                onConfirm={send}
                processing={form.processing}
            />
        </FormSection>
    );
}

/**
 * Modifier un utilisateur (React), réservé à l'administrateur : identité,
 * fonction, rôle et coordonnées ; actions sur le compte, changement de
 * département et journal du compte. Règles côté Laravel (UpdateUserRequest,
 * ChangeUserDepartmentRequest, UserPolicy).
 */
export default function Edit(props) {
    // Une page neuve par compte : après un changement de département, le nouveau
    // compte ne reprend ni la saisie ni les sections ouvertes de l'ancien.
    return <EditAccount key={props.user.id} {...props} />;
}

function EditAccount({ user, roles, departments, auditLogs, can, urls }) {
    const form = useForm({
        last_name: user.lastName ?? '',
        first_name: user.firstName ?? '',
        job_title: user.jobTitle ?? '',
        role_id: String(user.roleId ?? ''),
        phone: user.phone ?? '',
        email: user.email ?? '',
    });
    const { data, errors } = form;
    const set = (name) => (value) => form.setData(name, value);

    return (
        <>
            <Head title="Modifier un utilisateur" />

            <FormPage
                title={user.name}
                subtitle={`${user.username} · ${user.department ?? ''}`}
                cancelUrl={urls.cancel}
                processing={form.processing}
                hasErrors={form.hasErrors}
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(urls.update, { preserveScroll: true });
                }}
                before={<TemporaryPasswordBanner />}
                after={
                    <>
                        <AccountActions user={user} can={can} urls={urls} />
                        {can.changeDepartment && <ChangeDepartment departments={departments} urls={urls} />}
                        <FormSection title="Journal du compte" Icon={Clock} defaultOpen={false}>
                            <ul className="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                                {auditLogs.map((log) => (
                                    <li key={log.id} className="flex flex-wrap justify-between gap-2 py-2">
                                        <span>
                                            {log.action}
                                            {log.comment && ` — ${log.comment}`}
                                        </span>
                                        <span className="text-slate-500 dark:text-slate-400">
                                            {log.actor} · {log.date}
                                        </span>
                                    </li>
                                ))}
                                {auditLogs.length === 0 && <li className="py-2 text-slate-500">Aucune action enregistrée.</li>}
                            </ul>
                        </FormSection>
                    </>
                }
            >
                <div className="flex flex-wrap items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                    {user.active ? <StatusBadge color="green">Actif</StatusBadge> : <StatusBadge color="red">Inactif</StatusBadge>}
                    {user.previousUsername && (
                        <span>
                            Ce compte remplace l’ancien compte <strong>{user.previousUsername}</strong> (changement de département).
                        </span>
                    )}
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <Field name="last_name" label="Nom" required value={data.last_name} onChange={set('last_name')} error={errors.last_name} />
                    <Field name="first_name" label="Prénom" required value={data.first_name} onChange={set('first_name')} error={errors.first_name} />
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <Field name="job_title" label="Fonction" required value={data.job_title} onChange={set('job_title')} error={errors.job_title} />
                    <SelectField name="role_id" label="Rôle" required options={roles} value={data.role_id} onChange={set('role_id')} error={errors.role_id} />
                </div>

                <FormSection title="Coordonnées" Icon={Mail} hasErrors={Boolean(errors.phone || errors.email)}>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field name="phone" label="Téléphone" value={data.phone} onChange={set('phone')} error={errors.phone} />
                        <Field name="email" label="E-mail interne" type="email" value={data.email} onChange={set('email')} error={errors.email} />
                    </div>
                </FormSection>
            </FormPage>
        </>
    );
}
