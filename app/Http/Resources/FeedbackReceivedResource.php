<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FeedbackEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeedbackEntry */
final class FeedbackReceivedResource extends JsonResource
{
    public static $wrap = null;

    /** @return array{id: string, status: string} */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'status' => 'received'];
    }
}
