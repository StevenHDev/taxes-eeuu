<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ApiDocumentationController extends Controller
{
    /**
     * Show the rendered API documentation (docs/api.md).
     */
    public function index(): Response
    {
        $markdown = File::get(base_path('docs/api.md'));

        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return Inertia::render('api-docs', [
            'html' => $html,
        ]);
    }
}
