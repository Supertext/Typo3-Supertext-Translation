<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Service;

use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Maps TYPO3 site languages to Supertext language codes.
 *
 * Defaults to the site language's locale (de_CH.UTF-8 => "de-CH"). Override per
 * language in config/sites/<site>/config.yaml:
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
            'target' => $this->codeFor($target),
            'source' => $site->getDefaultLanguage()->getLocale()->getLanguageCode(),
            'politeness' => in_array($politeness, ['more', 'less'], true) ? $politeness : 'default',
            'title' => $target->getTitle(),
        ];
    }

    private function codeFor(SiteLanguage $language): string
    {
        $custom = trim((string)($language->toArray()['supertext_code'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        $name = $language->getLocale()->getName();
        return $name !== '' ? $name : $language->getLocale()->getLanguageCode();
    }
}
