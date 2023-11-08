<?php

namespace Faeb\Videoprocessing\Processing;


use Faeb\Videoprocessing\FormatRepository;
use Faeb\Videoprocessing\Preset\PresetInterface;
use TYPO3\CMS\Core\Resource\Processing\AbstractTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use TYPO3\CMS\Core\Resource;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Service\ConfigurationService;


class VideoProcessingTask extends AbstractTask
{
    const TYPE = 'Video';
    // const TYPE = 'Videoprocessing';
    const NAME = 'CropScale';
    const STATUS_NEW = 'new';

    const STATUS_PROCESSING = 'processing';
    const STATUS_FINISHED = 'finished';
    const STATUS_FAILED = 'failed';


    /**
     * @var Resource\ProcessedFile
     */
    protected $targetFile;

    /**
     * @var Resource\File
     */
    protected $sourceFile;

    /**
     * @var array
     */
    protected $configuration = [];

    /**
     * @var int|null
     */
    protected $uid = null;

    /**
     * @var string
     */
    protected $type = self::TYPE;

    /**
     * @var string
     */
    protected $name = self::NAME;

    /**
     * @var array
     */
    protected $progress = [];

    /**
     * @param ProcessedFile $targetFile
     */
    public function __construct(
        ProcessedFile $targetFile,
        array $configuration
    )
    {

        // print ("Video processing task constructor ");

        $this->targetFile = $targetFile;
        $this->sourceFile = $targetFile->getOriginalFile();
        $this->configuration = $configuration;
    }


    /**
     * Checks if the given configuration is sensible for this task, i.e. if all required parameters
     * are given, within the boundaries and don't conflict with each other.
     *
     * @param $configuration
     *
     * @return bool
     */
    // protected function isValidConfiguration(array $configuration)
    protected function isValidConfiguration($configuration)
    {
        return true;
    }

    /**
     * Returns TRUE if the file has to be processed at all, such as e.g. the original file does.
     *
     * Note: This does not indicate if the concrete ProcessedFile attached to this task has to be (re)processed.
     * This check is done in ProcessedFile::isOutdated(). @todo isOutdated()/needsReprocessing()?
     *
     * @return bool
     */
    public function fileNeedsProcessing(): bool
    {
        return true;
    }

    public function getTargetFileExtension(): string
    {
        $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);
        $definition = $formatRepository->findFormatDefinition($this->getConfiguration());
        return $definition['fileExtension'];
    }

    public function getStatus(): string
    {
        if (!$this->isExecuted()) {
            return self::STATUS_NEW;

        }
/*
        if ($this->isExecuted()) {
            // return self::STATUS_PROCESSING;
            // return self::STATUS_NEW;
            return self::STATUS_FINISHED;

        }
*/


        if ($this->isSuccessful()) {
            return self::STATUS_FINISHED;
        }

        return self::STATUS_FAILED;
    }

    public function setStatus(string $status)
    {
        switch ($status) {
            case self::STATUS_NEW:
                $this->executed = false;
                $this->successful = false;
                break;

            case self::STATUS_PROCESSING:
                $this->executed = true;
                $this->successful = false;
                break;

            case self::STATUS_FAILED:
                $this->setExecuted(false);
                break;
            case self::STATUS_FINISHED:
                $this->setExecuted(true);
                break;
            default:
                throw new \RuntimeException("Status $status does not exist");
        }
    }

    public function getPriority(): int
    {
        if (isset($this->getConfiguration()['priority'])) {
            return $this->getConfiguration()['priority'];
        }

        $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);
        $definition = $formatRepository->findFormatDefinition($this->getConfiguration());
        if (isset($definition['priority'])) {
            return $definition['priority'];
        }

        return 0;
    }

    public function addProgressStep(float $progress, float $timestamp = null): int
    {
        if ($progress < 0.0 || $progress > 1.0) {
            throw new \OutOfRangeException("Progress must be between 0 and 1, got $progress.");
        }

        if ($timestamp === null) {
            $timestamp = microtime(true);
        }

        $newEntry = [
            'timestamp' => $timestamp,
            'progress' => round($progress, 5),
        ];

        // put the new entry at the position
        $i = count($this->progress);
        while (true) {
            if (--$i < 0 || $this->progress[$i]['timestamp'] <= $timestamp) {
                $insertionIndex = $i + 1;
                array_splice($this->progress, $insertionIndex, 0, [$newEntry]);
                return $insertionIndex;
            }
        }

        throw new \LogicException("This shouldn't be reached");
    }

    public function getProgressSteps(): array
    {
        return $this->progress;
    }

    public function getLastProgress(): float
    {
        if (empty($this->progress)) {
            return 0.0;
        }

        return end($this->progress)['progress'];
    }

    public function getEstimatedRemainingTime(): float
    {
        if (count($this->progress) < 2) {
            return 60 * 60 * 24;
        }

        // TODO more steps should be taken into consideration to reduce variance
        $steps = array_slice($this->progress, -2);
        $timespan = $steps[1]['timestamp'] - $steps[0]['timestamp'];
        $progressSpan = $steps[1]['progress'] - $steps[0]['progress'];
        $remainingProgress = 1 - $steps[1]['progress'];

        if ($remainingProgress <= 0.0) {
            return 0.0;
        }

        $remainingTime = $timespan / ($progressSpan / $remainingProgress);
        // secretly add a bit so that the estimate is actually too high ~ better correct down than up
        return $remainingTime * 1.05;
    }

    public function getLastUpdate(): int
    {
        if (empty($this->progress)) {
            return 0;
        }

        return end($this->progress)['timestamp'];
    }

    public function getProcessingDuration(): float
    {
        $progressSteps = $this->getProgressSteps();
        if (count($progressSteps) < 2) {
            return 0;
        }

        return end($progressSteps)['timestamp'] - reset($progressSteps)['timestamp'];
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    /**
     * @param array $row
     * @internal this method is meant for deserialization
     */
    public function setDatabaseRow(array $row)
    {
        $this->uid = $row['uid'];
        $this->setStatus($row['status']);
        $this->progress = json_decode($row['progress'], true) ?: [];
    }
}
