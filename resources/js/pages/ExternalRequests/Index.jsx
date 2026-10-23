import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DossierFilters from '@/components/app/DossierFilters';
import DossierTable from '@/components/app/DossierTable';
import PageHeader from '@/components/app/PageHeader';

/**
 * Demandes externes (React) : toutes les demandes extérieures enregistrées
 * par le RH, avec recherche instantanée (référence, objet, demandeur ou
 * organisme) et filtre de statut. Accès réservé à la permission
 * manage_external_requests (ExternalRequestController@index).
 */
export default function Index({ filters, statuses, dossiers, can, urls }) {
    return (
        <>
            <Head title="Demandes externes" />

            <PageHeader
                title="Demandes externes"
                subtitle="Toutes les demandes extérieures, enregistrées par le RH."
                actions={
                    can.create && (
                        <a href={urls.create} className="btn-primary">
                            <Plus className="size-4" aria-hidden="true" /> Nouvelle demande externe
                        </a>
                    )
                }
            />

            <DossierFilters url={urls.index} filters={filters} statuses={statuses} />

            <DossierTable dossiers={dossiers} />
        </>
    );
}
