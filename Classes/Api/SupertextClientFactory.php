<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Api;

use Supertext\Typo3Translation\Configuration\ExtensionSettings;
use Supertext\Typo3Translation\Configuration\ExtensionVersion;
use Supertext\Typo3Translation\Sdk\V1\Client;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Creates the Supertext SDK client (bundled in lib/supertext-sdk, namespace
 * Supertext\Typo3Translation\Sdk; see scripts/vendor-sdk.sh) from the
 * extension configuration. The SDK handles the protocol: authentication header,
 * multipart upload, 429 retries, polling, download, delete and HTML packing.
 */
final class SupertextClientFactory
{
    /** Sent in the User-Agent after the SDK's own name, with the version from ext_emconf.php. */
    public static function userAgent(): string
    {
        return 'TYPO3-Supertext-Translation/' . ExtensionVersion::get();
    }

    public function __construct(
        private readonly ExtensionSettings $settings,
        private readonly RequestFactory $requestFactory,
    ) {}

    public function hasApiKey(): bool
    {
        return $this->settings->getApiKey() !== '';
    }

    public function create(): Client
    {
        $timeout = $this->settings->getPollTimeout();
        if (function_exists('set_time_limit')) {
            @set_time_limit($timeout + 60);
        }
        return new Client(
            $this->settings->getApiKey(),
            baseUrl: $this->settings->getBaseUrl(),
            pollInterval: (float)$this->settings->getPollInterval(),
            timeout: (float)$timeout,
            userAgent: self::userAgent(),
            transport: $this->transport(),
        );
    }

    /** TYPO3's HTTP client for the SDK (translations, the API key check and version reports). */
    public function transport(): Typo3HttpTransport
    {
        return new Typo3HttpTransport($this->requestFactory);
    }

    public function getApiKey(): string
    {
        return $this->settings->getApiKey();
    }
}
