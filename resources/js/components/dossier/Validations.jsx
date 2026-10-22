import { useForm } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import SectionTitle from '@/components/app/SectionTitle';
import StatusBadge from '@/components/app/StatusBadge';
import TextareaField from '@/components/form/TextareaField';

/** Décision du responsable : Valider ou Refuser, avec commentaire facultatif. */
function Decide({ validation }) {
    const form = useForm({ comment: '' });

    const decide = (decision) => {
        form.transform((data) => ({ ...data, decision }));
        form.post(validation.decideUrl, { preserveScroll: true });
    };

    return (
        <form className="mt-4 space-y-3" onSubmit={(event) => event.preventDefault()}>
            <TextareaField id={`decision-comment-${validation.id}`} name="comment" label="Commentaire (facultatif)" rows={3} value={form.data.comment} onChange={(value) => form.setData('comment', value)} error={form.errors.comment ?? form.errors.decision} />
            <div className="flex gap-2">
                <button type="button" className="btn-success" disabled={form.processing} onClick={() => decide('approve')}>
                    Valider
                </button>
                <button type="button" className="btn-danger" disabled={form.processing} onClick={() => decide('reject')}>
                    Refuser
                </button>
            </div>
        </form>
    );
}

/** Validations hiérarchiques du dossier. */
export default function Validations({ validations }) {
    if (validations.length === 0) {
        return null;
    }

    return (
        <section className="card" aria-labelledby="validations-title">
            <SectionTitle id="validations-title" Icon={CircleCheck} count={validations.length}>
                Validations hiérarchiques
            </SectionTitle>

            <ol className="mt-4 space-y-4">
                {validations.map((validation) => (
                    <li key={validation.id} className="rounded-lg border border-univ-line p-4 dark:border-slate-800">
                        <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span>
                                <span className="font-semibold">{validation.requester}</span> → {validation.validator}
                            </span>
                            <StatusBadge color={validation.status.color}>{validation.status.label}</StatusBadge>
                        </div>
                        <div className="mt-1 text-xs text-slate-500 dark:text-slate-400">Transmis le {validation.createdAt}</div>
                        {validation.requestComment && (
                            <div className="mt-2 text-sm">
                                <span className="font-medium">Commentaire de l’employé :</span> {validation.requestComment}
                            </div>
                        )}
                        {validation.decidedAt && (
                            <div className="mt-2 text-sm">
                                <span className="font-medium">Décision du {validation.decidedAt}</span>
                                {validation.decisionComment && ` : ${validation.decisionComment}`}
                            </div>
                        )}
                        {validation.canDecide && <Decide validation={validation} />}
                    </li>
                ))}
            </ol>
        </section>
    );
}
