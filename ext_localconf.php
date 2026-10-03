<?php

defined('TYPO3') or die();

// Translate records right after TYPO3 localizes them (Page module "Translate" wizard,
// "Create new translation", list module, CLI). Batched: one Supertext call per run.
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['supertext_translation']
    = \Supertext\Typo3Translation\Hooks\DataHandlerHook::class;
