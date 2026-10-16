<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FeedbackEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $id
 * @property string $project_id
 * @property string $client_request_id
 * @property string $source
 * @property string|null $name
 * @property string $email
 * @property string $message
 * @property string $locale
 * @property string|null $identity_user_id
 * @property string $page_url
 * @property string|null $page_title
 * @property string|null $referrer
 * @property string $origin
 * @property string|null $ip
 * @property string|null $os_name
 * @property string|null $os_version
 * @property string|null $browser_name
 * @property string|null $browser_version
 * @property string|null $device_type
 * @property string $version_precision
 * @property string|null $user_agent
 * @property int|null $viewport_w
 * @property int|null $viewport_h
 * @property int|null $screen_w
 * @property int|null $screen_h
 * @property int|null $page_w
 * @property int|null $page_h
 * @property int|null $scroll_x
 * @property int|null $scroll_y
 * @property string|null $dpr
 * @property int|null $color_depth
 * @property string|null $timezone
 * @property string|null $browser_language
 * @property array<mixed>|null $console_log
 * @property array<mixed>|null $network_log
 * @property array<mixed>|null $annotations
 * @property array<mixed>|null $custom_data
 * @property string|null $screenshot_error
 * @property string|null $submitted_from_url
 * @property string $widget_version
 * @property int|null $elapsed_ms
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FeedbackProject $project
 * @property-read FeedbackScreenshot|null $screenshot
 */
class FeedbackEntry extends Model
{
    /** @use HasFactory<FeedbackEntryFactory> */
    use HasFactory;

    use HasUlids;
    use LogsActivity;

    /**
     * Incoming feedback is the record itself and is not logged. Only later
     * changes (completed, reopened) are.
     *
     * @var list<string>
     */
    protected static array $recordEvents = ['updated'];

    protected $table = 'feedback_entries';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'console_log' => 'array',
            'network_log' => 'array',
            'annotations' => 'array',
            'custom_data' => 'array',
            'completed_at' => 'datetime',
            'viewport_w' => 'integer',
            'viewport_h' => 'integer',
            'screen_w' => 'integer',
            'screen_h' => 'integer',
            'page_w' => 'integer',
            'page_h' => 'integer',
            'scroll_x' => 'integer',
            'scroll_y' => 'integer',
            'color_depth' => 'integer',
            'elapsed_ms' => 'integer',
            'dpr' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Personal data (message, email, name, ip) is never copied into the log.
        return LogOptions::defaults()
            ->useLogName('feedback')
            ->logOnly(['completed_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event): string => match (true) {
                $this->wasChanged('completed_at') => $this->completed_at === null ? 'reopened' : 'completed',
                default => $event,
            });
    }

    /** @return BelongsTo<FeedbackProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(FeedbackProject::class, 'project_id');
    }

    /** @return HasOne<FeedbackScreenshot, $this> */
    public function screenshot(): HasOne
    {
        return $this->hasOne(FeedbackScreenshot::class, 'entry_id');
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /** @param Builder<self> $query */
    public function scopeCompleted(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }
}
