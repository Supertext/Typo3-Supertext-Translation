<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Service;

use Supertext\Sdk\LanguageCodes;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Maps TYPO3 site languages to Supertext language codes.
 *
 * Target: the site language's locale with its region (de_CH.UTF-8 => "de-CH"); a
 * locale without region gets a regional default ("de" => "de-DE"), because
 * Supertext rejects bare targets. Source: the default language's bare language
 * ("en"). The rules are the Supertext SDK's LanguageCodes. Override per language in
 * config/sites/<site>/config.yaml:
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
     * @return array{target: string, source: string, politeness: string, title: string}|null
     */
    public function resolve(int $pageId, int $targetLanguageId): ?array
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
            $target = $site->getLanguageById($targetLanguageId);
        } catch (SiteNotFoundException|\InvalidArgumentException) {
            return null;
        }
        $config = $target->toArray();
        $politeness = (string)($config['supertext_politeness'] ?? 'default');

        return [
            'target' => LanguageCodes::target($this->codeOf($target)),
            'source' => LanguageCodes::source($this->codeOf($site->getDefaultLanguage())),
            'politeness' => in_array($politeness, ['more', 'less'], true) ? $politeness : 'default',
            'title' => $target->getTitle(),
        ];
    }

    /** The language's supertext_code, else its locale without encoding or modifier ("de_CH.UTF-8" => "de_CH"). */
    private function codeOf(SiteLanguage $language): string
    {
        $override = trim((string)($language->toArray()['supertext_code'] ?? ''));
        if ($override !== '') {
            return $override;
        }
        $name = $language->getLocale()->getName();
        $locale = $name !== '' ? $name : $language->getLocale()->getLanguageCode();
        return trim((string)preg_replace('/[.@].*$/', '', $locale));
    }
}
