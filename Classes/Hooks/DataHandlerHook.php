<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Hooks;

use Supertext\Typo3Translation\Configuration\ExtensionSettings;
use Supertext\Typo3Translation\Localization\Labels;
use Supertext\Typo3Translation\Service\TranslationResult;
use Supertext\Typo3Translation\Service\TranslationService;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Collects every record TYPO3 localizes ("localize" = connected translation,
 * "copyToLanguage" = free mode) during one DataHandler run, then translates them
 * all together once the run has finished.
 */
final class DataHandlerHook
{
    /** @var array<int, array<string, array{table: string, source: int, target: int, language: int}>> */
    private array $pending = [];

    private ?TranslationResult $lastResult = null;

    public function __construct(
        private readonly TranslationService $translationService,
        private readonly ExtensionSettings $settings,
        private readonly FlashMessageService $flashMessageService,
    ) {}

    public function processCmdmap_postProcess(string $command, string $table, $id, $value, DataHandler $dataHandler, $pasteUpdate = false, $pasteDatamap = false): void
    {
        if (!in_array($command, ['localize', 'copyToLanguage'], true) || TranslationService::$running || !$this->settings->isEnabled()) {
            return;
        }
        $language = (int)$value;
        if ($language <= 0) {
            return;
        }
        $key = spl_object_id($dataHandler);
        // copyMappingArray holds this command's source => new uid pairs, including
        // inline children (e.g. image captions in sys_file_reference).
        foreach ($dataHandler->copyMappingArray as $mappedTable => $map) {
            foreach ($map as $sourceUid => $targetUid) {
                if ((int)$targetUid > 0) {
                    $this->pending[$key][$mappedTable . ':' . $targetUid] = [
                        'table' => (string)$mappedTable,
                        'source' => (int)$sourceUid,
                        'target' => (int)$targetUid,
                        'language' => $language,
                    ];
                }
            }
        }
    }

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        $key = spl_object_id($dataHandler);
        $jobs = array_values($this->pending[$key] ?? []);
        unset($this->pending[$key]);
        if ($jobs === [] || !$dataHandler->BE_USER) {
            return;
        }

        $this->lastResult = $result = $this->translationService->translate($jobs, $dataHandler->BE_USER);

        if (Environment::isCli()) {
            return;
        }
        $queue = $this->flashMessageService->getMessageQueueByIdentifier();
        if ($result->records !== []) {
            $queue->enqueue(new FlashMessage($result->summary(Labels::forUser($dataHandler->BE_USER)), 'Supertext', ContextualFeedbackSeverity::OK, true));
        }
        foreach ($result->errors as $error) {
            $queue->enqueue(new FlashMessage($error, 'Supertext', ContextualFeedbackSeverity::WARNING, true));
        }
    }

    public function resetLastResult(): void
    {
        $this->lastResult = null;
    }

    public function getLastResult(): ?TranslationResult
    {
        return $this->lastResult;
    }
}
