<?php
// Yes, Daddy
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

        /*
        if (!$file instanceof File) {
            return;
        }
        */


    }
}