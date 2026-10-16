<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PharData;
use RecursiveIteratorIterator;
use Throwable;

/** Downloads the MaxMind GeoLite2 database (only needed with FEEDBACK_GEO=maxmind). */
final class GeoIpUpdateCommand extends Command
{
    protected $signature = 'geoip:update {--json : Output as JSON}';

    protected $description = 'Download or refresh the local MaxMind GeoLite2 database';

    public function handle(): int
    {
        $account = config('feedback.maxmind.account_id');
        $license = config('feedback.maxmind.license_key');
        $edition = config('feedback.maxmind.edition');
        $url = config('feedback.maxmind.download_url');
        $target = config('feedback.maxmind.database_path');

        if (! is_string($account) || $account === '' || ! is_string($license) || $license === '') {
            return $this->failWith('MAXMIND_ACCOUNT_ID and MAXMIND_LICENSE_KEY must be set.', self::INVALID);
        }

        if (! is_string($edition) || ! is_string($url) || ! is_string($target)) {
            return $this->failWith('Invalid feedback.maxmind configuration.', self::INVALID);
        }

        $work = storage_path('framework/geoip-'.bin2hex(random_bytes(4)));
        @mkdir($work, 0775, true);
        $archive = $work.'/db.tar.gz';

        try {
            $response = Http::withBasicAuth($account, $license)
                ->timeout(120)
                ->sink($archive)
                ->get(str_replace('{edition}', $edition, $url));

            if (! $response->successful()) {
                return $this->failWith("Download failed (HTTP {$response->status()}).", self::FAILURE);
            }

            (new PharData($archive))->extractTo($work, null, true);

            $mmdb = null;

            foreach (new RecursiveIteratorIterator(new \RecursiveDirectoryIterator($work, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file instanceof \SplFileInfo && str_ends_with($file->getFilename(), '.mmdb')) {
                    $mmdb = $file->getPathname();
                }
            }

            if ($mmdb === null) {
                return $this->failWith('The archive contains no .mmdb file.', self::FAILURE);
            }

            @mkdir(dirname($target), 0775, true);
            rename($mmdb, $target);
        } catch (Throwable $e) {
            return $this->failWith('Update failed: '.$e->getMessage(), self::FAILURE);
        } finally {
            $this->removeDirectory($work);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(['status' => 'updated', 'path' => $target, 'bytes' => filesize($target)], JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info("GeoIP-Datenbank aktualisiert: {$target}");
        }

        return self::SUCCESS;
    }

    private function failWith(string $message, int $code): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->error($message);
        }

        return $code;
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($items as $item) {
            if ($item instanceof \SplFileInfo) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}
