<?php

declare(strict_types=1);

namespace Faeb\Videoprocessing\EventListener;

// use TYPO3\CMS\FrontendLogin\Event\PasswordChangeEvent;

//use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;
use Faeb\Videoprocessing\Processing\VideoProcessor;
use Faeb\Videoprocessing\Processing\VideoTaskRepository;
use TYPO3\CMS\Core\Resource\Event\BeforeFileProcessingEvent;


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
final class FileProcessingEvent
{
    public function __construct(
        VideoProcessor $videoProcessor
    ) {
        $this->videoProcessor = $videoProcessor;
    }

    public function __invoke(BeforeFileProcessingEvent $event): void
    {

//        $event->getProcessedFile();
        $processedFile = $event->getProcessedFile();
        // var_dump( $processedFile->usesOriginalFile() ) ;
        // var_dump( $processedFile->isNew() );
        // $event->setProcessedFile();
        // $event->getDriver();
        // $event->getFile();
        // var_dump( $event->getTaskType() );

        $needsProcessing = $processedFile->isNew()
            || (!$processedFile->usesOriginalFile()
            && !$processedFile->exists()) || $processedFile->isOutdated();

        if (!$needsProcessing) {
            // var_dump("no processing needed");
            return;
        } else {
            // var_dump("yes daddy, please process");
            $configuration = $event->getConfiguration();
//            var_dump($configuration);
        }

        $task = $processedFile->getTask();
        if (!$this->videoProcessor->canProcessTask($task)) {
            // var_dump("no cant do processing");
            return;
        } else {
            // var_dump("processTask()");
        }
        $this->videoProcessor->processTask($task);

        // TYPO3's file processing isn't really meant to be extended.
        // well i guess it was at some point which is why it sort-of™ works.
        // but one of the downsides is that it isn't possible to properly add another processor
        // the workaround is to use this pre processor and mark the file as "processed" even though it isn't
        // that way TYPO3 won't try to use the hardcoded image scaling.
        var_dump($task->getTargetFile()->setName($task->getTargetFilename()));
        $task->getTargetFile()->setName($task->getTargetFilename());

    }

}