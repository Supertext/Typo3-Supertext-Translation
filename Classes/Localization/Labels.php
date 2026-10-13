<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Localization;

use Supertext\Sdk\SupertextException;
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

    /** Label key per Supertext SDK error code (SupertextException::$errorCode). */
    private const ERROR_LABELS = [
        SupertextException::MISSING_API_KEY => 'error.noApiKey',
        SupertextException::AUTHENTICATION_FAILURE => 'error.authentication',
        SupertextException::INVALID_LANGUAGE_PAIR => 'error.languagePair',
        SupertextException::NOT_FOUND => 'error.notFound',
        SupertextException::PAYLOAD_TOO_LARGE => 'error.tooLarge',
        SupertextException::TOO_MANY_REQUESTS => 'error.rateLimit',
        SupertextException::SERVICE_UNAVAILABLE => 'error.unavailable',
        SupertextException::UNEXPECTED_STATUS => 'error.http',
        SupertextException::TRANSPORT_ERROR => 'error.unreachable',
        SupertextException::NO_FILE_ID => 'error.noFileId',
        SupertextException::TRANSLATION_ERROR => 'error.failed',
        SupertextException::QUOTA_EXCEEDED => 'error.limitExceeded',
        SupertextException::FILE_DELETED => 'error.deleted',
        SupertextException::TIMEOUT => 'error.timeout',
        SupertextException::INCOMPLETE_RESPONSE => 'error.empty',
    ];

    /** The translated message of a Supertext SDK error, or the English message of other exceptions. */
    public function forException(\Throwable $e): string
    {
        if (!$e instanceof SupertextException || !isset(self::ERROR_LABELS[$e->errorCode])) {
            return $e->getMessage();
        }
        $detail = trim((string)$e->detail);
        $message = match ($e->errorCode) {
            SupertextException::UNEXPECTED_STATUS => $this->get('error.http', (int)$e->status),
            SupertextException::TRANSPORT_ERROR => $this->get('error.unreachable', $detail),
            default => $this->get(self::ERROR_LABELS[$e->errorCode]),
        };
        if ($e->errorCode === SupertextException::TRANSPORT_ERROR) {
            return $message;
        }
        return $detail !== '' ? $message . ' (' . $detail . ')' : $message;
    }
}
