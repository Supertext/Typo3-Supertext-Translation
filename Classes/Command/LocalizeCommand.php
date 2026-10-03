<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Command;

use Supertext\Typo3Translation\Hooks\DataHandlerHook;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * vendor/bin/typo3 supertext:localize <page> <language> [--recursive]
 *
 * Localizes a page and all of its content elements into a site language,
 * translated by Supertext. Handy for seeding demo sites and bulk jobs.
 */
final class LocalizeCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly DataHandlerHook $hook,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('page', InputArgument::REQUIRED, 'Page uid (default language)')
            ->addArgument('language', InputArgument::REQUIRED, 'Target site language id')
            ->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Include all subpages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Bootstrap::initializeBackendAuthentication();
        $language = (int)$input->getArgument('language');
        $pages = $this->collectPages((int)$input->getArgument('page'), (bool)$input->getOption('recursive'));

        $cmdmap = [];
        foreach ($pages as $pageUid) {
            if (!$this->hasTranslation('pages', 'l10n_parent', $pageUid, $language)) {
                $cmdmap['pages'][$pageUid]['localize'] = $language;
            }
            foreach ($this->contentOf($pageUid) as $contentUid) {
                if (!$this->hasTranslation('tt_content', 'l18n_parent', $contentUid, $language)) {
                    $cmdmap['tt_content'][$contentUid]['localize'] = $language;
                }
            }
        }
        if ($cmdmap === []) {
            $output->writeln('Everything is already translated.');
            return Command::SUCCESS;
        }

        $this->hook->resetLastResult();
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], $cmdmap);
        $dataHandler->process_cmdmap();
        foreach ($dataHandler->errorLog as $error) {
            $output->writeln('<error>' . $error . '</error>');
        }

        $result = $this->hook->getLastResult();
        if ($result === null) {
            $output->writeln('No Supertext translation ran: nothing new was localized, or the extension is disabled.');
            return Command::SUCCESS;
        }
        $output->writeln($result->summary());
        foreach ($result->errors as $error) {
            $output->writeln('<comment>' . $error . '</comment>');
        }
        return $result->errors === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return list<int> */
    private function collectPages(int $root, bool $recursive): array
    {
        $pages = [$root];
        if (!$recursive) {
            return $pages;
        }
        for ($i = 0; $i < count($pages); $i++) {
            $qb = $this->connectionPool->getQueryBuilderForTable('pages');
            $children = $qb->select('uid')->from('pages')
                ->where(
                    $qb->expr()->eq('pid', $qb->createNamedParameter($pages[$i], Connection::PARAM_INT)),
                    $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter(0, Connection::PARAM_INT))
                )
                ->executeQuery()->fetchFirstColumn();
            foreach ($children as $child) {
                $pages[] = (int)$child;
            }
        }
        return $pages;
    }

    /** @return list<int> */
    private function contentOf(int $pageUid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tt_content');
        return array_map('intval', $qb->select('uid')->from('tt_content')
            ->where(
                $qb->expr()->eq('pid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('sorting')
            ->executeQuery()->fetchFirstColumn());
    }

    private function hasTranslation(string $table, string $parentField, int $uid, int $language): bool
    {
        // Translations start out hidden, so only exclude deleted records here.
        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        $qb->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return (int)$qb->count('uid')->from($table)
            ->where(
                $qb->expr()->eq($parentField, $qb->createNamedParameter($uid, Connection::PARAM_INT)),
                $qb->expr()->eq('sys_language_uid', $qb->createNamedParameter($language, Connection::PARAM_INT))
            )
            ->executeQuery()->fetchOne() > 0;
    }
}
