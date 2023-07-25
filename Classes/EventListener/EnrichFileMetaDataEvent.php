<?php

declare(strict_types=1);

namespace Faeb\Videoprocessing\EventListener;

// use Faeb\Videoprocessing\Processing\VideoProcessor;
use Faeb\Videoprocessing\VideoMetadataExtractor;


final class EnrichFileMetaDataEvent
{

    private VideoMetadataExtractor $videoMetadataExtractor;

    public function injectVideoProcessor(VideoMetadataExtractor $videoMetadataExtractor): void
    {
        $this->videoMetadataExtractor = VideoMetadataExtractor;
    }

    public function __invoke(EnrichFileMetaDataEvent $event): void
    {

        var_dump( "EnrichFileMetaDataEvent getFileUid", $event->getFileUid() );
        var_dump( "EnrichFileMetaDataEvent getRecord", $event->getRecord() );

        $file = GeneralUtility::makeInstance(FileRepository::class)->findByUid($event->getFileUid());
        if (!$file instanceof File) {
            var_dump("[EnrichFileMetaDataEvent] not a file, return");
            return;
        }


    }
}