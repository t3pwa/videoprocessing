<?php

namespace Faeb\Videoprocessing\Converter;

use Faeb\Videoprocessing\VideoMetadataExtractor;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Format;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Video\X264;

use Faeb\Videoprocessing\Exception\ConversionException;
use Faeb\Videoprocessing\FormatRepository;
use Faeb\Videoprocessing\Processing\VideoProcessingTask;
use Faeb\Videoprocessing\Processing\VideoTaskRepository;

//use Faeb\Videoprocessing\Metadata\Index;
// there is already one
// use Faeb\Videoprocessing\VideoMetadataExtractor;


use MongoDB\BSON\Iterator;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PhpFFmpegConverter extends AbstractVideoConverter
{
    /**
     * @var LocalCommandRunner
     */
//    protected $runner;

    public function __construct()
    {
        // not necessary with php-ffmpeg?!
//        $this->runner = GeneralUtility::makeInstance(LocalCommandRunner::class);
    }

    /**
     * @param VideoProcessingTask $task
     *
     * @throws ConversionException
     * @return \Iterator
     */
    public function process(VideoProcessingTask $task): void
    {

        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        echo "[process in phpffmpeg converter]\n";
        // localSourceFile ???


        var_dump( $task->getSourceFile()->isMissing() );
        if ($task->getSourceFile()->isMissing()) {
            return;
        }


        if (!file_exists($task->getSourceFile()->getForLocalProcessing(false))) {

            $task->getSourceFile()->setMissing(true);
            var_dump( $task->getSourceFile()->isMissing() );
            $task->setStatus('failed');
            // throw new \RuntimeException("errr. localFile ". $task->getSourceFile()->getIdentifier() ." does not exist, ...");
            return;

        }

        $localFile = $task->getSourceFile()->getForLocalProcessing(false);

        //fileInformation ???


        try {
            $localFileProbeInfo = $this->ffprobe($localFile);
            // var_dump("localFileProbeInfo", $localFileProbeInfo);
//            var_dump($task->getConfiguration()['format']);
        } catch (Exception $e) {
            $logger->notice('[PhpFFmpegConverter] [process] probe failed', ['exception' => $e]);
            throw new \RuntimeException("errr. localFile probe failed ...");
        }

        $streams    = $localFileProbeInfo['streams'] ?? [];

        $codec      = $localFileProbeInfo['codec'] ?? "";


        $duration   = $localFileProbeInfo['duration'] ?? 3600.0;

        if (array_key_exists('start', $task->getConfiguration())) {
            $start = $task->getConfiguration()['start'];
        } else {
            $start = 0;
        }

        $duration   = $duration - $start ?? 0;

        $duration   = min($duration, $task->getConfiguration()['duration'] ?? INF);

        $SourceFileMetaData = $task->getSourceFile()->getMetaData();
        if ($SourceFileMetaData->offsetExists('title')) {
            if ( $SourceFileMetaData->offsetGet('title') != '') {
                $SourceFileMetaData->offsetSet('title', $task->getSourceFile()->getIdentifier());
            }
        }

        if ($SourceFileMetaData->offsetExists('duration')) {
            var_dump( 'metaData duration', $SourceFileMetaData->offsetGet('duration') );
            if ( $SourceFileMetaData->offsetGet('duration') != '') {
                $SourceFileMetaData->offsetSet('duration', $duration);
            }

        }

        try {
            $VideoMetadataExtractor = new VideoMetadataExtractor();
            // when extractor configured as service after file upload, we dont need to extract it here, just get it directly from sys_file meta data
            $metaData = $VideoMetadataExtractor->extractMetaData($task->getSourceFile());
            var_dump($metaData);

        } catch (Exception $e) {
            var_dump($e);
            $logger->notice('[PhpFFmpegConverter] [process] failed', ['exception' => $e]);
            throw new \RuntimeException("errr.");
        } finally {
            $logger->notice('[PhpFFmpegConverter] [process] metaData', ['metaData' => $metaData]);
        }




        //targetVideoFileFormat?
//        $format = $task->getConfiguration()['format'];
        // $tempFilename = GeneralUtility::tempnam('video_').'.'.$format;
        $tempFilenameVideo = GeneralUtility::tempnam('video_'); // looks like this: var/transient/video_nyoPOl (wihtout extension)

        try {
            $videoTaskRepository = GeneralUtility::makeInstance(VideoTaskRepository::class);
            // $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);

            $parameters = [
                $localFile,
                $tempFilenameVideo,
                $task->getConfiguration(),
                $streams,
                $codec
            ];

            $this->ffmpeg($parameters, $task);
            $videoTaskRepository->store($task);

            // finish to get processed file
            $this->finishTask($task, $tempFilenameVideo, $streams);

            $processedFile = $task->getTargetFile();
            // var_dump("task targetfile, processed? should probe again after? ", $processedFile->getIdentifier());
            // var_dump("task targetfile, processed? indexed?", $processedFile->isIndexed());



            // second validating probe
            try {
                if (!file_exists( $processedFile->getForLocalProcessing() )) {
                    throw new \RuntimeException("errr. ".   $processedFile->getForLocalProcessing() ." does not exist, ...");
                }
                $processedFileProbeInfo = $this->ffprobe( $processedFile->getForLocalProcessing());
                // var_dump($processedFileProbeInfo);

                // var_dump($localFileProbeInfo['dataformat']);
                // var_dump($processedFileProbeInfo['dataformat']);

                var_dump($localFileProbeInfo['codec']);
                var_dump($processedFileProbeInfo['codec']);

/*
                $processedFileMetaData = $processedFile->getMetaData();
                if ($processedFileMetaData->offsetExists('description')) {
                    // if ( $processedFileMetaData->offsetGet('title') != '') {
                        $processedFileMetaData->offsetSet('description', $processedFileProbeInfo['codec'] );
                    // }
                }
*/






            } catch (Exception $e) {
                var_dump($e);
                $this->logger->notice('[PhpFFmpegConverter] [process] validating probe failed', ['exception' => $e]);
                throw new \RuntimeException("errr.");
            }

        } catch (Exception $e) {
            $this->logger->notice('[PhpFFmpegConverter] [process] failed', ['exception' => $e]);
            throw new \RuntimeException("errr.");
        } finally {
            var_dump("[PhpFFmpegConverter] [process] finally: tempFilename, processed file name idenifier ", $tempFilenameVideo, $processedFile->getIdentifier() );
            GeneralUtility::unlink_tempfile($tempFilenameVideo);
        }


        return;
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
        $ffprobe = FFProbe::create();

        /*
         * // ToDo: Check for obj type is there, otherwise exception ffmpeg not installed
        if (!is_string($ffprobe)) {
            throw new \RuntimeException("FFMpeg\FFProbe not found.");
        }
        */

        $logger->debug('run ffprobe', ['file' => $file]);
        /*
        $response = implode('', iterator_to_array($execution));
        $returnValue = $execution->getReturn();
        */

        //        $response = $ffprobe->isValid($file);
//        echo "response: isValid? ".$response;

        //$returnValue = $ffprobe

        $response['file']   = $file;

        $response['codec']  = $ffprobe
            ->streams($file)            // extracts streams informations
            ->videos()                  // filters video streams
            ->first()                   // returns the first video stream
            ->get('codec_name') // returns the codec_name property
        ;
        $response['duration'] = $ffprobe
            ->format($file)             // extracts file informations
            ->get('duration');  // returns the duration property



        // echo "response: ".$response;
        $logger->debug('ffprobe result', ['output' =>  $response]);
        /*
        if ($returnValue !== 0 && $returnValue !== null) {
            // throw new ConversionException("Probing failed: $commandStr", $returnValue);
            throw new ConversionException("Probing failed: ");
        }
        */

        if (empty($response)) {
            // throw new ConversionException("Probing result empty: $commandStr");
            throw new ConversionException("Probing result empty");
        }




        // $json = json_decode($response, true);
        // $json = json_encode($response);
/*
        if (json_last_error()) {
            $jsonMsg = json_last_error_msg();
            $msg = strlen($response) > 32 ? substr($response, 0, 16) . '...' . substr($response, -8) : $response;
            throw new ConversionException("Probing result ($msg) could not be parsed: $jsonMsg : $commandStr");
        }
*/
        // var_dump("[ffprobe] response ", $response);

        return $response;
    }

    /**
     * @param string $parameters
     * @param VideoProcessingTask $task
     *
     * @return \Iterator
     * @throws ConversionException
     */
    protected function ffmpeg(array $parameters, VideoProcessingTask $task): void
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

//        $videoTaskRepository = GeneralUtility::makeInstance(VideoTaskRepository::class);


        $ffmpeg = FFMpeg::create();
        $logger->debug(sprintf('[PhpFFmpegConverter] run php-ffmpeg with parameters %s', json_encode($parameters) ) );
        $video = $ffmpeg->open($parameters[0]);

        var_dump($parameters[4]);
        var_dump($parameters[2]['format']);

        // $process = $this->runner->run($commandStr);
        /*
        var_dump("parameters in php-ffmpeg-converter", $parameters);
        var_dump("parameters in php-ffmpeg-converter parameter 1 (target): ". $parameters[1]);
        var_dump("parameters in php-ffmpeg-converter 0 (source): ". $parameters[0]);
        var_dump("params2", $parameters[2]);
        var_dump("params2", $parameters[2]["format"]);
        */
        // var_dump("process", $process);
//        var_dump("parameters in php-ffmpeg-converter format", $format);
//        var_dump("parameters codec", $parameters[4]);

        if ($parameters[2]["format"] == 'h264') { // codec
//            $format = new \FFMpeg\Format\Video\X264();
            $format = new \FFMpeg\Format\Video\X264(
                audioCodec: 'aac', // 'libopus'
                videoCodec: 'libx264'
            );
            /*
            $format
                ->setKiloBitrate(1000)
                ->setAudioChannels(2)
                ->setAudioKiloBitrate(256)
            ;
            */

        }

        if ($parameters[2]['format'] == 'webm') { // codec
            $format = new \FFMpeg\Format\Video\WebM(
                audioCodec: 'libvorbis',
                videoCodec: 'libvpx'
            );
        }



        // var_dump($task->getConfiguration());
        // var_dump($task->getLastProgress());
        $format->on('progress', function ($video, $format, $percentage) use ($task) {
            echo "$percentage% transcoded\n";
            $task->addProgressStep((float)($percentage/100));
            // $videoTaskRepository->store($task);


        } );
        echo "after format on progress\n";
        // var_dump($task->getConfiguration());
        // var_dump($task->getLastProgress());

        // var_dump("parameters in php-ffmpeg-converter format", $format);

        // $output = '[output]';
        // This is a command runner, not to be used with php-ffmpeg-objects!
//
//            var_dump("parameters in php-ffmpeg-converter 1a (target): ". $parameters[0]);
//            var_dump("parameters in php-ffmpeg-converter 1b (target): ". $parameters[1]);
//            var_dump("parameters in php-ffmpeg-converter 1c (target): ". $parameters[2]['format']);

        // put poster grabbing here

        /* poster test in video **************************************** */
        /*

        // ***************************************
        // the php-ffmpeg way, works with t3v11, php82, php-ffmpeg:1.1
        // https://stackoverflow.com/questions/2043007/generate-preview-image-from-video-file
        // https://github.com/PHP-FFMpeg/PHP-FFMpeg
        */

        // ToDo from settings, or better calculate on duration
        $timeFrame = 3; // seconds from start for frame capture to use as poster image
        $frame = $video->frame(TimeCode::fromSeconds($timeFrame));
        // ->save($extractedImagePath);

        // https://hotexamples.com/examples/ffmpeg.coordinate/TimeCode/-/php-timecode-class-examples.html
        // https://hotexamples.com/examples/ffmpeg.coordinate/TimeCode/-/php-timecode-class-examples.html

//        $poster = $processedFile->getIdentifier();
//        var_dump("posterFilePath processed file", $poster);


//        $frame->save("public/fileadmin/" . $parameters[1].'jpg');

        $frame->save($parameters[1].'.png');

        $video->save($format, $parameters[1].'.'.$parameters[2]["format"]);


        // because updating referenced values in unit tests is hard, null is also checked here
//        var_dump($process->getReturn());
//        $returnValue = $process->getReturn();

        /*
        if ($returnValue !== 0) {
            throw new ConversionException("Bad return value ($returnValue): $commandStr\n$output");
            // ToDo add reason to task, when failed.
            // $task->set $output
        }
        */

    }
}
