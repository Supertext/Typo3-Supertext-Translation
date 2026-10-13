<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Reporting;

/**
 * Where the reporter's state lives between requests (TYPO3: the registry, see
 * RegistryStateStorage). Values are plain strings (JSON); implementations may throw,
 * Reporting catches everything.
 */
interface StateStorage
{
    public function get(string $key): ?string;

    public function set(string $key, string $value): void;
}
