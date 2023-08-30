<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Faeb\Videoprocessing\Processing;

use Faeb\Videoprocessing\FormatRepository;
use TYPO3\CMS\Core\Core\Environment;
use Faeb\Videoprocessing\Converter\LocalCommandRunner;
use Faeb\Videoprocessing\Exception\ConversionException;
use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Imaging\ImageMagickFile;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Video\X264;

use TYPO3\CMS\Core\Log\LogManager;

use TYPO3\CMS\Core\Utility\DebugUtility;




/**
 * Helper for creating local image previews using TYPO3s image processing classes.
 */
class LocalVideoPreviewHelper
{
    /**
     * @var LocalCommandRunner
     */
    protected $runner;

    /**
     * Default preview configuration
     *
     * @var array
     */
    protected static $defaultConfiguration = [
        'width' => 512, // 64
        'height' => 512, // 64
    ];

    public function __construct()
    {
        $this->runner = GeneralUtility::makeInstance(LocalCommandRunner::class);
    }

    /**
     * Enforce default configuration for preview processing
     *
     * @param array $configuration
     * @return array
     */
    public static function preProcessConfiguration(array $configuration): array
    {
        $configuration = array_replace(static::$defaultConfiguration, $configuration);
        $configuration['width'] = MathUtility::forceIntegerInRange($configuration['width'], 1, 1000);
        $configuration['height'] = MathUtility::forceIntegerInRange($configuration['height'], 1, 1000);

        return array_filter(
            $configuration,
            static function ($value, $name) {
                return !empty($value) && in_array($name, ['width', 'height'], true);
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * This method actually does the processing of files locally
     *
     * @param TaskInterface $task
     * @return array|null
     */
    public function process(TaskInterface $task)
    {
//        var_dump("<hr>LocalVideoPreviewHelper, process<hr>");
        $sourceFile = $task->getSourceFile();
        // var_dump("[source File] in LocalVideoPreview process",$sourceFile->getName());
        $configuration = static::preProcessConfiguration($task->getConfiguration());

        // Do not scale up if the source file has a size and the target size is larger
        if ($sourceFile->getProperty('width') > 0 && $sourceFile->getProperty('height') > 0
            && $configuration['width'] > $sourceFile->getProperty('width')
            && $configuration['height'] > $sourceFile->getProperty('height')) {
            return null;
        }

        return $this->generatePreviewFromFile(
            $sourceFile,
            $configuration,
            $this->getTemporaryFilePath($task),
            $task->getTargetFileName()
        );
    }

    /**
     * Does the heavy lifting prescribed in processTask()
     * except that the processing can be performed on any given local image
     *
     * @param TaskInterface $task
     * @param string $localFile
     * @return array|null
     */

/*
    public function processWithLocalFile(TaskInterface $task, string $localFile): ?array
    {
        return $this->generatePreviewFromLocalFile(
            $localFile,
            $task->getConfiguration(),
            $this->getTemporaryFilePath($task)
        );
    }
*/

    /**
     * Returns the path to a temporary file for processing
     *
     * @param TaskInterface $task
     * @return non-empty-string
     */
    protected function getTemporaryFilePath(TaskInterface $task)
    {
        return GeneralUtility::tempnam('preview_', '_'.$task->getTargetFileName());


    }

    /**
     * Generates a preview for a video file
     * first frame from the video file saved as temp png
     *
     * @param File $file The source file
     * @param array $configuration Processing configuration
     * @param string $targetFilePath Output file path
     * @return array
     */
    protected function generatePreviewFromFile(
        File $file,
        array $configuration,
        string $tempFilePath,
        string $targetFilePath
    )
    {
        // print ('[generate temp Preview Frame from file] temp targetFilePath'. $targetFilePath);
        // print ('>>> final targetFilePath from task with checksum?'. $targetFilePath);
        // print ('>>> temp '. $tempFilePath. "<<<");

        // Check file extension, only video
        if ($file->getType() !== File::FILETYPE_IMAGE && !$file->isImage()) {

/*        if (
            $file->getType() !== File::FILETYPE_VIDEO
        //    && !$file->isImage()
        ) {
*/

    //          var_dump($configuration);

//            try {
                // ToDo get Storage dyn instead of hard coded fileadmin

                $localFile = Environment::getPublicPath() . '/fileadmin' . $file->getIdentifier();
                $localFilePreview = Environment::getPublicPath() . '/fileadmin' . $this->str_lreplace('/', '/preview_',$file->getIdentifier());
                $localFilePreview = str_replace('.mp4', '.png' , $localFilePreview);

                /*
                $task->getSourceFile()->getNameWithoutExtension()
                . '_' . $task->getConfigurationChecksum()
                // . '.' . $task->getTargetFileExtension()
                . '.png'
                */

                $info = $this->ffprobe( $localFile );
                $streams = $info['streams'] ?? [];
                $duration = $info['format']['duration'] ?? 3600.0;
//                print("<br><strong>Duration:</strong> ". intval($duration) . "<br>" );
                $startFrame = intval($duration / 2 );

                // Create a default image
                /*
                $graphicalFunctions = GeneralUtility::makeInstance(GraphicalFunctions::class);
                $graphicalFunctions->getTemporaryImageWithText(
                    $targetFilePath,
                    'Not imagefile!',
                    'No ext!',
                    $file->getName()
                );
                */

                // $videoTaskRepository = GeneralUtility::makeInstance(VideoTaskRepository::class);
                // $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);

                $parameters2 = [
                    '-v',
                    'quiet',
                    '-frames:v 1',
                    '-q:v 2',
                    '-f image2',
                ];

                // var_dump(implode(" ", $parameters));

                // $commandStr = $ffprobe . ' ' . implode(' ', array_map('escapeshellarg', $parameters));
                // $commandStr = $ffmpeg . ' ' . implode(' ', array_map('escapeshellarg', $parameters));

                // ?string $input, ?string $output, array $options = [], array $sourceStreams = null): string

                /*
                $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);
                $ffmpegCommand = $formatRepository->buildParameterString(
                    $localFile,
                    $targetFilePath,
                    //$task->getConfiguration(),
                    // $configuration,
                    $parameters2,
                    // $streams
                );
                */

//                print("<br>targetfilepath?". $targetFilePath."<br>");
//                print("targetfilepath exists? ". file_exists($targetFilePath));
//                print(" ".file_exists($localFile));

                // ffmpeg -ss 01:23:45 -i input -frames:v 1 -q:v 2 output.jpg
                // use tempfile, -y should not be necessary
                //$ffmpegCommand = " -ss " .$startFrame. " -y -i '".$localFile."' -frames:v 1 -q:v 2 -f image2 '".$targetFilePath."'";

                $tempFilePathPrefix = str_replace(".webm", '.png', $tempFilePath);

                $ffmpegCommand = " -ss " .$startFrame. " -y -i '".$localFile."' -frames:v 1 -q:v 2 -f image2 '".$tempFilePathPrefix."'";
                $progress = $this->ffmpeg($ffmpegCommand);

                // Important to run progress now
                $progress->current();
                sleep(2);

                // var_dump($progress);

                if (file_exists($tempFilePathPrefix) ) {
                    if (filesize($tempFilePathPrefix) < 100 ) {
                        var_dump("<br> ffmpeg fail, empty file<hr>");
                    }
                } else {
                    print('<br> ffmpeg fail, no file found >>>' . $tempFilePathPrefix . '<<< <hr>');
                }

//                print('filesize a: '. strval(filesize($tempFilePathPrefix)). "<br>");

//            } catch (Exception $e) {
/*
                var_dump("poster catch");
                // $this->logger->notice('poster generation', ['exception' => $e]);
                throw new \RuntimeException("poster generation not working: " . $e->getMessage() );
*/
//            } finally {
/*
                print('finally 276 targetFilePath exists: '. $targetFilePath . " exists:" . strval(file_exists($targetFilePath)). "<br>");
                print('filesize b: '. strval(filesize($targetFilePath)). "<br>");
                print('new target/local File Preview : '. $localFilePreview. "<br>");
*/

//                GeneralUtility::unlink_tempfile($targetFilePath);
//                GeneralUtility::unlink_tempfile($tempFilePathPrefix);

//            }
        }

        // return $this->generatePreviewFromLocalFile($file->getForLocalProcessing(false), $configuration, $targetFilePath);
        // return $this->generatePreviewFromLocalFile($file->getForLocalProcessing(false), $configuration, $targetFilePath);

        return $this->generatePreviewFromLocalVideoFile(
            // $file->getForLocalProcessing(false),
            $targetFilePath,
            $configuration,
            // $localFilePreview // target preview image
            // still use the temp file
            $targetFilePath,
            //$tempFilePath,
            $tempFilePathPrefix
        );

    }

    /**
     * Generates a preview for a local file
     * local temp file generated with ffmpeg of a video file
     *
     * @param string $originalFileName Optional input file path
     * @param array $configuration Processing configuration
     * @param string $targetFilePath Output file path
     * @return array
     */

    protected function generatePreviewFromLocalVideoFile(
        string $originalFileName, // generated frame original size
        array $configuration,
        string $targetFilePath,
        string $tempFilePath
    )
    {


        $targetFilePath = Environment::getPublicPath() . '/fileadmin/user_upload/' . $targetFilePath;

        $tempFilePath = str_replace(".webm", ".png", $tempFilePath);

        // var_dump("<strong>[LocalVideoPreviewHelper] original file, before temp file: </strong>".$originalFileName. "<hr>");
        // var_dump("<strong>[generatePreviewFromLocalFile]</strong> targetFilePath: ".$targetFilePath. "<hr>");
        // var_dump("<strong>[generatePreviewFromLocalFile]</strong> tempFilePath: ".$tempFilePath. "<hr>");

        // Create the temporary file

        // var_dump("IM enabeld?",($GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_enabled']));

        if ($GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_enabled']) {
            $arguments = CommandUtility::escapeShellArguments([
                'width' => $configuration['width'],
                'height' => $configuration['height'],
            ]);

//            ImageMagickFile::fromFilePath($originalFileName, 0)

            // used?
            $targetFilePath = str_replace(".webm", ".png", $targetFilePath);
            $targetFilePath = str_replace(".mp4", ".png", $targetFilePath);

            $tempFilePathPrefix = str_replace(".webm", '.png', $tempFilePath);

            // $imOutput = ImageMagickFile::fromFilePath($targetFilePath, 0);
            // overwrite itself?
            $imOutput = ImageMagickFile::fromFilePath($tempFilePathPrefix, 0);
//            print ("<strong>imOutput</strong>". $imOutput."<hr>");

            // $imInput = ImageMagickFile::fromFilePath($originalFileName, 0);
            $imInput = ImageMagickFile::fromFilePath($tempFilePathPrefix, 0);
            // print ("<strong>imInput</strong>". $imInput."<hr>");

            // same file, just smaller
            $parameters = '-sample ' . $arguments['width'] . 'x' . $arguments['height']
                . ' ' . $imInput
                // not yet final target file, still temp
//                . ' ' . ImageMagickFile::fromFilePath($targetFilePath, 0);
                . ' ' . $imOutput ;

//            \TYPO3\CMS\Core\Utility\DebugUtility::debug($parameters);
//            var_dump($parameters);

            $cmd = CommandUtility::imageMagickCommand('convert', $parameters) . ' 2>&1';
//            if ( file_exists($originalFileName) && filesize($originalFileName) > 100 ) {
            if ( file_exists($tempFilePathPrefix) && filesize($tempFilePathPrefix) > 100 ) {
// // //
// *******************************
// *******************************
//
//
// *******************************
// // //                $exec_cmd = CommandUtility::exec($cmd);
                // var_dump($exec_cmd);
            } else {
                print ( $tempFilePathPrefix. "<br><strong>tempFilePathPrefix doesnt exist</strong><br>");
            }
    /*
            // if (!file_exists($targetFilePath)) {
                // Create an error gif
                $graphicalFunctions = GeneralUtility::makeInstance(GraphicalFunctions::class);
                $graphicalFunctions->getTemporaryImageWithText(
                    $targetFilePath,
                    'No VIDEO thumb',
                    'generated!',
                    ''
                );
            // }
    */

        }

        return [
//            'filePath' => $targetFilePath,
            'filePath' => $tempFilePathPrefix
            // 'filePath' => $tempFilePath,
        ];
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
            throw new \RuntimeException("ffprobe command in runner not found.");
        }

        $parameters = [
            '-v',
            'quiet',
            '-print_format',
            'json',
            '-show_streams',
            '-show_format',
            $file
        ];

        $commandStr = $ffprobe . ' ' . implode(' ', array_map('escapeshellarg', $parameters));

        $logger->info('run ffprobe command', ['command' => $commandStr]);

        $execution = $this->runner->run($commandStr);
        $response = implode('', iterator_to_array($execution));
        $returnValue = $execution->getReturn();

        $logger->debug(
            'ffprobe result',
            ['output' => preg_replace('#\s{2,}#', ' ', $response)]
        );

        if ($returnValue !== 0 && $returnValue !== null) {
            throw new ConversionException("LocalVideoPreviewHelper Probing failed: $commandStr", $returnValue);
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

        // \TYPO3\CMS\Core\Utility\DebugUtility::debug("ffmpeg command in ffmpeg func localvideopreviewhelper");
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
        } else {
            throw new \RuntimeException("nice not found.");
        }

        $commandStr = "$ffmpeg -loglevel warning -stats $parameters";
        // $commandStr = "$ffmpeg $parameters";
        // var_dump($commandStr);

        $process = $this->runner->run($commandStr);

        $logger->notice("run ffmpeg command", ['command' => $commandStr]);

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

        return $returnValue;

    }

    function str_lreplace($search, $replace, $subject)
    {
        $pos = strrpos($subject, $search);
        if($pos !== false)
        {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }
        return $subject;
    }



}
