<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Service;

final class TranslationResult
{
    public int $fields = 0;
    /** @var array<string, true> */
    public array $records = [];
    /** @var array<string, string> language title => Supertext code */
    public array $languages = [];
    /** @var list<string> */
    public array $errors = [];

    public function summary(): string
    {
        if ($this->records === []) {
            return 'Nothing was translated.';
        }
        $languages = [];
        foreach ($this->languages as $title => $code) {
            $languages[] = sprintf('%s (%s)', $title, $code);
        }
        return sprintf(
            'Supertext translated %d field(s) in %d record(s) into %s.',
            $this->fields,
            count($this->records),
            implode(', ', $languages)
        );
    }
}
