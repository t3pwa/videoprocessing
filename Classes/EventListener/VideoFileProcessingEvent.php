<?php

declare(strict_types=1);

namespace Faeb\Videoprocessing\EventListener;

//use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;
use Faeb\Videoprocessing\Exception\FormatException;
use Faeb\Videoprocessing\Processing\VideoProcessor;

use Faeb\Videoprocessing\Processing\LocalImageExtendProcessor;

// use Faeb\Videoprocessing\Processing\VideoTaskRepository;

use TYPO3\CMS\Core\Resource\Event\BeforeFileProcessingEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedToIndexEvent;

use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
#

/*
 * recordPostRetrieval
 * EnrichFileMetaDataEvent
BeforeFileProcessingEvent
AfterFileAddedEvent
AfterFileAddedToIndexEvent
AfterFileContentsSetEvent
AfterFileCopiedEvent
AfterFileCreatedEvent
AfterFileDeletedEvent
AfterFileMarkedAsMissingEvent
AfterFileMetaDataCreatedEvent
AfterFileMetaDataDeletedEvent
AfterFileMetaDataUpdatedEvent
AfterFileMovedEvent
AfterFileProcessingEvent
AfterFileRemovedFromIndexEvent
AfterFileRenamedEvent
AfterFileReplacedEvent
AfterFileUpdatedInIndexEvent
AfterFolderAddedEvent
AfterFolderCopiedEvent
AfterFolderDeletedEvent
AfterFolderMovedEvent
AfterFolderRenamedEvent
AfterResourceStorageInitializationEvent
BeforeFileAddedEvent
BeforeFileContentsSetEvent
BeforeFileCopiedEvent
BeforeFileCreatedEvent
BeforeFileDeletedEvent
BeforeFileMovedEvent
BeforeFileProcessingEvent
BeforeFileRenamedEvent
BeforeFileReplacedEvent
BeforeFolderAddedEvent
BeforeFolderCopiedEvent
BeforeFolderDeletedEvent
BeforeFolderMovedEvent
BeforeFolderRenamedEvent
BeforeResourceStorageInitializationEvent
EnrichFileMetaDataEvent
GeneratePublicUrlForResourceEvent
ModifyIconForResourcePropertiesEvent
SanitizeFileNameEven
*/


/**
 * FileProcessingEvent
 * https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/ApiOverview/Events/Events/Core/Resource/EnrichFileMetaDataEvent.html
 *
 */
final class VideoFileProcessingEvent
{

    /** @var \TYPO3\CMS\Core\Log\Logger */
    protected $logger;


    /**
     * @var LocalImageExtendProcessor
     */
    protected $localImageExtendProcessor;


    public function __construct(
        VideoProcessor $videoProcessor,
        // LocalImageExtendProcessor $localImageExtendProcessor,
//        \TYPO3\CMS\Core\Log\LogManager $logManager
    ) {
        $this->videoProcessor = $videoProcessor;
        // $this->localImageExtendProcessor = $localImageExtendProcessor;
//        $this->logger = $logManager->getLogger(self::class);
    }

    protected function getLogger(): LoggerInterface
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    protected function getLocalImageExtendProcessor() {
        // return GeneralUtility::makeInstance(LocalImageExtendProcessor::class)->getLocalImageExtendProcessor(__CLASS__);
        return GeneralUtility::makeInstance(LocalImageExtendProcessor::class);
    }

    public function __invoke(BeforeFileProcessingEvent $event): void
    //public function __invoke(AfterFileAddedToIndexEvent $event): void
    {

        // not used here
        // using this->videoprocessor not image
        // $localImageExtendProcessor = $this->getLocalImageExtendProcessor();
        // \TYPO3\CMS\Core\Utility\DebugUtility::debug($event);

        $processedFile = $event->getProcessedFile();

        $needsProcessing = $processedFile->isNew()
            || (!$processedFile->usesOriginalFile()
            && !$processedFile->exists()) || $processedFile->isOutdated();

        if (!$needsProcessing) {
//            print ("".$processedFile->getPublicUrl()." IMAGE VIDEO needs NOT processing <br>");
            return;
        } else {
            // print ("".$processedFile->getPublicUrl()." IMAGE VIDEO DOES need processing <br>");
            // \TYPO3\CMS\Core\Utility\DebugUtility::debug("BeforeFileProcessingEvent");
            // temp. not used
            $configuration = $event->getConfiguration();
        }

/*

        $allowedMimeTypes = array("video/mp4", "video/webm");
        if (in_array(mime_content_type($processedFile->getPublicUrl()), $allowedMimeTypes)) {
            var_dump('allowed');
        } else {
            print ("not allowed");
            // return;
        }
*/

        $task = $processedFile->getTask();
        if (!$this->videoProcessor->canProcessTask($task)) {
            return;
        }
        // only here, disabled in  Image Process for testing
        $this->videoProcessor->processTask($task);

//        \TYPO3\CMS\Core\Utility\DebugUtility::debug("Before localImageExtendProcessor canProcess? ");
//        \TYPO3\CMS\Core\Utility\DebugUtility::debug( $localImageExtendProcessor->canProcessTask($task) );


        // TYPO3's file processing isn't really meant to be extended.
        // well i guess it was at some point which is why it sort-of™ works.
        // but one of the downsides is that it isn't possible to properly add another processor
        // the workaround is to use this pre processor and mark the file as "processed" even though it isn't
        // that way TYPO3 won't try to use the hardcoded image scaling.

        // $task->getTargetFile()->setName($task->getTargetFilename());
/*
        if ( $localImageExtendProcessor->canProcessTask($task) ) {
            $task->getTargetFile()->setName(
                $task->getSourceFile()->getNameWithoutExtension()
                . '.png'
            );

            // local Image procesing in other event
            $localImageExtendProcessor->processTask($task);
        }
*/

    }
}