<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Reporting;

use TYPO3\CMS\Core\Registry;

/**
 * Keeps the reporter's state in TYPO3's registry (table sys_registry, namespace
 * "supertext_translation"). No API key is stored, only the SDK's key fingerprint.
 */
final class RegistryStateStorage implements StateStorage
{
    public const NAMESPACE = 'supertext_translation';

    public function __construct(private readonly Registry $registry) {}

    public function get(string $key): ?string
    {
        $value = $this->registry->get(self::NAMESPACE, $key);
        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value): void
    {
        $this->registry->set(self::NAMESPACE, $key, $value);
    }
}
