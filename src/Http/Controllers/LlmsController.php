<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use STS\Docent\DocentManager;

final class LlmsController
{
    public function index(Request $request, DocentManager $docent): Response
    {
        return $this->response($docent->llmsText($docent->contextFor($request)));
    }

    public function full(Request $request, DocentManager $docent): Response
    {
        return $this->response($docent->llmsFullText($docent->contextFor($request)));
    }

    private function response(string $content): Response
    {
        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
