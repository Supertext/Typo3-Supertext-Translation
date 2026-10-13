<?php

// Site Management > Sites > (site) > Languages > (language) > "Supertext":
// the Supertext language (supertext_code) and tone (supertext_politeness) of a site language.
// Stored in config/sites/<site>/config.yaml like the other language settings.

defined('TYPO3') or die();

(static function (): void {
    $ll = 'LLL:EXT:supertext_translation/Resources/Private/Language/locallang.xlf:';
    $items = [['label' => $ll . 'siteLanguage.code.automatic', 'value' => '']];
    foreach (\Supertext\Typo3Translation\Sdk\V1\Languages::TARGETS as $language) {
        $items[] = ['label' => $language['name'] . ' (' . $language['code'] . ')', 'value' => $language['code']];
    }

    $GLOBALS['SiteConfiguration']['site_language']['columns']['supertext_code'] = [
        'label' => $ll . 'siteLanguage.code',
        'description' => $ll . 'siteLanguage.code.description',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => $items,
            'default' => '',
        ],
    ];
    $GLOBALS['SiteConfiguration']['site_language']['columns']['supertext_politeness'] = [
        'label' => $ll . 'siteLanguage.politeness',
        'description' => $ll . 'siteLanguage.politeness.description',
        'displayCond' => 'FIELD:languageId:>:0',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => $ll . 'status.tone.default', 'value' => ''],
                ['label' => $ll . 'status.tone.more', 'value' => 'more'],
                ['label' => $ll . 'status.tone.less', 'value' => 'less'],
            ],
            'default' => '',
        ],
    ];
    $GLOBALS['SiteConfiguration']['site_language']['palettes']['supertext'] = [
        'label' => $ll . 'siteLanguage.palette',
        'showitem' => 'supertext_code, supertext_politeness',
    ];
    $GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem'] .= ', --palette--;;supertext';
})();
