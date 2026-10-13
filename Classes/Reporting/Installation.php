<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Reporting;

/**
 * What the reporter needs to know about this TYPO3 installation (built by Typo3Reporting).
 */
final class Installation
{
    /**
     * @param bool        $reportsEnabled the setting combined with SUPERTEXT_VERSION_REPORTS
     * @param string|null $siteUrl        the site's base URL; null when unknown (then nothing is reported, the key is still checked)
     * @param string|null $callbackUrl    the absolute URL of the ping endpoint
     */
    public function __construct(
        public readonly bool $reportsEnabled,
        public readonly ?string $siteUrl,
        public readonly ?string $callbackUrl,
        public readonly string $pluginVersion,
        public readonly ?string $systemVersion,
        public readonly string $apiBaseUrl,
        public readonly string $settingsFingerprint,
    ) {}
}
