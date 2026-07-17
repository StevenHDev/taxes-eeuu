<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaxDocumentAbility;
use App\Http\Concerns\ManagesTaxDocuments;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaxDocumentRequest;
use App\Http\Resources\TaxDocumentResource;
use App\Models\TaxDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxDocumentController extends Controller
{
    use ManagesTaxDocuments;

    /**
     * List the tax documents visible to the authenticated token's user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureAbility($request, TaxDocumentAbility::Read);

        $user = $request->user();

        $documents = TaxDocument::query()
            ->visibleTo($user)
            ->when($request->string('type')->isNotEmpty(), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->integer('fiscal_year'), fn ($query, $year) => $query->where('fiscal_year', $year))
            ->latest()
            ->paginate(15);

        return TaxDocumentResource::collection($documents);
    }

    /**
     * Create a new tax document.
     */
    public function store(TaxDocumentRequest $request): JsonResponse
    {
        $userId = $this->resolveTargetUserId($request);

        $attributes = $request->safe()->except(['file', 'user_id']);

        if ($request->hasFile('file')) {
            $attributes = array_merge($attributes, $this->storeUploadedFile($request->file('file'), $userId));
        }

        $document = TaxDocument::create([
            ...$attributes,
            'user_id' => $userId,
            'uploaded_by_id' => $request->user()->id,
        ]);

        return TaxDocumentResource::make($document)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single tax document.
     */
    public function show(Request $request, TaxDocument $taxDocument): TaxDocumentResource
    {
        $this->ensureAbility($request, TaxDocumentAbility::Read);
        $this->authorize('view', $taxDocument);

        return TaxDocumentResource::make($taxDocument);
    }

    /**
     * Update a tax document.
     */
    public function update(TaxDocumentRequest $request, TaxDocument $taxDocument): TaxDocumentResource
    {
        $userId = $this->resolveTargetUserId($request);

        $attributes = $request->safe()->except(['file', 'user_id']);

        if (empty($attributes['ssn_itin'])) {
            unset($attributes['ssn_itin']);
        }

        if ($request->hasFile('file')) {
            $this->deleteStoredFile($taxDocument);
            $attributes = array_merge($attributes, $this->storeUploadedFile($request->file('file'), $userId));
        }

        $taxDocument->update([
            ...$attributes,
            'user_id' => $userId,
        ]);

        return TaxDocumentResource::make($taxDocument);
    }

    /**
     * Delete a tax document.
     */
    public function destroy(Request $request, TaxDocument $taxDocument): JsonResponse
    {
        $this->ensureAbility($request, TaxDocumentAbility::Write);
        $this->authorize('delete', $taxDocument);

        $this->deleteStoredFile($taxDocument);
        $taxDocument->delete();

        return response()->json(status: 204);
    }

    /**
     * Download the file attached to a tax document.
     */
    public function download(Request $request, TaxDocument $taxDocument): StreamedResponse
    {
        $this->ensureAbility($request, TaxDocumentAbility::Read);
        $this->authorize('view', $taxDocument);

        abort_unless($taxDocument->file_path !== null, 404);

        return Storage::disk('local')->download($taxDocument->file_path, $taxDocument->file_original_name);
    }

    /**
     * Reveal the decrypted SSN/ITIN for a tax document. Requires the token to have been
     * explicitly created with the "tax-documents:reveal-ssn" ability (there is no session
     * "confirm password" step to fall back on for a stateless API request).
     */
    public function revealSsn(Request $request, TaxDocument $taxDocument): JsonResponse
    {
        $this->ensureAbility($request, TaxDocumentAbility::RevealSsn);
        $this->authorize('view', $taxDocument);

        $value = $this->decryptAndLogSsnReveal($taxDocument, $request->user(), $request);

        return response()->json(['ssn_itin' => $value])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    protected function ensureAbility(Request $request, TaxDocumentAbility $ability): void
    {
        abort_unless($request->user()->tokenCan($ability->value), 403, "Token missing required ability: {$ability->value}");
    }
}
