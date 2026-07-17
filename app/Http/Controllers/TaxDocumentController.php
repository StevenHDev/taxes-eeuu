<?php

namespace App\Http\Controllers;

use App\Enums\TaxDocumentType;
use App\Http\Concerns\ManagesTaxDocuments;
use App\Http\Requests\TaxDocumentRequest;
use App\Models\TaxDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxDocumentController extends Controller
{
    use ManagesTaxDocuments;

    /**
     * Display a listing of the tax documents visible to the current user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $documents = TaxDocument::query()
            ->visibleTo($user)
            ->with(['user:id,name'])
            ->when($request->string('type')->isNotEmpty(), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->integer('fiscal_year'), fn ($query, $year) => $query->where('fiscal_year', $year))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('tax-documents/index', [
            'documents' => $documents,
            'types' => TaxDocumentType::options(),
            'clients' => $user->role === 'preparer' ? $user->clients()->select('id', 'name')->get() : [],
            'filters' => $request->only(['type', 'fiscal_year']),
        ]);
    }

    /**
     * Show the form for creating a new tax document.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', TaxDocument::class);

        $user = $request->user();

        return Inertia::render('tax-documents/create', [
            'types' => TaxDocumentType::options(),
            'clients' => $user->role === 'preparer' ? $user->clients()->select('id', 'name')->get() : [],
        ]);
    }

    /**
     * Store a newly created tax document.
     */
    public function store(TaxDocumentRequest $request): RedirectResponse
    {
        $this->authorize('create', TaxDocument::class);

        $userId = $this->resolveTargetUserId($request);

        $attributes = $request->safe()->except(['file', 'user_id']);

        if ($request->hasFile('file')) {
            $attributes = array_merge($attributes, $this->storeUploadedFile($request->file('file'), $userId));
        }

        TaxDocument::create([
            ...$attributes,
            'user_id' => $userId,
            'uploaded_by_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documento guardado.')]);

        return to_route('tax-documents.index');
    }

    /**
     * Show the form for editing a tax document.
     */
    public function edit(Request $request, TaxDocument $taxDocument): Response
    {
        $this->authorize('update', $taxDocument);

        $user = $request->user();

        return Inertia::render('tax-documents/edit', [
            'document' => $taxDocument,
            'types' => TaxDocumentType::options(),
            'clients' => $user->role === 'preparer' ? $user->clients()->select('id', 'name')->get() : [],
        ]);
    }

    /**
     * Update a tax document.
     */
    public function update(TaxDocumentRequest $request, TaxDocument $taxDocument): RedirectResponse
    {
        $this->authorize('update', $taxDocument);

        $userId = $this->resolveTargetUserId($request);

        $attributes = $request->safe()->except(['file', 'user_id']);

        // The SSN/ITIN field is write-only (masked in the UI): a blank submission means
        // "keep the existing encrypted value", never overwrite it with null.
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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documento actualizado.')]);

        return to_route('tax-documents.index');
    }

    /**
     * Remove a tax document.
     */
    public function destroy(TaxDocument $taxDocument): RedirectResponse
    {
        $this->authorize('delete', $taxDocument);

        $this->deleteStoredFile($taxDocument);
        $taxDocument->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documento eliminado.')]);

        return to_route('tax-documents.index');
    }

    /**
     * Download the file attached to a tax document.
     */
    public function download(TaxDocument $taxDocument): StreamedResponse
    {
        $this->authorize('view', $taxDocument);

        abort_unless($taxDocument->file_path !== null, 404);

        return Storage::disk('local')->download($taxDocument->file_path, $taxDocument->file_original_name);
    }

    /**
     * Reveal the decrypted SSN/ITIN for a tax document (requires password confirmation via middleware).
     */
    public function revealSsn(Request $request, TaxDocument $taxDocument): JsonResponse
    {
        $this->authorize('view', $taxDocument);

        $value = $this->decryptAndLogSsnReveal($taxDocument, $request->user(), $request);

        return response()->json(['ssn_itin' => $value])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
