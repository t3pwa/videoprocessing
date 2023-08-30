<?php

declare(strict_types=1);

namespace Faeb\Videoprocessing\EventListener;

//use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;
use Faeb\Videoprocessing\Exception\FormatException;
use Faeb\Videoprocessing\Processing\VideoProcessor;

use Faeb\Videoprocessing\Processing\LocalImageExtendProcessor;

// use Faeb\Videoprocessing\Processing\VideoTaskRepository;
use TYPO3\CMS\Core\Resource\Event\BeforeFileProcessingEvent;
use TYPO3\CMS\Core\Resource\Event\FileProcessingEvent;

use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use TYPO3\CMS\Core\Messaging\FlashMessage;

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
final class ImageFileProcessingEvent
{

    /** @var \TYPO3\CMS\Core\Log\Logger */
    protected $logger;


    /**
     * @var LocalImageExtendProcessor
     */
    protected $localImageExtendProcessor;

    // no need for video processor in image processing?!
    /*
    public function __construct(
        VideoProcessor $videoProcessor,
        // LocalImageExtendProcessor $localImageExtendProcessor,
//        \TYPO3\CMS\Core\Log\LogManager $logManager
    ) {
        $this->videoProcessor = $videoProcessor;
        // $this->localImageExtendProcessor = $localImageExtendProcessor;
//        $this->logger = $logManager->getLogger(self::class);
    }
    */

    protected function getLogger(): LoggerInterface
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    protected function getLocalImageExtendProcessor() {
        // return GeneralUtility::makeInstance(LocalImageExtendProcessor::class)->getLocalImageExtendProcessor(__CLASS__);
        return GeneralUtility::makeInstance(LocalImageExtendProcessor::class);
    }


    public function __invoke(BeforeFileProcessingEvent $event): void
    // public function __invoke(AfterFileProcessingEvent $event): void
    {

        // var_dump("invoke");

        $localImageExtendProcessor = $this->getLocalImageExtendProcessor();
        // \TYPO3\CMS\Core\Utility\DebugUtility::debug($event);
        $processedFile = $event->getProcessedFile();
        $needsProcessing = $processedFile->isNew()
            || (!$processedFile->usesOriginalFile()
            && !$processedFile->exists()) || $processedFile->isOutdated();

        if (!$needsProcessing) {
            // print ("".$processedFile->getPublicUrl()."<br> exists, but process anyways!");
//            return;
        } else {
            // \TYPO3\CMS\Core\Utility\DebugUtility::debug("BeforeFileProcessingEvent");
/*
            $message = GeneralUtility::makeInstance(FlashMessage::class,
                'Needs processing',
                'ImageFileProcessingEvent',
                FlashMessage::INFO,
                true
            );

            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $messageQueue->addMessage($message);
*/
            $configuration = $event->getConfiguration();

//            var_dump("image configuration of event", $configuration );

        }

        $task = $processedFile->getTask();

        /* Video not needed here, just image, right?
        if (!$this->videoProcessor->canProcessTask($task)) {
            return;
        }
        */

        // TYPO3's file processing isn't really meant to be extended.
        // well i guess it was at some point which is why it sort-of™ works.
        // but one of the downsides is that it isn't possible to properly add another processor
        // the workaround is to use this pre processor and mark the file as "processed" even though it isn't
        // that way TYPO3 won't try to use the hardcoded image scaling.

        // $task->getTargetFile()->setName($task->getTargetFilename());

        if ( $localImageExtendProcessor->canProcessTask($task) ) {
            $task->getTargetFile()->setName(
                $task->getSourceFile()->getNameWithoutExtension()
//                . '_' . $task->getConfigurationChecksum()
//                . '.' . $task->getTargetFileExtension()
                . '.png'
            );
/*
            return $this->getSourceFile()->getNameWithoutExtension()
                . '_' . $this->getConfigurationChecksum()
                . '.' . $this->getTargetFileExtension();
*/

            $localImageExtendProcessor->processTask($task);
        }
    }
}