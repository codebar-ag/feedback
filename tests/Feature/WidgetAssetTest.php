<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/widget-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->dir.'/chunks');
    File::put($this->dir.'/feedback.js', 'var CodebarFeedback={};');
    File::put($this->dir.'/chunks/capture-Ab12_-x.js', 'export const capturePage=1;');
    File::put($this->dir.'/secret.php', '<?php');
    config(['feedback.widget_path' => $this->dir]);
});

afterEach(function (): void {
    File::deleteDirectory($this->dir);
});

it('serves the widget script to any website', function (): void {
    test()->get('/widget/v1/feedback.js', ['Origin' => 'https://web.atrion.test', 'Sec-Fetch-Dest' => 'script', 'Sec-Fetch-Mode' => 'no-cors'])
        ->assertOk()
        ->assertContent('var CodebarFeedback={};')
        ->assertHeader('Content-Type', 'text/javascript; charset=utf-8')
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('ETag');
});

it('serves the hashed screenshot chunk with CORS and a long cache', function (): void {
    // Loaded with import(): a CORS request, it fails without Access-Control-Allow-Origin.
    test()->get('/widget/v1/chunks/capture-Ab12_-x.js', ['Origin' => 'https://web.atrion.test', 'Sec-Fetch-Mode' => 'cors', 'Sec-Fetch-Dest' => 'script'])
        ->assertOk()
        ->assertContent('export const capturePage=1;')
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

});

it('answers 304 for an unchanged script', function (): void {
    $etag = (string) test()->get('/widget/v1/feedback.js')->headers->get('ETag');

    test()->get('/widget/v1/feedback.js', ['If-None-Match' => $etag])->assertStatus(304)->assertContent('');
    File::put($this->dir.'/feedback.js', 'var CodebarFeedback={v:2};');
    test()->get('/widget/v1/feedback.js', ['If-None-Match' => $etag])->assertOk()->assertContent('var CodebarFeedback={v:2};');
});

it('serves nothing else and nothing before the build', function (string $path): void {
    test()->get($path)->assertForbidden()->assertContent('');
})->with([
    '/widget/v1/secret.php',
    '/widget/v1/sri.json',
    '/widget/v1/../../.env',
    '/widget/v1/chunks/../secret.php',
    '/widget/v1/chunks/other.js',
    '/widget/v2/feedback.js',
    '/widget/v1/',
]);

it('reveals nothing when the widget was not built', function (): void {
    config(['feedback.widget_path' => $this->dir.'/missing']);

    test()->get('/widget/v1/feedback.js')->assertForbidden()->assertContent('');
});

it('is a script, not a page: typing the URL into a browser gets the usual 403', function (): void {
    test()->get('/widget/v1/feedback.js', ['Sec-Fetch-Mode' => 'navigate', 'Sec-Fetch-Dest' => 'document'])->assertForbidden()->assertContent('');
});
