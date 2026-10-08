<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Localization;

use Supertext\Typo3Translation\Api\SupertextException;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Messages shown to editors, in the backend user's interface language
 * (Resources/Private/Language/{,de.,fr.,it.}locallang.xlf; English is the fallback).
 */
final class Labels
{
    public const FILE = 'LLL:EXT:supertext_translation/Resources/Private/Language/locallang.xlf:';

    public function __construct(private readonly LanguageService $languageService) {}

    public static function forUser(?AbstractUserAuthentication $user): self
    {
        return new self(GeneralUtility::makeInstance(LanguageServiceFactory::class)->createFromUserPreferences($user));
    }

    public function get(string $key, string|int ...$arguments): string
    {
        $label = $this->languageService->sL(self::FILE . $key);
        if ($label === '') {
            $label = $key;
        }
        return $arguments === [] ? $label : vsprintf($label, $arguments);
    }

    /** The translated message of an API error, or its English message for other exceptions. */
    public function forException(\Throwable $e): string
    {
        if (!$e instanceof SupertextException || $e->labelKey === '') {
            return $e->getMessage();
        }
        $message = $this->get($e->labelKey, ...$e->arguments);
        return $e->detail !== '' ? $message . ' (' . $e->detail . ')' : $message;
    }
}
