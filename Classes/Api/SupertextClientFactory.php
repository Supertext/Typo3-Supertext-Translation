<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Api;

use Supertext\Sdk\Client;
use Supertext\Typo3Translation\Configuration\ExtensionSettings;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Creates the official Supertext SDK client (supertext/supertext-sdk) from the
 * extension configuration. The SDK handles the protocol: authentication header,
 * multipart upload, 429 retries, polling, download, delete, HTML packing and
 * language codes.
 */
final class SupertextClientFactory
{
    /** Sent in the User-Agent after the SDK's own name. */
    public const USER_AGENT = 'TYPO3-Supertext-Translation/0.1.0';

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
            userAgent: self::USER_AGENT,
            transport: new Typo3HttpTransport($this->requestFactory),
        );
    }
}
