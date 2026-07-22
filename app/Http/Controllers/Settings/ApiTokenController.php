<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ApiAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ApiTokenStoreRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    /**
     * Show the user's API tokens settings page.
     */
    public function index(Request $request): Response
    {
        $tokens = $request->user()->tokens()
            ->latest()
            ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']);

        return Inertia::render('settings/api-tokens', [
            'tokens' => $tokens,
            'abilities' => ApiAbility::options(),
        ]);
    }

    /**
     * Create a new API token for the user.
     */
    public function store(ApiTokenStoreRequest $request): RedirectResponse
    {
        $token = $request->user()->createToken(
            $request->string('name')->value(),
            $request->array('abilities'),
        );

        Inertia::flash('apiToken', $token->plainTextToken);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token creado. Cópialo ahora, no volverá a mostrarse.')]);

        return to_route('api-tokens.index');
    }

    /**
     * Revoke one of the user's API tokens.
     */
    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->where('id', $token)->firstOrFail()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revocado.')]);

        return to_route('api-tokens.index');
    }
}
