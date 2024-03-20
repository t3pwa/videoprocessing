<?php
// Yes, Daddy
declare(strict_types=1);

namespace Faeb\Videoprocessing\EventListener;

use Faeb\Videoprocessing\Processing\VideoProcessor;
use Faeb\Videoprocessing\VideoMetadataExtractor;
use TYPO3\CMS\Core\Utility\DebugUtility;


final class EnrichFileMetaDataEvent
{

    private VideoMetadataExtractor $videoMetadataExtractor;

    public function injectVideoProcessor(VideoMetadataExtractor $videoMetadataExtractor): void
    {
        $this->videoMetadataExtractor = $videoMetadataExtractor;
    }

    public function __invoke(EnrichFileMetaDataEvent $event): void
    {
        \TYPO3\CMS\Core\Utility\DebugUtility::debug("EnrichFileMetaDataEvent");

        if (!$file instanceof File) {
            return;
        }

    }
}