import { Head } from '@inertiajs/react';
import Documents from '@/components/dossier/Documents';
import History from '@/components/dossier/History';
import HrSection from '@/components/dossier/HrSection';
import MyTreatment from '@/components/dossier/MyTreatment';
import Recipients from '@/components/dossier/Recipients';
import Responses from '@/components/dossier/Responses';
import Summary from '@/components/dossier/Summary';
import Validations from '@/components/dossier/Validations';

/**
 * Fiche d'un dossier (React). Toutes les données et tous les droits viennent
 * de App\Support\DossierPage : chaque bouton n'apparaît que si la politique
 * Laravel l'autorise, et chaque action reste vérifiée côté serveur.
 */
export default function Show({ dossier, can, urls, hr, myTreatment, validations, recipients, responses, documents, history }) {
    return (
        <>
            <Head title={dossier.reference} />

            <Summary dossier={dossier} can={can} urls={urls} />

            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <div className="space-y-6 xl:col-span-2">
                    {hr && <HrSection hr={hr} />}
                    {myTreatment && <MyTreatment treatment={myTreatment} external={dossier.external} />}
                    <Validations validations={validations} />
                    <Recipients recipients={recipients} />
                    <Responses responses={responses} />
                </div>

                <div className="space-y-6">
                    <Documents documents={documents} can={can} urls={urls} />
                </div>
            </div>

            {history && (
                <div className="mt-6">
                    <History days={history} urls={urls} />
                </div>
            )}
        </>
    );
}
