<?php
// Demo container overrides (baked into the image, not stored on the volume).
// Railway terminates TLS at its proxy and forwards plain HTTP.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] = '*';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxySSL'] = '*';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyHeaderMultiValue'] = 'first';
// Regex of allowed host names, e.g. '^typo3-demo\.up\.railway\.app$'. Defaults to any host.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = getenv('TYPO3_TRUSTED_HOSTS') ?: '.*';
$GLOBALS['TYPO3_CONF_VARS']['BE']['cookieSameSite'] = 'lax';
