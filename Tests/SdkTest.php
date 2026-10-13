<?php

// Checks the glue between the extension and the Supertext SDK (needs `composer install`):
//   php Tests/SdkTest.php
// The SDK has its own tests for the protocol, HTML packing and language codes.
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Supertext\Typo3Translation\Sdk\Client;
use Supertext\Typo3Translation\Sdk\Http\HttpRequest;
use Supertext\Typo3Translation\Sdk\Http\HttpResponse;
use Supertext\Typo3Translation\Sdk\Http\HttpTransport;
use Supertext\Typo3Translation\Sdk\LanguageCodes;
use Supertext\Typo3Translation\Sdk\Segment;
use Supertext\Typo3Translation\Sdk\SupertextException;
use Supertext\Typo3Translation\Localization\Labels;

$failures = [];
$check = static function (string $name, mixed $actual, mixed $expected) use (&$failures): void {
    if ($actual !== $expected) {
        $failures[] = sprintf("%s:\n  expected: %s\n  actual:   %s", $name, json_encode($expected), json_encode($actual));
    }
};

// 1. Every SDK error code has a label, and the label exists in all four languages.
$labels = (new ReflectionClassConstant(Labels::class, 'ERROR_LABELS'))->getValue();
$codes = (new ReflectionClass(SupertextException::class))->getConstants(ReflectionClassConstant::IS_PUBLIC);
$english = file_get_contents(__DIR__ . '/../Resources/Private/Language/locallang.xlf');
foreach ($codes as $code) {
    if (!isset($labels[$code])) {
        $failures[] = "SDK error code $code has no label in Labels::ERROR_LABELS";
    } elseif (!str_contains($english, 'id="' . $labels[$code] . '"')) {
        $failures[] = "label {$labels[$code]} (for $code) is missing in locallang.xlf";
    }
}

// 2. TYPO3 locales, as LanguageResolver hands them to the SDK (encoding stripped).
$strip = static fn(string $locale): string => trim((string)preg_replace('/[.@].*$/', '', $locale));
$check('target de_CH.UTF-8', LanguageCodes::target($strip('de_CH.UTF-8')), 'de-CH');
$check('target fr_FR.UTF-8@euro', LanguageCodes::target($strip('fr_FR.UTF-8@euro')), 'fr-FR');
$check('target bare de', LanguageCodes::target($strip('de')), 'de-DE');
$check('source en_US.UTF-8', LanguageCodes::source($strip('en_US.UTF-8')), 'en');
$check('target variant dropped', LanguageCodes::target($strip('de_DE_formal')), 'de-DE');

// 3. translateTexts round trip with a fake transport that "translates" by putting » before every text node.
$transport = new class implements HttpTransport {
    public string $uploaded = '';
    public array $fields = [];

    public function send(HttpRequest $request): HttpResponse
    {
        if ($request->method === 'POST') {
            $this->uploaded = (string)$request->body;
            preg_match_all('/name="(target_lang|source_lang|politeness)"\r\n\r\n([^\r]*)/', $this->uploaded, $m);
            $this->fields = array_combine($m[1], $m[2]);
            return new HttpResponse(200, '{"file_id":"f1"}');
        }
        if (str_ends_with($request->url, '/status')) {
            return new HttpResponse(200, '{"status":"done"}');
        }
        if (str_ends_with($request->url, '/translation')) {
            preg_match('/<body>.*<\/body>/s', $this->uploaded, $m);
            $body = preg_replace_callback('/>([^<]+)</', static fn(array $t): string => '>»' . $t[1] . '<', $m[0]);
            return new HttpResponse(200, '<html>' . $body . '</html>');
        }
        return new HttpResponse(204);
    }
};
$client = new Client('Supertext-Auth-Key test', baseUrl: 'https://api.supertext.com/v1/', transport: $transport, sleep: static function (): void {});
$result = $client->translateTexts([
    3 => new Segment("Fish & Chips\nfor two", false),
    7 => new Segment('<p>We ship <strong>Swiss chocolate</strong>.</p>', true),
    9 => new Segment('  ', false),
], 'de_CH', 'en-US', 'more');
$check('plain text segment', $result[3] ?? null, "»Fish & Chips\n»for two");
$check('html segment', $result[7] ?? null, '<p>»We ship <strong>»Swiss chocolate</strong>».</p>');
$check('blank segment unchanged', $result[9] ?? null, '  ');
$check('keys kept in order', array_keys($result), [3, 7, 9]);
$check('target sent regional', $transport->fields['target_lang'] ?? null, 'de-CH');
$check('source sent bare', $transport->fields['source_lang'] ?? null, 'en');
$check('politeness sent', $transport->fields['politeness'] ?? null, 'more');

// 4. A language Supertext doesn't know at all (HTTP 404 INVALID_LANGUAGE_PAIR) gets the language pair label.
$unknown = new Client('test', transport: new class implements HttpTransport {
    public function send(HttpRequest $request): HttpResponse
    {
        return new HttpResponse(404, '{"error_code":"INVALID_LANGUAGE_PAIR","source_lang":"en","target_lang":"xx-YY"}', ['content-type' => 'application/json']);
    }
}, sleep: static function (): void {});
try {
    $unknown->submit('<p data-st-id="0">Hi</p>', 'xx-YY', 'en');
    $failures[] = 'unknown language: expected an error';
} catch (SupertextException $e) {
    $check('unknown language: error code', $e->errorCode, SupertextException::INVALID_LANGUAGE_PAIR);
    $check('unknown language: label', $labels[$e->errorCode] ?? null, 'error.languagePair');
}

foreach ($failures as $failure) {
    fwrite(STDERR, $failure . "\n");
}
echo $failures === [] ? "OK: extension and Supertext SDK fit together\n" : 'FAILED: ' . count($failures) . "\n";
exit($failures === [] ? 0 : 1);
