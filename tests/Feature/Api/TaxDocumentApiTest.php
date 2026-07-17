<?php

namespace Tests\Feature\Api;

use App\Models\TaxDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaxDocumentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected()
    {
        $this->getJson(route('api.tax-documents.index'))->assertUnauthorized();
    }

    public function test_a_token_without_the_read_ability_cannot_list_documents()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['tax-documents:write']);

        $this->getJson(route('api.tax-documents.index'))->assertForbidden();
    }

    public function test_a_token_with_the_read_ability_can_list_only_its_owners_documents()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        TaxDocument::factory()->for($user)->create(['title' => 'Mine']);
        TaxDocument::factory()->for($other)->create(['title' => 'Not mine']);

        Sanctum::actingAs($user, ['tax-documents:read']);

        $response = $this->getJson(route('api.tax-documents.index'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Mine');
    }

    public function test_a_token_with_the_write_ability_can_create_a_document()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['tax-documents:write']);

        $response = $this->postJson(route('api.tax-documents.store'), [
            'type' => 'w2',
            'title' => 'W-2 via API',
            'file' => UploadedFile::fake()->create('w2.pdf', 50, 'application/pdf'),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tax_documents', ['title' => 'W-2 via API', 'user_id' => $user->id]);
    }

    public function test_a_token_cannot_create_a_document_for_another_user()
    {
        $client = User::factory()->create();
        $stranger = User::factory()->create(['role' => 'preparer']);

        Sanctum::actingAs($stranger, ['tax-documents:write']);

        $response = $this->postJson(route('api.tax-documents.store'), [
            'type' => 'w2',
            'title' => 'Not allowed',
            'user_id' => $client->id,
            'file' => UploadedFile::fake()->create('w2.pdf', 50, 'application/pdf'),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('user_id');
    }

    public function test_a_token_can_only_manage_its_owners_documents()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $document = TaxDocument::factory()->for($owner)->create();

        Sanctum::actingAs($intruder, ['tax-documents:read', 'tax-documents:write']);

        $this->getJson(route('api.tax-documents.show', $document))->assertForbidden();
        $this->deleteJson(route('api.tax-documents.destroy', $document))->assertForbidden();
    }

    public function test_downloading_a_file_requires_the_read_ability()
    {
        Storage::fake('local');
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['tax-documents:write']);
        $store = $this->postJson(route('api.tax-documents.store'), [
            'type' => 'w2',
            'title' => 'W-2',
            'file' => UploadedFile::fake()->create('w2.pdf', 50, 'application/pdf'),
        ]);
        $documentId = $store->json('data.id');

        Sanctum::actingAs($user, ['tax-documents:write']);
        $this->getJson(route('api.tax-documents.download', $documentId))->assertForbidden();

        Sanctum::actingAs($user, ['tax-documents:read']);
        $this->getJson(route('api.tax-documents.download', $documentId))->assertOk();
    }

    public function test_revealing_the_ssn_requires_the_dedicated_ability_and_is_logged()
    {
        $user = User::factory()->create();
        $document = TaxDocument::factory()->identification()->for($user)->create();

        Sanctum::actingAs($user, ['tax-documents:read']);
        $this->postJson(route('api.tax-documents.reveal-ssn', $document))->assertForbidden();

        Sanctum::actingAs($user, ['tax-documents:read', 'tax-documents:reveal-ssn']);
        $response = $this->postJson(route('api.tax-documents.reveal-ssn', $document));

        $response->assertOk();
        $response->assertJson(['ssn_itin' => '123-45-6789']);

        $this->assertDatabaseHas('tax_document_reveals', [
            'tax_document_id' => $document->id,
            'revealed_by_id' => $user->id,
        ]);
    }

    public function test_the_response_never_includes_the_raw_ssn()
    {
        $user = User::factory()->create();
        TaxDocument::factory()->identification()->for($user)->create();

        Sanctum::actingAs($user, ['tax-documents:read']);

        $this->getJson(route('api.tax-documents.index'))
            ->assertOk()
            ->assertDontSee('123-45-6789')
            ->assertJsonFragment(['ssn_itin_masked' => '***-**-6789']);
    }
}
