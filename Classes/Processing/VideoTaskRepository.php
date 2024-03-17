<?php

namespace Faeb\Videoprocessing\Processing;


use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use TYPO3\CMS\Core\Utility\DebugUtility;

class VideoTaskRepository implements SingletonInterface
{
    const TABLE_NAME = 'tx_videoprocessing_task';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var VideoProcessingTask[]
     */
    private $tasks = [];

    public function __construct()
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $this->connection = $connectionPool->getConnectionForTable(self::TABLE_NAME);
    }

    public function store(VideoProcessingTask $task)
    {
        $values = [
            'tstamp' => time(),
            'file' => $task->getSourceFile()->getUid(),
            'configuration' => serialize($task->getConfiguration()),
            'status' => $task->getStatus(),
            'progress' => json_encode($task->getProgressSteps(), JSON_UNESCAPED_SLASHES),
            'priority' => $task->getPriority(),
        ];

        if ($task->getUid() !== null && $this->tasks[$task->getUid()] === $task) {
            $this->connection->update(self::TABLE_NAME, $values, ['uid' => $task->getUid()]);
        } else {
            $this->connection->insert(self::TABLE_NAME, $values + ['crdate' => $values['tstamp']]);
            $id = $this->connection->lastInsertId(self::TABLE_NAME);
            $task->setDatabaseRow($values + ['uid' => $id]);
            $this->tasks[$id] = $task;
        }
    }

    /**
     * @return QueryBuilder
     */
    private function createQueryBuilder(): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->from(self::TABLE_NAME, 'task');
        $qb->select('task.uid', 'task.file', 'task.configuration', 'task.status', 'task.progress');
        return $qb;
    }


    /**
     * @param TaskInterface $task
     *
     * @return VideoProcessingTask|null
     */
    public function findByTask(TaskInterface $task): ?VideoProcessingTask
    {
        if ($task instanceof VideoProcessingTask && $task->getUid() && isset($this->tasks[$task->getUid()])) {
            return $this->tasks[$task->getUid()];
        }

        return $this->findByFile($task->getSourceFile()->getUid(), $task->getConfiguration());
    }

    /**
     * @param int $file
     * @param array $configuration
     *
     * @return VideoProcessingTask|null
     */
    public function findByFile(int $file, array $configuration): ?VideoProcessingTask
    {
        $qb = $this->createQueryBuilder();
        $qb->orderBy('task.uid', 'desc');

        $qb->setParameter('file', $file);
        $qb->andWhere($qb->expr()->eq('task.file', ':file'));

        $qb->setParameter('configuration', serialize($configuration));
        $qb->andWhere($qb->expr()->eq('task.configuration', ':configuration'));

        $qb->setMaxResults(1);
        $row = $qb->execute()->fetch();

       \TYPO3\CMS\Core\Utility\DebugUtility::debug($row);

        if (!$row) {
            return null;
        }

        return $this->serializeTask($row);
    }

    /**
     * @param int $uid
     *
     * @return VideoProcessingTask|null
     */
    public function findByUid(int $uid): ?VideoProcessingTask
    {
        $qb = $this->createQueryBuilder();
        $qb->andWhere($qb->expr()->eq('task.uid', $qb->createNamedParameter($uid, Connection::PARAM_INT)));

        $qb->setMaxResults(1);
        $row = $qb->execute()->fetch();

//        \TYPO3\CMS\Core\Utility\DebugUtility::debug($row);


        if (!$row) {
            return null;
        }

        return $this->serializeTask($row);
    }

    /**
     * Finds tasks by a specific status.
     *
     * @param string $status
     *
     * @return VideoProcessingTask[]
     */
    public function findByStatus(string $status): array
    {
        $qb = $this->createQueryBuilder();
        $qb->addOrderBy('task.priority', 'desc');
        $qb->addOrderBy('task.uid', 'asc');

        $qb->setParameter('status', $status);
        $qb->andWhere($qb->expr()->eq('task.status', ':status'));

        $rows = $qb->execute()->fetchAll();
        return array_map([$this, 'serializeTask'], $rows);
    }

    protected function serializeTask(array $row): VideoProcessingTask
    {
        if (isset($this->tasks[$row['uid']])) {
            $this->tasks[$row['uid']]->setDatabaseRow($row);
            return $this->tasks[$row['uid']];
        }
        $uid = $row['uid'];

        try {
            $file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($row['file']);
        } catch (\Exception $e) {
            $row['status'] = 'failed';

            // ToDo update to status "file not found" instead of deleting
            $qb = $this->createQueryBuilder();
            $qb->delete(self::TABLE_NAME);
            $qb->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, Connection::PARAM_INT)));

            $success = $qb->execute() > 0;
            if ($success) {
                // var_dump("delete success");
                unset($this->tasks[$uid]);
            } else {
                // var_dump("delete NO sucess");
            }
        }

        if (file_exists($row['file'])) {
            $file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($row['file']);
        } else {

        }

        $configuration = unserialize($row['configuration']);
        $repository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
        $processedFile = $repository->findOneByOriginalFileAndTaskTypeAndConfiguration($file, 'Video.CropScale', $configuration);

        $task = $processedFile->getTask();
        if (!$task instanceof VideoProcessingTask) {
            $type = is_object($task) ? get_class($task) : gettype($task);
            throw new \RuntimeException("Expected " . VideoProcessingTask::class . ", got $type");
        }

        $task->setDatabaseRow($row);
        $this->tasks[$row['uid']] = $task;
        return $task;
    }

    /**
     * @param VideoProcessingTask $task
     *
     * @return bool
     */
    public function delete(VideoProcessingTask $task): bool
    {
        if ($task->getUid() === null || !isset($this->tasks[$task->getUid()])) {
            return false;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->delete(self::TABLE_NAME);
        $qb->where($qb->expr()->eq('uid', $qb->createNamedParameter($task->getUid(), Connection::PARAM_INT)));

        $success = $qb->execute() > 0;
        if ($success) {
            unset($this->tasks[$task->getUid()]);
            return true;
        } else {
            return false;
        }
    }
}
