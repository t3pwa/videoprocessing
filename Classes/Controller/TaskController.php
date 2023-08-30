<?php

namespace Faeb\Videoprocessing\Controller;

use Psr\Http\Message\ResponseInterface;
use Faeb\Videoprocessing\Processing\VideoTaskRepository;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class TaskController extends ActionController
{

    private VideoTaskRepository $videoTaskRepository;

    public function listAction(): ResponseInterface
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_videoprocessing_task');
        $qb->from('tx_videoprocessing_task', 'task');
        $qb->select(
            'task.uid',
            'task.crdate',
            'task.file AS file_uid',
            'task.configuration',
            'task.status'
        );

        $qb->leftJoin('task', 'sys_file', 'file', $qb->expr()->eq('file.uid', $qb->quoteIdentifier('task.file')));
        $qb->addSelectLiteral(
            "CONCAT({$qb->quoteIdentifier('file.storage')}, ':', {$qb->quoteIdentifier('file.identifier')}) AS file_combi_ident"
        );
        $qb->addSelect(
            'file.uid AS file_uid',
            'file.identifier AS file_identifier',
            'file.name AS file_name',
            'task.status'
        );
        $qb->orderBy('task.uid', 'DESC');

        $statement = $qb->execute();
        $generator = function () use ($statement) {
            while ($row = $statement->fetch()) {
                $row['configuration'] = unserialize($row['configuration']);
                yield $row;
            }
        };

        $this->view->assignMultiple([
            'tasks' => $generator(),
        ]);
        return $this->htmlResponse();
    }

    public function deleteAction(int $task)
    {
        $videoTaskRepository = GeneralUtility::makeInstance(VideoTaskRepository::class);

        try {
            if ($videoTaskRepository->findByUid($task)) {
                $task = $videoTaskRepository->findByUid($task);
            }
        } finally {

        }

        if (!$task) {
            $this->addFlashMessage("Task wasn't found", AbstractMessage::ERROR);
            $this->redirect('list');
        }

        $processedFile = $task->getTargetFile();
        if (!$processedFile->usesOriginalFile() && $processedFile->delete()) {
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
            $cacheManager->flushCachesInGroupByTag('pages', "processed_video_{$processedFile->getUid()}");
            $this->addFlashMessage("Processed file {$processedFile->getName()} was deleted.", AbstractMessage::OK);
        } else {
            $this->addFlashMessage("The associated processed file {$processedFile->getName()} could not be deleted.", AbstractMessage::ERROR);
        }

        if ($videoTaskRepository->delete($task)) {
            $this->addFlashMessage("Task was deleted.", AbstractMessage::OK);
        } else {
            $this->addFlashMessage("Unknown error while deleting the task.", AbstractMessage::ERROR);
        }

        $this->redirect('list');
    }
}
