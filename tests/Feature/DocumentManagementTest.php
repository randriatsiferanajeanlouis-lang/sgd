<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Dossier;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DossierNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithDossiers;
use Tests\TestCase;
use ZipArchive;

class DocumentManagementTest extends TestCase
{
    use InteractsWithDossiers, RefreshDatabase;

    protected bool $seed = true;

    private User $sender;

    private User $recipient;

    private Dossier $dossier;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('dossiers');
        $this->sender = $this->makeUser(Role::STAFF, 'PRODUCTION');
        $this->recipient = $this->makeUser(Role::STAFF, 'TECHNIQUE');
        $this->dossier = $this->sendInternalRequest($this->sender, [$this->recipient], [
            'attachments' => [$this->pdf('contrat.pdf'), $this->pdf('annexe.pdf')],
        ]);
    }

    public function test_server_checks_the_real_file_type(): void
    {
        $fakePdf = $this->realUpload('facture.pdf', "#!/bin/sh\nrm -rf /\n");

        $this->actingAs($this->sender)
            ->post(route('documents.store', $this->dossier), ['attachments' => [$fakePdf]])
            ->assertSessionHasErrors('attachments.0');

        $this->assertSame(2, $this->dossier->documents()->count());
    }

    public function test_documents_are_downloaded_only_through_the_protected_controller(): void
    {
        $document = $this->document('contrat.pdf');

        $this->actingAs($this->recipient)->get(route('documents.download', $document))
            ->assertOk()
            ->assertDownload('contrat.pdf');

        $this->actingAs($this->makeUser(Role::STAFF, 'TECHNIQUE'))->get(route('documents.download', $document))->assertForbidden();

        auth()->logout();
        $this->get(route('documents.download', $document))->assertRedirect(route('login'));
    }

    public function test_replacing_keeps_v1_traced_but_no_longer_offered(): void
    {
        $v1 = $this->document('contrat.pdf');

        $this->actingAs($this->sender)
            ->post(route('documents.replace', $v1), ['file' => $this->pdf('contrat-signe.pdf')])
            ->assertRedirect();

        $v2 = Document::query()->where('document_key', $v1->document_key)->where('version', 2)->sole();
        $this->assertSame(DocumentStatus::Replaced, $v1->fresh()->status);
        $this->assertSame(DocumentStatus::Active, $v2->status);
        $this->assertTrue($v2->previousVersion->is($v1));
        Storage::disk('dossiers')->assertExists($v1->path);

        $log = $this->dossier->activityLogs()->where('action', 'document.replaced')->sole();
        $this->assertSame(['document' => 'contrat.pdf', 'version' => 1], $log->old_values);
        $this->assertSame(['document' => 'contrat-signe.pdf', 'version' => 2], $log->new_values);

        $admin = $this->makeUser(Role::ADMIN, 'ADMINISTRATION');
        $this->actingAs($admin)->get(route('documents.download', $v2))->assertOk();
        $this->actingAs($admin)->get(route('documents.download', $v1))->assertForbidden();

        $this->actingAs($this->recipient)->get(route('dossiers.show', $this->dossier))
            ->assertSee('contrat-signe.pdf')
            ->assertSee('Versions (2)')
            ->assertSee('Remplacée');
    }

    public function test_only_the_uploader_or_the_dossier_author_can_replace_or_cancel(): void
    {
        $document = $this->document('contrat.pdf');

        $this->actingAs($this->recipient)->post(route('documents.replace', $document), ['file' => $this->pdf()])->assertForbidden();
        $this->actingAs($this->recipient)->post(route('documents.cancel', $document), ['reason' => 'Non'])->assertForbidden();

        $this->assertSame(DocumentStatus::Active, $document->fresh()->status);
    }

    public function test_cancelled_document_leaves_operations_but_stays_in_history(): void
    {
        $document = $this->document('annexe.pdf');

        $this->actingAs($this->sender)->post(route('documents.cancel', $document), ['reason'])->assertSessionHasErrors('reason');
        $this->post(route('documents.cancel', $document), ['reason' => 'Mauvais fichier joint'])->assertRedirect();

        $document->refresh();
        $this->assertSame(DocumentStatus::Cancelled, $document->status);
        $this->assertSame($this->sender->id, $document->cancelled_by);
        $this->assertSame('Mauvais fichier joint', $this->dossier->activityLogs()->where('action', 'document.cancelled')->sole()->comment);

        $this->actingAs($this->makeUser(Role::ADMIN, 'ADMINISTRATION'))->get(route('documents.download', $document))->assertForbidden();
    }

    public function test_zip_contains_only_active_documents(): void
    {
        $this->actingAs($this->sender)->post(route('documents.replace', $this->document('contrat.pdf')), ['file' => $this->pdf('contrat-v2.pdf')]);
        $this->post(route('documents.cancel', $this->document('annexe.pdf')), ['reason' => 'Doublon']);
        $this->post(route('documents.store', $this->dossier), ['attachments' => [$this->pdf('planning.pdf')]]);

        $response = $this->actingAs($this->recipient)->get(route('documents.zip', $this->dossier));
        $response->assertOk()->assertDownload($this->dossier->reference.'-pieces.zip');

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($index) => $zip->getNameIndex($index))->sort()->values()->all();
        $zip->close();

        $this->assertSame(['contrat-v2.pdf', 'planning.pdf'], $names);
    }

    public function test_participants_add_documents_and_others_are_notified(): void
    {
        Notification::fake();

        $this->actingAs($this->recipient)
            ->post(route('documents.store', $this->dossier), ['attachments' => [$this->pdf('photo-chantier.pdf')]])
            ->assertRedirect();

        $this->assertSame(3, $this->dossier->documents()->count());
        $this->assertTrue($this->dossier->activityLogs()->where('action', 'document.added')->where('user_id', $this->recipient->id)->exists());
        Notification::assertSentTo($this->sender, DossierNotification::class, fn ($n) => $n->type === 'document_added');

        $this->actingAs($this->makeUser(Role::STAFF, 'RSE'))
            ->post(route('documents.store', $this->dossier), ['attachments' => [$this->pdf()]])
            ->assertForbidden();
    }

    private function document(string $name): Document
    {
        return $this->dossier->documents()->where('original_name', $name)->firstOrFail();
    }
}
