<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Configuration;

/**
 * The extension's version from its official version source, ext_emconf.php (the number
 * TYPO3's extension manager shows and the release workflow checks). Never a second copy.
 */
final class ExtensionVersion
{
    private static ?string $version = null;

    public static function get(): string
    {
        if (self::$version !== null) {
            return self::$version;
        }
        $source = @file_get_contents(dirname(__DIR__, 2) . '/ext_emconf.php');
        $version = is_string($source) && preg_match("/'version'\\s*=>\\s*'([^']+)'/", $source, $m) === 1 ? $m[1] : '';
        return self::$version = ($version !== '' ? $version : '0.0.0');
    }
}
