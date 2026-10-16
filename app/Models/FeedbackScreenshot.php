<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Binary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Screenshot of an entry, stored as BLOB in its own table so that list and
 * detail queries never load binary data.
 *
 * @property string $entry_id
 * @property string $mime
 * @property int $width
 * @property int $height
 * @property int $bytes
 * @property string $sha256
 * @property string|null $data
 * @property-read FeedbackEntry $entry
 */
class FeedbackScreenshot extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'feedback_screenshots';

    protected $primaryKey = 'entry_id';

    protected $keyType = 'string';

    protected $fillable = ['entry_id', 'mime', 'width', 'height', 'bytes', 'sha256', 'data'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
            'data' => Binary::class,
        ];
    }

    /** @return BelongsTo<FeedbackEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(FeedbackEntry::class, 'entry_id');
    }

    public function extension(): string
    {
        return match ($this->mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
