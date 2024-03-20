<?php

namespace Faeb\Videoprocessing\Converter;


use Faeb\Videoprocessing\FormatRepository;
use Faeb\Videoprocessing\Processing\VideoProcessingTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractVideoConverter implements VideoConverterInterface
{
    public function start(VideoProcessingTask $task): void
    {
    }

    protected function finishTask(VideoProcessingTask $task, string $tempFilename, array $streams)
    {
        // the name has to be set before anything
        $processedFile = $task->getTargetFile();
        $processedFile->setName($task->getTargetFilename());

        // the properties also have to be set before writing the file
        $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);
        $properties = $formatRepository->getProperties($task->getConfiguration(), $streams);
        $processedFile->updateProperties($properties + ['checksum' => $task->getConfigurationChecksum()]);

        // now actually update the file
        var_dump("$tempFilename with extesnion?", $tempFilename);
//        var_dump($task->getConfiguration()['format']);
//        $format = $task->getConfiguration()['format'];

        $processedFile->updateWithLocalFile($tempFilename.'.'.$task->getConfiguration()['format']);
        // $processedFile->updateWithLocalFile($tempFilename);

        $task->setExecuted(true);

        // return $processedFile;
    }

    public function update(VideoProcessingTask $task): void
    {
    }
}
