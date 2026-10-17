<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Configuration;

use Supertext\Typo3Translation\Localization\Labels;
use Supertext\Typo3Translation\PluginHealth\KeyCheck;
use Supertext\Typo3Translation\Reporting\Typo3Reporting;
use Supertext\Typo3Translation\Sdk\V1\Languages;
use Supertext\Typo3Translation\Sdk\V1\Politeness;
use Supertext\Typo3Translation\Service\LanguageResolver;
use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The "Status" block at the top of the extension configuration (ext_conf_template.txt,
 * type=user): the latest API key check, the version reports setting with the ping URL,
 * and how every site language maps to a Supertext language (its choice, an exact match or the
 * SDK's default; only languages with neither are marked as not mapped).
 *
 * Opening the configuration after a save runs the API key check and the version report
 * (TYPO3 has no "configuration saved" event; see Reporting), so the result shows here.
 * Called by TYPO3's install tool without dependency injection, so it builds what it needs
 * itself and never lets an error break the configuration form.
 */
final class StatusField
{
    public const ACCOUNT_URL = 'https://www.supertext.com/person/en/account/signin';
    public const API_KEY_URL = 'https://www.supertext.com/en/integrations/api';

    /** @param array<string, mixed> $params */
    #[AsAllowedCallable]
    public function render(array $params = []): string
    {
        $labels = Labels::forUser($GLOBALS['BE_USER'] ?? null);
        try {
            $reporting = GeneralUtility::makeInstance(Typo3Reporting::class);
            $settings = GeneralUtility::makeInstance(ExtensionSettings::class);
            $sites = GeneralUtility::makeInstance(SiteFinder::class);
        } catch (\Throwable) {
            return '<p>' . $this->e($labels->get('status.unavailable')) . '</p>';
        }

        try {
            if ($reporting->settingsChanged()) {
                // Saved since the last check: check the key and report now, and show the result.
                $check = $reporting->run()->check ?? $reporting->lastCheck();
            } else {
                $check = $reporting->lastCheck();
                $reporting->later();
            }
        } catch (\Throwable) {
            $check = null;
        }

        $html = '<div class="supertext-status">';
        $html .= $this->keyCheck($labels, $check);
        $html .= $this->reports($labels, $reporting, $settings);
        $html .= $this->languages($labels, $sites);
        return $html . '</div>';
    }

    public function keyCheck(Labels $labels, ?KeyCheck $check): string
    {
        $html = '<p><strong>' . $this->e($labels->get('status.keyCheck')) . '</strong></p>';
        if ($check === null) {
            return $html . '<div class="callout callout-info"><div class="callout-body">' . $this->e($labels->get('status.notChecked')) . '</div></div>';
        }
        $severity = match ($check->status) {
            KeyCheck::OK => 'success',
            KeyCheck::INVALID, KeyCheck::MISSING => 'danger',
            default => 'warning',
        };
        $message = self::keyCheckMessage($labels, $check);
        $time = strtotime($check->checkedAt);
        $checkedAt = $labels->get('status.checkedAt', $time !== false ? date('Y-m-d H:i', $time) : $check->checkedAt);
        return $html . '<div class="callout callout-' . $severity . '"><div class="callout-body">'
            . self::withLinks($this->e($message)) . ' <small>(' . $this->e($checkedAt) . ')</small></div></div>';
    }

    /** The Commando Center's message for a key check, in the user's language. */
    public static function keyCheckMessage(Labels $labels, KeyCheck $check): string
    {
        return match ($check->status) {
            KeyCheck::OK, KeyCheck::INVALID, KeyCheck::MISSING, KeyCheck::UNREACHABLE => $labels->get('keycheck.' . $check->status),
            default => $labels->get('keycheck.error', (string)($check->httpStatus ?? $check->errorCode ?? '?')),
        };
    }

    private function reports(Labels $labels, Typo3Reporting $reporting, ExtensionSettings $settings): string
    {
        $enabled = $settings->areVersionReportsEnabled();
        $state = $enabled ? $labels->get('status.reportsOn') : $labels->get($settings->isVersionReportsSetByEnvironment() ? 'status.reportsOffEnvironment' : 'status.reportsOff');
        $html = '<p><strong>' . $this->e($labels->get('status.reports')) . ':</strong> ' . $this->e($state);
        if ($enabled) {
            $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
            $installation = $reporting->installation($request instanceof \Psr\Http\Message\ServerRequestInterface ? $request : null);
            $html .= '<br>' . $this->e($installation->callbackUrl !== null ? $labels->get('status.pingUrl', $installation->callbackUrl) : $labels->get('status.noSiteUrl'));
        }
        return $html . '</p>';
    }

    private function languages(Labels $labels, SiteFinder $siteFinder): string
    {
        try {
            $sites = $siteFinder->getAllSites();
        } catch (\Throwable) {
            $sites = [];
        }
        $html = '<p><strong>' . $this->e($labels->get('status.languages')) . '</strong><br>' . $this->e($labels->get('status.languagesHelp')) . '</p>';
        if ($sites === []) {
            return $html . '<p>' . $this->e($labels->get('status.noSites')) . '</p>';
        }
        foreach ($sites as $site) {
            $html .= '<table class="table table-sm table-striped"><caption>' . $this->e($labels->get('status.site', $site->getIdentifier())) . '</caption>'
                . '<thead><tr><th>' . $this->e($labels->get('status.language')) . '</th><th>' . $this->e($labels->get('status.locale')) . '</th><th>'
                . $this->e($labels->get('status.supertext')) . '</th><th>' . $this->e($labels->get('status.tone')) . '</th></tr></thead><tbody>';
            $default = $site->getDefaultLanguage();
            foreach ($site->getAllLanguages() as $language) {
                $locale = LanguageResolver::localeOf($language);
                $override = LanguageResolver::overrideOf($language);
                $isDefault = $language->getLanguageId() === $default->getLanguageId();
                $code = $isDefault ? LanguageResolver::sourceCodeOrNull($locale, $override) : LanguageResolver::targetCodeOrNull($locale, $override);
                if ($code === null) {
                    $codeCell = '<span class="text-danger">' . $this->e($labels->get('status.notMapped')) . '</span>';
                } else {
                    $notes = [];
                    if ($isDefault) {
                        $notes[] = $labels->get('status.source');
                    }
                    if (LanguageResolver::usesDefault($locale, $override)) {
                        $notes[] = $labels->get('status.default');
                    }
                    $codeCell = '<code>' . $this->e($code) . '</code>' . ($notes !== [] ? ' <small>(' . $this->e(implode(', ', $notes)) . ')</small>' : '');
                }
                $tone = '';
                if (!$isDefault) {
                    $politeness = LanguageResolver::politenessOf($language);
                    $tone = $this->e($labels->get('status.tone.' . $politeness));
                    if ($politeness !== Politeness::DEFAULT && $code !== null && Languages::supportsPoliteness($code) === false) {
                        $tone .= ' <span class="text-danger">' . $this->e($labels->get('status.noPoliteness')) . '</span>';
                    }
                }
                $html .= '<tr><td>' . $this->e($language->getTitle()) . '</td><td><code>' . $this->e($locale) . '</code></td><td>' . $codeCell . '</td><td>' . $tone . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }
        return $html;
    }

    /**
     * Links "supertext.com → Integrations → API" to the API key page and the remaining
     * "supertext.com" to the account page (new tab), in an already escaped message.
     */
    public static function withLinks(string $escaped): string
    {
        $apiPhrase = 'supertext.com → Integrations → API';
        $link = static fn(string $url, string $text): string => '<a href="' . $url . '" target="_blank" rel="noopener">' . $text . '</a>';
        $parts = explode($apiPhrase, $escaped);
        foreach ($parts as $i => $part) {
            $parts[$i] = str_replace('supertext.com', $link(self::ACCOUNT_URL, 'supertext.com'), $part);
        }
        return implode($link(self::API_KEY_URL, $apiPhrase), $parts);
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
