<?php

namespace App\Http\Concerns;

use App\Http\Requests\TaxDocumentRequest;
use App\Models\TaxDocument;
use App\Models\TaxDocumentReveal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ManagesTaxDocuments
{
    /**
     * Resolve which user the document belongs to, based on the authenticated user's role.
     * Clients can only ever create documents for themselves — the "user_id" input is
     * never trusted for a client, to prevent horizontal privilege escalation.
     */
    protected function resolveTargetUserId(TaxDocumentRequest $request): int
    {
        $user = $request->user();

        if ($user->role === 'preparer') {
            return (int) $request->validated('user_id');
        }

        return $user->id;
    }

    /**
     * @return array{file_path: string, file_original_name: string, file_mime_type: string, file_size: int}
     */
    protected function storeUploadedFile(UploadedFile $file, int $userId): array
    {
        $path = $file->storeAs(
            "tax-documents/{$userId}",
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            'local',
        );

        throw_if($path === false, new \RuntimeException('Unable to store the uploaded file.'));

        $size = $file->getSize();

        return [
            'file_path' => $path,
            'file_original_name' => $file->getClientOriginalName(),
            'file_mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'file_size' => $size === false ? 0 : $size,
        ];
    }

    protected function deleteStoredFile(TaxDocument $taxDocument): void
    {
        if ($taxDocument->file_path) {
            Storage::disk('local')->delete($taxDocument->file_path);
        }
    }

    /**
     * Decrypt the SSN/ITIN for a one-time reveal, recording who accessed it and from where.
     */
    protected function decryptAndLogSsnReveal(TaxDocument $taxDocument, User $revealedBy, Request $request): ?string
    {
        TaxDocumentReveal::create([
            'tax_document_id' => $taxDocument->id,
            'revealed_by_id' => $revealedBy->id,
            'ip_address' => $request->ip(),
        ]);

        return $taxDocument->ssn_itin;
    }
}
