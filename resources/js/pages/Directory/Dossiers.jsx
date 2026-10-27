import { Head } from '@inertiajs/react';
import DossierTable from '@/components/app/DossierTable';
import PageHeader from '@/components/app/PageHeader';

/**
 * Dossiers envoyés à un département (React) : uniquement ceux que
 * l'utilisateur connecté a lui-même transmis (DirectoryController@dossiers).
 */
export default function Dossiers({ department, dossiers, urls }) {
    return (
        <>
            <Head title={`Dossiers envoyés — ${department.name}`} />

            <div className="list-page">
            <PageHeader
                title={`Dossiers envoyés à ${department.name}`}
                subtitle="Tous les dossiers que vous avez transmis à ce département."
                actions={
                    <a href={urls.back} className="btn-secondary">
                        Retour aux départements
                    </a>
                }
            />

            <DossierTable dossiers={dossiers} />
            </div>
        </>
    );
}
