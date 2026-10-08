<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Service;

use Supertext\Typo3Translation\Localization\Labels;

final class TranslationResult
{
    public int $fields = 0;
    /** @var array<string, true> */
    public array $records = [];
    /** @var array<string, string> language title => Supertext code */
    public array $languages = [];
    /** @var list<string> already in the user's interface language */
    public array $errors = [];

    public function summary(Labels $labels): string
    {
        if ($this->records === []) {
            return $labels->get('result.nothing');
        }
        $languages = [];
        foreach ($this->languages as $title => $code) {
            $languages[] = sprintf('%s (%s)', $title, $code);
        }
        return $labels->get(
            'result.summary',
            $this->fields,
            count($this->records),
            implode(', ', $languages)
        );
    }
}
