<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Api;

/**
 * The English message is for logs; `labelKey` and `arguments` name the
 * translated message in Resources/Private/Language/locallang.xlf (error.*),
 * `detail` is the API's own answer, appended untranslated.
 */
final class SupertextException extends \RuntimeException
{
    /** @param list<string|int> $arguments */
    public function __construct(
        string $message,
        int $code,
        public readonly string $labelKey = '',
        public readonly array $arguments = [],
        public readonly string $detail = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($detail !== '' ? $message . ' (' . $detail . ')' : $message, $code, $previous);
    }
}
