<?php

namespace Faeb\Videoprocessing\Converter;


use Faeb\Videoprocessing\Exception\ConversionException;
use Faeb\Videoprocessing\FormatRepository;
use Faeb\Videoprocessing\Processing\VideoProcessingTask;
use Faeb\Videoprocessing\Processing\VideoTaskRepository;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LocalFFmpegConverter extends AbstractVideoConverter
{
    /**
     * @var LocalCommandRunner
     */
    protected $runner;

    public function __construct()
    {
        $this->runner = GeneralUtility::makeInstance(LocalCommandRunner::class);
    }

    /**
     * @param VideoProcessingTask $task
     *
     * @throws ConversionException
     */
    public function process(VideoProcessingTask $task): void
    {
        $localFile = $task->getSourceFile()->getForLocalProcessing(false);
        $info = $this->ffprobe($localFile);
        $streams = $info['streams'] ?? [];

        $duration = $info['format']['duration'] ?? 3600.0;
        $duration = $duration - $task->getConfiguration()['start'] ?? 0;
        $duration = min($duration, $task->getConfiguration()['duration'] ?? INF);

        $tempFilename = GeneralUtility::tempnam('video');
        try {
            $videoTaskRepository = GeneralUtility::makeInstance(VideoTaskRepository::class);
            $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);

            $ffmpegCommand = $formatRepository->buildParameterString($localFile, $tempFilename, $task->getConfiguration(), $streams);
            $progress = $this->ffmpeg($ffmpegCommand);
            foreach ($progress as $time) {
                $progress = $time / $duration;
                if ($progress > 1.0) {
                    continue;
                }

                $task->addProgressStep($progress);
                $videoTaskRepository->store($task);
            }

            // make the progress bar end
            $task->addProgressStep(1.0);
            $videoTaskRepository->store($task);

            $this->finishTask($task, $tempFilename, $streams);
        } finally {
            GeneralUtility::unlink_tempfile($tempFilename);
        }
    }

    /**
     * @param string $file
     *
     * @return array
     * @throws ConversionException
     */
    protected function ffprobe(string $file): array
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $ffprobe = $this->runner->getCommand('ffprobe');
        if (!is_string($ffprobe)) {
            throw new \RuntimeException("ffprobe not found.");
        }

        $parameters = ['-v', 'quiet', '-print_format', 'json', '-show_streams', '-show_format', $file];
        $commandStr = $ffprobe . ' ' . implode(' ', array_map('escapeshellarg', $parameters));
        $logger->info('run ffprobe command', ['command' => $commandStr]);

        $execution = $this->runner->run($commandStr);
        $response = implode('', iterator_to_array($execution));
        $returnValue = $execution->getReturn();
        $logger->debug('ffprobe result', ['output' => preg_replace('#\s{2,}#', ' ', $response)]);

        if ($returnValue !== 0 && $returnValue !== null) {
            throw new ConversionException("Probing failed: $commandStr", $returnValue);
        }

        if (empty($response)) {
            throw new ConversionException("Probing result empty: $commandStr");
        }

        $json = json_decode($response, true);
        if (json_last_error()) {
            $jsonMsg = json_last_error_msg();
            $msg = strlen($response) > 32 ? substr($response, 0, 16) . '...' . substr($response, -8) : $response;
            throw new ConversionException("Probing result ($msg) could not be parsed: $jsonMsg : $commandStr");
        }

        return $json;
    }

    /**
     * @param string $parameters
     *
     * @return \Iterator
     * @throws ConversionException
     */
    protected function ffmpeg(string $parameters): \Iterator
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $ffmpeg = $this->runner->getCommand('ffmpeg');
        if (!is_string($ffmpeg)) {
            throw new \RuntimeException("ffmpeg not found.");
        }

        // if possible run ffmpeg with lower priority
        // this is because i assume you are using it on the webserver
        // which should care more about delivering pages than about converting the video
        // if the utility is not found than just ignore this priority shift
        $nice = $this->runner->getCommand('nice');
        if (is_string($nice)) {
            $ffmpeg = "$nice $ffmpeg";
        }

        $commandStr = "$ffmpeg -loglevel warning -stats $parameters";
        $logger->notice("run ffmpeg command", ['command' => $commandStr]);
        $process = $this->runner->run($commandStr);
        $output = '';
        foreach ($process as $line) {
            $output .= $line;
            if (preg_match('#time=(\d+):(\d{2}):(\d{2}).(\d{2})#', $line, $matches)) {
                yield $matches[1] * 3600 + $matches[2] * 60 + $matches[3] + $matches[4] / 100;
            }
        }
        $logger->debug('ffmpeg result', ['output' => $output]);

        // because updating referenced values in unit tests is hard, null is also checked here
        $returnValue = $process->getReturn();
        if ($returnValue !== 0) {
            throw new ConversionException("Bad return value ($returnValue): $commandStr\n$output");
        }
    }
}
