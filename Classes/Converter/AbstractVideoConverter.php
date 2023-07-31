<?php

namespace Faeb\Videoprocessing\Converter;


use Faeb\Videoprocessing\FormatRepository;
use Faeb\Videoprocessing\Processing\VideoProcessingTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractVideoConverter implements VideoConverterInterface
{
    public function start(VideoProcessingTask $task): void
    {
        print ("[VideoProcessingTask start] ");
    }

    protected function finishTask(VideoProcessingTask $task, string $tempFilename, array $streams)
    {
        // the name has to be set before anything else or else random errors
        $processedFile = $task->getTargetFile();
        $processedFile->setName($task->getTargetFilename());

        // the properties also have to be set before writing the file or else... guess
        $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);
        $properties = $formatRepository->getProperties($task->getConfiguration(), $streams);
        $processedFile->updateProperties($properties + ['checksum' => $task->getConfigurationChecksum()]);

        // now actually update the file
        $processedFile->updateWithLocalFile($tempFilename);
        $task->setExecuted(true);

        // return $processedFile;
    }

    public function update(VideoProcessingTask $task): void
    {
    }
}
