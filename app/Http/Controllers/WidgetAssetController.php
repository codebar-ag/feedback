<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the widget (copied by the deploy build, see widget/scripts/build.mjs)
 * to any website: CORS for the lazy screenshot chunk, a short cache for
 * feedback.js so deploys roll out, a long cache for the hashed chunks.
 */
final class WidgetAssetController
{
    public function __invoke(Request $request, string $path): Response
    {
        $dir = config('feedback.widget_path');
        $file = is_string($dir) ? rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$path : '';

        if (! is_file($file)) {
            throw new NotFoundHttpException;
        }

        $contents = (string) file_get_contents($file);
        $hashed = str_starts_with($path, 'chunks/');

        $response = new Response($contents, 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => $hashed ? 'public, max-age=31536000, immutable' : 'public, max-age=300',
            'Access-Control-Allow-Origin' => '*',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setEtag(hash('xxh128', $contents));
        $response->isNotModified($request);

        return $response;
    }
}
