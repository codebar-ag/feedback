<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\StoreFeedback;
use App\Http\Middleware\ResolveProject;
use App\Http\Requests\StoreFeedbackRequest;
use App\Http\Resources\FeedbackReceivedResource;
use Illuminate\Http\JsonResponse;

final class StoreFeedbackController
{
    public function __invoke(StoreFeedbackRequest $request, StoreFeedback $storeFeedback): JsonResponse
    {
        $result = $storeFeedback->handle(ResolveProject::project($request), $request->toSubmission(), $request);

        return (new FeedbackReceivedResource($result['entry']))
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }
}
