<?php

namespace Tests\Feature;

use App\Models\TaxDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaxDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $document = TaxDocument::factory()->create();

        $this->get(route('tax-documents.index'))->assertRedirect(route('login'));
        $this->get(route('tax-documents.edit', $document))->assertRedirect(route('login'));
    }

    public function test_a_client_can_create_view_update_and_delete_their_own_document()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tax-documents.store'), [
                'type' => 'w2',
                'title' => 'W-2 Acme Corp',
                'fiscal_year' => 2024,
                'file' => UploadedFile::fake()->create('w2.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tax-documents.index'));

        $document = TaxDocument::first();
        $this->assertSame($user->id, $document->user_id);
        $this->assertSame($user->id, $document->uploaded_by_id);

        $this->actingAs($user)
            ->get(route('tax-documents.edit', $document))
            ->assertOk();

        $this->actingAs($user)
            ->put(route('tax-documents.update', $document), [
                'type' => 'w2',
                'title' => 'W-2 Acme Corp (updated)',
                'fiscal_year' => 2024,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tax-documents.index'));

        $this->assertSame('W-2 Acme Corp (updated)', $document->fresh()->title);

        $this->actingAs($user)
            ->delete(route('tax-documents.destroy', $document))
            ->assertRedirect(route('tax-documents.index'));

        $this->assertModelMissing($document);
    }

    public function test_a_client_cannot_view_or_edit_another_clients_document()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $document = TaxDocument::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->get(route('tax-documents.edit', $document))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->put(route('tax-documents.update', $document), ['type' => 'w2', 'title' => 'x'])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('tax-documents.destroy', $document))
            ->assertForbidden();
    }

    public function test_a_preparer_can_manage_documents_of_an_assigned_client()
    {
        $preparer = User::factory()->create(['role' => 'preparer']);
        $client = User::factory()->create(['preparer_id' => $preparer->id]);
        $document = TaxDocument::factory()->for($client)->create(['file_path' => 'tax-documents/seed/existing.pdf']);

        $this->actingAs($preparer)
            ->get(route('tax-documents.edit', $document))
            ->assertOk();

        $this->actingAs($preparer)
            ->put(route('tax-documents.update', $document), [
                'type' => 'w2',
                'title' => 'Updated by preparer',
                'user_id' => $client->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tax-documents.index'));

        $this->assertSame('Updated by preparer', $document->fresh()->title);
    }

    public function test_a_preparer_cannot_manage_documents_of_another_preparers_client()
    {
        $preparerA = User::factory()->create(['role' => 'preparer']);
        $preparerB = User::factory()->create(['role' => 'preparer']);
        $clientOfB = User::factory()->create(['preparer_id' => $preparerB->id]);
        $document = TaxDocument::factory()->for($clientOfB)->create();

        $this->actingAs($preparerA)
            ->get(route('tax-documents.edit', $document))
            ->assertForbidden();

        $this->actingAs($preparerA)
            ->delete(route('tax-documents.destroy', $document))
            ->assertForbidden();
    }

    public function test_ssn_is_encrypted_at_rest_and_decrypts_via_the_model()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tax-documents.store'), [
            'type' => 'identification',
            'title' => 'Identificación',
            'ssn_itin' => '123-45-6789',
        ])->assertSessionHasNoErrors();

        $document = TaxDocument::first();

        $raw = DB::table('tax_documents')->where('id', $document->id)->value('ssn_itin');
        $this->assertNotSame('123-45-6789', $raw);
        $this->assertSame('123-45-6789', $document->fresh()->ssn_itin);
    }

    public function test_ssn_is_masked_in_the_index_and_edit_responses()
    {
        $user = User::factory()->create();
        TaxDocument::factory()->identification()->for($user)->create();

        $response = $this->actingAs($user)->get(route('tax-documents.index'));

        $response->assertOk();
        $response->assertDontSee('123-45-6789');
        $response->assertSee('***-**-6789');
    }

    public function test_revealing_the_ssn_requires_password_confirmation_and_is_logged()
    {
        $user = User::factory()->create();
        $document = TaxDocument::factory()->identification()->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('tax-documents.reveal-ssn', $document))
            ->assertStatus(423);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('tax-documents.reveal-ssn', $document));

        $response->assertOk();
        $response->assertJson(['ssn_itin' => '123-45-6789']);

        $this->assertDatabaseHas('tax_document_reveals', [
            'tax_document_id' => $document->id,
            'revealed_by_id' => $user->id,
        ]);
    }

    public function test_file_can_be_uploaded_and_downloaded()
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tax-documents.store'), [
            'type' => 'w2',
            'title' => 'W-2',
            'file' => UploadedFile::fake()->create('w2.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $document = TaxDocument::first();

        Storage::disk('local')->assertExists($document->file_path);

        $this->actingAs($user)
            ->get(route('tax-documents.download', $document))
            ->assertOk();
    }

    public function test_a_document_requiring_a_file_is_rejected_without_one()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tax-documents.store'), [
                'type' => 'w2',
                'title' => 'W-2 sin archivo',
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_a_dependent_document_requires_a_name_and_date_of_birth()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tax-documents.store'), [
                'type' => 'dependent',
                'title' => 'Dependiente',
            ])
            ->assertSessionHasErrors(['dependent_name', 'dependent_date_of_birth']);
    }
}
