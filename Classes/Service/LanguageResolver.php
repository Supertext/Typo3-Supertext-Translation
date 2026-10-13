<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Service;

use Supertext\Typo3Translation\Sdk\V1\Languages;
use Supertext\Typo3Translation\Sdk\V1\Politeness;
use Supertext\Typo3Translation\Sdk\V1\SupertextException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Maps TYPO3 site languages to Supertext language codes, with the Supertext SDK's
 * rules (no guessing, the same in every Supertext plugin):
 *
 * - The site language's `supertext_code` wins (the administrator's mapping, sent as it is).
 * - Otherwise its locale without encoding (de_CH.UTF-8 => de_CH) is used if it equals a
 *   Supertext code apart from case and _/- (de_CH => de-CH, es => es, nl_NL => nothing:
 *   Supertext wants bare "nl").
 * - Anything else is SupertextException unsupported_language: nothing is sent, and the
 *   editor is asked to have the language mapped.
 *
 * The source is the default language's code reduced to what Supertext accepts as a source
 * (en_US => en). Set the codes per language in Site Management > Sites (tab Supertext) or
 * in config/sites/<site>/config.yaml:
 *
 *   languages:
 *     - languageId: 1
 *       locale: de_CH.UTF-8
 *       supertext_code: de-CH        # optional, Supertext target code
 *       supertext_politeness: more   # optional: more (formal) | less (informal)
 */
final class LanguageResolver
{
    public function __construct(private readonly SiteFinder $siteFinder) {}

    /**
     * @return array{target: string, source: string, politeness: string, title: string}|null null: the page's site has no such language
     * @throws SupertextException unsupported_language when a language has no Supertext code
     */
    public function resolve(int $pageId, int $targetLanguageId): ?array
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
            $target = $site->getLanguageById($targetLanguageId);
        } catch (SiteNotFoundException|\InvalidArgumentException) {
            return null;
        }
        return $this->resolveLanguage($site, $target);
    }

    /** The site language's title, '' when there is none. */
    public function titleOf(int $pageId, int $languageId): string
    {
        try {
            return $this->siteFinder->getSiteByPageId($pageId)->getLanguageById($languageId)->getTitle();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return array{target: string, source: string, politeness: string, title: string}
     * @throws SupertextException unsupported_language
     */
    public function resolveLanguage(Site $site, SiteLanguage $target): array
    {
        return [
            'target' => self::targetCode(self::localeOf($target), self::overrideOf($target)),
            'source' => self::sourceCode(self::localeOf($site->getDefaultLanguage()), self::overrideOf($site->getDefaultLanguage())),
            'politeness' => self::politenessOf($target),
            'title' => $target->getTitle(),
        ];
    }

    /**
     * The Supertext target for a locale: the override if set, else an exact Supertext code.
     *
     * @throws SupertextException unsupported_language (targetLang = the locale)
     */
    public static function targetCode(string $locale, string $override = ''): string
    {
        return Languages::target($locale, $override !== '' ? [$locale => $override] : []);
    }

    /**
     * The Supertext source for the default language: the override or the locale, reduced to a
     * Supertext source language (de-CH => de).
     *
     * @throws SupertextException unsupported_language (sourceLang = the code)
     */
    public static function sourceCode(string $locale, string $override = ''): string
    {
        return Languages::source($override !== '' ? $override : $locale);
    }

    /** The target code, or null when the language isn't mapped (for the status overview). */
    public static function targetCodeOrNull(string $locale, string $override = ''): ?string
    {
        try {
            return self::targetCode($locale, $override);
        } catch (SupertextException) {
            return null;
        }
    }

    /** The source code, or null when the default language isn't mapped (for the status overview). */
    public static function sourceCodeOrNull(string $locale, string $override = ''): ?string
    {
        try {
            return self::sourceCode($locale, $override);
        } catch (SupertextException) {
            return null;
        }
    }

    /** The language's supertext_code (trimmed), '' when not set. */
    public static function overrideOf(SiteLanguage $language): string
    {
        return trim((string)($language->toArray()['supertext_code'] ?? ''));
    }

    /** more, less or default, from supertext_politeness. */
    public static function politenessOf(SiteLanguage $language): string
    {
        $politeness = (string)($language->toArray()['supertext_politeness'] ?? '');
        return in_array($politeness, [Politeness::MORE, Politeness::LESS], true) ? $politeness : Politeness::DEFAULT;
    }

    /** The locale without encoding or modifier ("de_CH.UTF-8" => "de_CH"). */
    public static function localeOf(SiteLanguage $language): string
    {
        $name = $language->getLocale()->getName();
        $locale = $name !== '' ? $name : $language->getLocale()->getLanguageCode();
        return self::stripEncoding($locale);
    }

    public static function stripEncoding(string $locale): string
    {
        return trim((string)preg_replace('/[.@].*$/', '', $locale));
    }
}
