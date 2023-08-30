<?php

namespace Faeb\Videoprocessing\Slot;

use Faeb\Videoprocessing\Processing\VideoProcessor;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Service\FileProcessingService;

use Faeb\Videoprocessing\Processing\LocalImageExtendProcessor;

use TYPO3\CMS\Core\Utility\DebugUtility;

class FileProcessingServiceSlot
{
    /**
     * @var VideoProcessor
     */
    protected $videoProcessor;

    /**
     * @var LocalImageExtendProcessor
     */
    protected $localImageExtendProcessor;

    public function injectVideoProcessor(VideoProcessor $videoProcessor): void
    {
        $this->videoProcessor = $videoProcessor;
    }

    /**
     * @param FileProcessingService $fileProcessingService
     * @param DriverInterface $driver
     * @param ProcessedFile $processedFile
     * @param FileInterface $file
     * @param $context
     * @param array $configuration
     *
     * @see \TYPO3\CMS\Core\Resource\Service\FileProcessingService::processFile
     */
    public function preFileProcess(FileProcessingService $fileProcessingService, DriverInterface $driver, ProcessedFile $processedFile, FileInterface $file, $context, array $configuration)
    {
        $needsProcessing = $processedFile->isNew()
            || (!$processedFile->usesOriginalFile() && !$processedFile->exists()) || $processedFile->isOutdated();
        if (!$needsProcessing) {
            return;
        }

        $task = $processedFile->getTask();
        if (!$this->videoProcessor->canProcessTask($task)) {
            return;
        }

        $this->videoProcessor->processTask($task);

        // TYPO3's file processing isn't really meant to be extended.
        // well i guess it was at some point which is why it sort-of™ works.
        // but one of the downsides is that it isn't possible to properly add another processor
        // the workaround is to use this pre processor and mark the file as "processed" even though it isn't
        // that way TYPO3 won't try to use the hardcoded image scaling.
        $task->getTargetFile()->setName($task->getTargetFilename());
    }
}