<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Dossier;
use App\Models\DossierActivityLog;
use App\Models\Response;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * Export A4 de l'historique d'un dossier (PDF et Word) : en-tête du
 * dossier, puis une ligne par action (date, heure, utilisateur, action,
 * commentaire).
 */
class HistoryExporter
{
    /**
     * Entrées de l'historique telles qu'un utilisateur peut les lire : l'action
     * reste visible, mais le détail et le commentaire d'une réponse (ou d'une
     * pièce de réponse) qu'il n'a pas le droit de lire sont masqués. La base
     * conserve toujours la version complète.
     *
     * @return Collection<int, array<string, string>>
     */
    public function rowsFor(Dossier $dossier, User $viewer): Collection
    {
        return $dossier->activityLogs()->with(['user', 'subject'])->orderBy('id')->get()
            ->map(fn (DossierActivityLog $log) => $this->row($log, $viewer));
    }

    /**
     * @return array<string, string>
     */
    public function row(DossierActivityLog $log, User $viewer): array
    {
        $masked = ! $this->isReadableBy($log, $viewer);

        return [
            'date' => $log->created_at->format('d/m/Y'),
            'time' => $log->created_at->format('H:i'),
            'user' => $log->user?->name ?? 'Système',
            'action' => $log->description ?? $log->action,
            'details' => $masked ? 'Contenu réservé aux personnes concernées' : $this->details($log),
            'comment' => $masked ? '' : (string) $log->comment,
        ];
    }

    /**
     * @return array{header: array<string, string>, rows: Collection<int, array<string, string>>}
     */
    public function data(Dossier $dossier, User $viewer): array
    {
        $dossier->loadMissing(['requestType', 'creator.department', 'originatingDepartment', 'externalRequester', 'recipients.assignedUser', 'recipients.department']);

        $header = [
            'Référence' => $dossier->reference,
            'Objet' => $dossier->subject,
            'Type' => $dossier->requestType->name,
            'Expéditeur' => $dossier->isExternal()
                ? trim(($dossier->externalRequester?->full_name ?? '').' '.($dossier->externalRequester?->organization ? '('.$dossier->externalRequester->organization.')' : '')).' — enregistré par '.$dossier->creator->name
                : $dossier->creator->name,
            'Destinataires' => $dossier->recipients->map(fn ($recipient) => $recipient->assignedUser->name.' ('.$recipient->status->label().')')->implode(', '),
            'Département' => $dossier->originatingDepartment?->name
                ?? $dossier->recipients->pluck('department.name')->unique()->implode(', '),
            'Date création' => $dossier->created_at->format('d/m/Y H:i'),
            'Statut' => $dossier->status->label(),
        ];

        return ['header' => $header, 'rows' => $this->rowsFor($dossier, $viewer)];
    }

    public function pdf(Dossier $dossier, User $viewer): HttpResponse
    {
        return Pdf::loadView('exports.history', [...$this->data($dossier, $viewer), 'generatedAt' => now()])
            ->setPaper('a4')
            ->download($this->filename($dossier, 'pdf'));
    }

    /**
     * Génère le fichier .docx dans un fichier temporaire et renvoie son chemin.
     */
    public function word(Dossier $dossier, User $viewer): string
    {
        ['header' => $header, 'rows' => $rows] = $this->data($dossier, $viewer);

        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(9);

        $section = $word->addSection([
            'pageSizeW' => Converter::cmToTwip(21),
            'pageSizeH' => Converter::cmToTwip(29.7),
            'marginTop' => Converter::cmToTwip(1.5),
            'marginBottom' => Converter::cmToTwip(1.5),
            'marginLeft' => Converter::cmToTwip(1.5),
            'marginRight' => Converter::cmToTwip(1.5),
        ]);

        $section->addText('Historique du dossier '.$header['Référence'], ['bold' => true, 'size' => 14]);
        $section->addText('SOGEDIPROMA — édité le '.now()->format('d/m/Y à H:i'), ['size' => 8, 'color' => '666666']);
        $section->addTextBreak();

        $headerTable = $section->addTable(['borderSize' => 4, 'borderColor' => 'BBBBBB', 'cellMargin' => 60]);
        foreach ($header as $label => $value) {
            $headerTable->addRow();
            $headerTable->addCell(Converter::cmToTwip(4))->addText($label, ['bold' => true]);
            $headerTable->addCell(Converter::cmToTwip(14))->addText((string) $value);
        }

        $section->addTextBreak();

        $logTable = $section->addTable(['borderSize' => 4, 'borderColor' => 'BBBBBB', 'cellMargin' => 50]);
        $logTable->addRow(null, ['tblHeader' => true]);
        foreach (['Date' => 2.2, 'Heure' => 1.4, 'Utilisateur' => 3.4, 'Action' => 5, 'Commentaire' => 6] as $title => $width) {
            $logTable->addCell(Converter::cmToTwip($width), ['bgColor' => 'E8EEF8'])->addText($title, ['bold' => true]);
        }

        foreach ($rows as $row) {
            $logTable->addRow();
            $logTable->addCell(Converter::cmToTwip(2.2))->addText($row['date']);
            $logTable->addCell(Converter::cmToTwip(1.4))->addText($row['time']);
            $logTable->addCell(Converter::cmToTwip(3.4))->addText($row['user']);
            $actionCell = $logTable->addCell(Converter::cmToTwip(5));
            $actionCell->addText($row['action'], ['bold' => true]);
            if ($row['details'] !== '') {
                $actionCell->addText($row['details'], ['size' => 8, 'color' => '555555']);
            }
            $logTable->addCell(Converter::cmToTwip(6))->addText($row['comment']);
        }

        $path = tempnam(sys_get_temp_dir(), 'historique-').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($path);

        return $path;
    }

    public function filename(Dossier $dossier, string $extension): string
    {
        return 'historique-'.$dossier->reference.'.'.$extension;
    }

    /**
     * « champ : ancienne → nouvelle » pour chaque valeur modifiée.
     */
    public function details(DossierActivityLog $log): string
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];

        return collect(array_unique([...array_keys($old), ...array_keys($new)]))
            ->map(function (string $key) use ($old, $new) {
                $before = $this->stringify($old[$key] ?? null);
                $after = $this->stringify($new[$key] ?? null);

                return $before !== '' ? "{$key} : {$before} → {$after}" : "{$key} : {$after}";
            })
            ->implode(' ; ');
    }

    private function isReadableBy(DossierActivityLog $log, User $viewer): bool
    {
        $subject = $log->subject;

        return match (true) {
            $subject instanceof Response => $viewer->can('view', $subject),
            $subject instanceof Document && $subject->response_id !== null => $viewer->can('view', $subject->response),
            default => true,
        };
    }

    private function stringify(mixed $value): string
    {
        return is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
