<?php

namespace Faeb\Videoprocessing\Converter;


use Faeb\Videoprocessing\Exception\ConversionException;
use Faeb\Videoprocessing\Processing\VideoProcessingTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class NoopConverter extends AbstractVideoConverter
{
    /**
     * This method is called new tasks to update their status and potentially process them in a blocking fashion.
     *
     * If you process files in a local process do that here.
     * You can repeatably persist the task object with a new status update for feedback.
     *
     * If you use an api or external service to process the file you can ask for a status update here.
     * This method will be called every time the process command is executed until the task is finished or failed.
     *
     * @param VideoProcessingTask $task
     *
     * @throws ConversionException
     */
    public function process(VideoProcessingTask $task): void
    {
        $filename = GeneralUtility::tempnam('video');
        $this->finishTask($task, $filename, []);
    }
}
