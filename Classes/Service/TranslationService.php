<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Service;

use Psr\Log\LoggerInterface;
use Supertext\Typo3Translation\Api\HtmlDocument;
use Supertext\Typo3Translation\Api\SupertextClient;
use Supertext\Typo3Translation\Configuration\ExtensionSettings;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Translates freshly localized records with Supertext and writes the result back.
 *
 * All records of one DataHandler run that share a target language go to Supertext
 * as ONE document (split only beyond ~900k characters), so translating a page with
 * twenty content elements is a single API round trip.
 */
final class TranslationService
{
    /** Set while we write translations back, so our own DataHandler run is ignored. */
    public static bool $running = false;

    public function __construct(
        private readonly SupertextClient $client,
        private readonly FieldCollector $fieldCollector,
        private readonly LanguageResolver $languageResolver,
        private readonly ExtensionSettings $settings,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param list<array{table: string, source: int, target: int, language: int}> $jobs
     */
    public function translate(array $jobs, BackendUserAuthentication $backendUser): TranslationResult
    {
        $result = new TranslationResult();
        if (!$this->client->hasApiKey()) {
            $result->errors[] = 'No Supertext API key configured. Records were localized but not translated.';
            return $result;
        }

        // Group segments by language pair, so each pair is one document.
        $groups = [];
        $verbatim = [];
        foreach ($jobs as $job) {
            $source = BackendUtility::getRecord($job['table'], $job['source']);
            $target = BackendUtility::getRecord($job['table'], $job['target']);
            if (!is_array($source) || !is_array($target)) {
                continue;
            }
            $pageId = $job['table'] === 'pages' ? $job['source'] : (int)$source['pid'];
            $language = $this->languageResolver->resolve($pageId, $job['language']);
            if ($language === null) {
                $result->errors[] = sprintf('%s:%d has no site language %d; skipped.', $job['table'], $job['source'], $job['language']);
                continue;
            }
            $key = $language['target'] . '|' . $language['source'] . '|' . $language['politeness'];
            $groups[$key]['language'] ??= $language;
            foreach ($this->fieldCollector->collect($job['table'], $source) as $field => $isHtml) {
                $groups[$key]['segments'][] = [
                    'table' => $job['table'],
                    'uid' => $job['target'],
                    'field' => $field,
                    'text' => (string)$source[$field],
                    'html' => $isHtml,
                ];
            }
            $groups[$key]['records'][$job['table'] . ':' . $job['target']] = $target;
            // Code fields we deliberately skip (e.g. HTML elements) keep their source
            // verbatim, without TYPO3's "[Translate to …:]" prefix breaking the code.
            if ($job['table'] === 'tt_content'
                && in_array((string)($source['CType'] ?? ''), $this->settings->getSkipBodytextCTypes(), true)
                && (string)($target['bodytext'] ?? '') !== (string)($source['bodytext'] ?? '')
            ) {
                $verbatim['tt_content'][$job['target']]['bodytext'] = (string)$source['bodytext'];
            }
        }

        $datamap = $verbatim;
        foreach ($groups as $group) {
            if (empty($group['segments'])) {
                continue;
            }
            $language = $group['language'];
            foreach ($this->chunk($group['segments']) as $chunk) {
                try {
                    $translated = $this->translateChunk($chunk, $language);
                } catch (\Throwable $e) {
                    $this->logger->error('Supertext translation failed', ['exception' => $e, 'language' => $language['target']]);
                    $result->errors[] = sprintf('%s: %s', $language['title'], $e->getMessage());
                    continue;
                }
                foreach ($chunk as $i => $segment) {
                    if (!isset($translated[$i]) || $translated[$i] === '') {
                        continue;
                    }
                    $datamap[$segment['table']][$segment['uid']][$segment['field']] = $translated[$i];
                    $result->fields++;
                    $result->records[$segment['table'] . ':' . $segment['uid']] = true;
                }
                $result->languages[$language['title']] = $language['target'];
            }
            if ($this->settings->shouldRegenerateSlugs()) {
                $this->addSlugs($datamap, $group['records']);
            }
        }

        if ($datamap !== []) {
            $this->write($datamap, $backendUser, $result);
        }
        return $result;
    }

    /**
     * @param list<array{table: string, uid: int, field: string, text: string, html: bool}> $chunk
     * @param array{target: string, source: string, politeness: string, title: string} $language
     * @return array<int, string>
     */
    private function translateChunk(array $chunk, array $language): array
    {
        $segments = [];
        $isHtml = [];
        foreach ($chunk as $i => $segment) {
            $segments[$i] = ['text' => $segment['text'], 'html' => $segment['html']];
            $isHtml[$i] = $segment['html'];
        }
        $translatedHtml = $this->client->translateDocument(
            HtmlDocument::build($segments),
            $language['target'],
            $language['source'],
            $language['politeness'],
        );
        return HtmlDocument::parse($translatedHtml, $isHtml);
    }

    /**
     * @param list<array{table: string, uid: int, field: string, text: string, html: bool}> $segments
     * @return list<list<array{table: string, uid: int, field: string, text: string, html: bool}>>
     */
    private function chunk(array $segments): array
    {
        $chunks = [];
        $current = [];
        $size = 0;
        foreach ($segments as $segment) {
            $length = mb_strlen($segment['text']) + 40;
            if ($current !== [] && $size + $length > SupertextClient::MAX_DOCUMENT_CHARACTERS) {
                $chunks[] = $current;
                $current = [];
                $size = 0;
            }
            $current[] = $segment;
            $size += $length;
        }
        if ($current !== []) {
            $chunks[] = $current;
        }
        return $chunks;
    }

    /**
     * Builds the URL segment of translated pages from their translated title.
     *
     * @param array<string, array<int, array<string, string>>> $datamap
     * @param array<string, array<string, mixed>> $records
     */
    private function addSlugs(array &$datamap, array $records): void
    {
        $slugConfig = $GLOBALS['TCA']['pages']['columns']['slug']['config'] ?? null;
        if (!is_array($slugConfig)) {
            return;
        }
        foreach ($datamap['pages'] ?? [] as $uid => $values) {
            $record = $records['pages:' . $uid] ?? null;
            if (!is_array($record) || !isset($values['title'])) {
                continue;
            }
            try {
                $record = array_merge($record, $values);
                $helper = GeneralUtility::makeInstance(SlugHelper::class, 'pages', 'slug', $slugConfig);
                $slug = $helper->generate($record, (int)$record['pid']);
                $state = RecordStateFactory::forName('pages')->fromArray($record, (int)$record['pid'], (int)$uid);
                $datamap['pages'][$uid]['slug'] = $helper->buildSlugForUniqueInSite($slug, $state);
            } catch (\Throwable $e) {
                $this->logger->warning('Could not regenerate slug', ['uid' => $uid, 'exception' => $e]);
            }
        }
    }

    /** @param array<string, array<int, array<string, string>>> $datamap */
    private function write(array $datamap, BackendUserAuthentication $backendUser, TranslationResult $result): void
    {
        self::$running = true;
        try {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start($datamap, [], $backendUser);
            $dataHandler->process_datamap();
            foreach ($dataHandler->errorLog as $error) {
                $result->errors[] = (string)$error;
            }
        } finally {
            self::$running = false;
        }
    }
}
