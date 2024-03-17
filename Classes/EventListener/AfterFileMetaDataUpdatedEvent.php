<?php
// Yes, Daddy
declare(strict_types=1);

namespace Faeb\Videoprocessing\EventListener;

use Faeb\Videoprocessing\VideoMetadataExtractor;
use TYPO3\CMS\Core\Utility\DebugUtility;

final class AfterFileMetaDataUpdatedEvent
{

    // private VideoMetadataExtractor $videoMetadataExtractor;
    /*
    public function injectVideoProcessor(VideoMetadataExtractor $videoMetadataExtractor): void
    {
        $this->videoMetadataExtractor = $videoMetadataExtractor;
    }
    */

    public function __invoke(AfterFileMetaDataUpdatedEvent $event): void
    {
        \TYPO3\CMS\Core\Utility\DebugUtility::debug("AfterFileMetaDataUpdatedEvent");

        /*
        if (!$file instanceof File) {
            return;
        }
        */


    }
}