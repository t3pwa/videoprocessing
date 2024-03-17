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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Imaging\GraphicalFunctions;
use TYPO3\CMS\Core\Resource\Processing\LocalCropScaleMaskHelper;
use TYPO3\CMS\Core\Resource\Processing\LocalPreviewHelper;

use Faeb\Videoprocessing\Processing\LocalVideoPreviewHelperPreviewHelper;

use TYPO3\CMS\Core\Resource\Processing\ProcessorInterface;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use TYPO3\CMS\Core\Resource\Processing\LocalImageProcessor;

use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\DebugUtility;


use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;

/**
 * Processes Local Images files
 */
#class LocalImageExtendProcessor implements ProcessorInterface, LoggerAwareInterface
class LocalImageExtendProcessor extends LocalImageProcessor
{
    use LoggerAwareTrait;

    /**
     * Returns TRUE if this processor can process the given task.
     *
     * @param TaskInterface $task
     * @return bool
     */
    public function canProcessTask(TaskInterface $task): bool
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->notice('LocalImageExtend can process task?' );



//        \TYPO3\CMS\Core\Utility\DebugUtility::debug("canProcessTask in LocalImageExtend");
//        var_dump($task->getType());
//        var_dump($task->getName());

        return $task->getType() === 'Video'
            && in_array($task->getName(), ['Video', 'CropScale'], true);
    }

    /**
     * Processes the given task.
     *
     * @param TaskInterface $task
     * @throws \InvalidArgumentException
     */
    public function processTask(TaskInterface $task): void
    {
        // var_dump( "[LocalImage processTask] \$this->checkForExistingTargetFile". $this->checkForExistingTargetFile($task) );

        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->logger->notice('LocalImage processTask');

        if ($this->checkForExistingTargetFile($task)) {
            // var_dump('exists already, return');
            $this->logger->notice('exists already, return' );
            return;
        } else {
            // var_dump('target file not exists, continue');
            $this->logger->notice('not exists, cont' );
        }

        $this->processTaskWithLocalFile($task, null);
    }

    /**
     * Processes an image described in a task, but optionally uses a given local image
     *
     * @param TaskInterface $task
     * @param string|null $localFile
     * @throws \InvalidArgumentException
     */
    public function processTaskWithLocalFile(
        TaskInterface $task,
        ?string $localFile): void
    {
        // print( "<strong>processTaskWithLocalFile:</strong> >>>".$localFile."<<< type:" . gettype($localFile) );


        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->logger->notice(sprintf('[LocalImageExtend] Processing task file %s', $localFile));

        // print ("process with helper: " . $task->getName() );
        $helper = $this->getHelperByTaskName($task->getName());
        // print ("use the right helper?:". $this->getHelperByTaskName($task->getName()) );


        try {
            if ($localFile === null) {
                // var_dump("[processTaskWithLocalFile] yes process LOCAL file NULL, task");
                // Should this return processed target file? no, still transient at this point
                $result = $helper->process($task);
            } else {
                // var_dump("[localFile YES exists] in try processWithLocalFile, dont process");
                $result = $helper->processWithLocalFile($task, $localFile);
            }
            // var_dump ("[after process] check results ...");
            // var_dump($result);


            if ($result === null) {
                // var_dump("<hr>result null");
//                $task->setExecuted(true);
                // ToDo? only when failed?
                // $task->getTargetFile()->setUsesOriginalFile();

            } elseif (!empty($result['filePath']) && file_exists($result['filePath'])) {
//                var_dump("result result, should be temp transient", $result['filePath']);
//                var_dump("<hr>result filepath exists!<br>");
//                var_dump( $result['filePath'] );


                $imageDimensions = $this->getGraphicalFunctionsObject()->getImageDimensions($result['filePath']);
                $task->getTargetFile()->updateProperties([
                    'width' => $imageDimensions[0] ?? 0,
                    'height' => $imageDimensions[1] ?? 0,
                    'size' => filesize($result['filePath']),
                    'checksum' => $task->getConfigurationChecksum(),
                    'duration' => '88'
                ]);

                try {
                    // Injects a local file, which is a processing result into the object.
                    $task->getTargetFile()->updateWithLocalFile($result['filePath']);

                    $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
                    $this->logger->info(sprintf('Processing task updated with local file', $result['filePath']));


                } finally {
//                    var_dump("updatewith local file");
                }
                $task->setExecuted(true);

                // var_dump ( $imageDimensions = $this->getGraphicalFunctionsObject()->getImageDimensions($result['filePath']) );

                // print("get identifier for", $result['filePath']);
                // $resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
                // $storageId = $resourceFactory->getDefaultStorage();

//                $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
//                $defaultStorage = $storageRepository->getDefaultStorage();

//                $identifier = $result['filePath'];
                // var_dump( $task->getSourceFile() );
                // $identifier = $task->getSourceFile();



                /*
                $dirname = dirname($result['filePath']);
                // var_dump("dirname", $dirname);
                $filename = str_replace($dirname."/", "", $identifier );
                // var_dump("filename", $filename, "<hr>");

                $dirnameShort = preg_replace('/.*(fileadmin)/', '', $dirname,);
                // var_dump("dirname short:", $dirnameShort);

                $folder = $defaultStorage->getFolder($dirnameShort);
                // var_dump($folder);
                */

//                $file = $task->getSourceFile();
//                $targetFile = $task->getTargetFile();
                // $localFile;
                // $file = $folder->getStorage()->getFileInFolder($filename, $folder);
                // var_dump("file uid:". $file->getUid());
//                var_dump("getPublicUrl".$targetFile->getPublicUrl());
                // var_dump("ident:".$file->getIdentifier());
//                var_dump("title:".$file->getProperty('title'));
                // var_dump("processed?:".$file->isProcessed());

                // var_dump($file->generateProcessedFileNameWithoutExtension());
//                var_dump("public:" . $file->getPublicUrl());
                // var_dump("props:" . $file->getProperties());


/*
                $fileRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\FileRepository::class);
                $fileObjects = $fileRepository->findByRelation('tt_content', 'assets', $file->getUid());

                var_dump("fileobj:", $fileObjects);
*/

                // toDo compare db processed file entries.

                // $resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
                // $file->updateWithLocalFile($result['filePath']);
                // var_dump($file->getPublicUrl());
                // $file->getProcessedFile();
                // $task->setTargetFile($file);




                // ToDo add this file in storage to ... orignal file obj?!


//                var_dump ("isprocessed: ". $task->getTargetFile()->isProcessed() . "<hr>" );
//                var_dump ("name: ". $task->getTargetFile()->getName() . "<hr>" );
//                var_dump ( implode( " ", $task->getTargetFile()->getProperties()) . "<hr>" );


            } else {
                // var_dump("<hr>ELSE no valid processing result<br>");
                // var_dump("result:", $result['filePath']);
                // var_dump("exists?", file_exists($result['filePath']) );

                // Seems we have no valid processing result
                $task->setExecuted(false);
            }

        } catch (\Exception $e) {

            // @todo: Swallowing all exceptions including PHP warnings here is a bad idea.
            // @todo: This should be restricted to more specific exceptions - if at all.
            // @todo: For now, we at least log the situation.
            $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $this->logger->error(sprintf('Processing task of image file'), ['exception' => $e]);
            $task->setExecuted(false);
            $msg = "";
            $msg .= "in helper process task? " . $e->getMessage();
            // var_dump("failed process task<hr>" . $msg);
            throw new \RuntimeException($msg);

        }


//        print("<hr><b>finished after all</b><hr>");

        // Todo get sys_filereference of original video
        // var_dump("localfile", $localFile);
        $storageId = 1;
        $identifier = $localFile;
        /*
        $fileObject = ResourceFactory::getInstance()
            ->getFileObjectByStorageAndIdentifier($storageId, $identifier);
        */

        // $storage = $task->getTargetFile()->getStorage();
        // $processingFolder = $storage->getProcessingFolder($task->getSourceFile());

        // var_dump ( $task->getTargetFile()->getIdentifier() );
        // var_dump ( $task->getSourceFile()->getIdentifier() );


    }

    /**
     * Check if the to be processed target file already exists
     * if exist take info from that file and mark task as done
     *
     * @param TaskInterface $task
     * @return bool
     */
    protected function checkForExistingTargetFile(TaskInterface $task)
    {
        // the storage of the processed file, not of the original file!
        $storage = $task->getTargetFile()->getStorage();
        $processingFolder = $storage->getProcessingFolder($task->getSourceFile());

        // explicitly check for the raw filename here, as we check for files that existed before we even started
        // processing, i.e. that were processed earlier


        // var_dump("[check for existing...] \$task->getTargetFileName()". $task->getTargetFileName() );
        $this->logger->notice('[LocalImageExtend] [check for existing...] \$task->getTargetFileName() ?  ',  ['targetFileName' => $task->getTargetFileName() ]  );

        if ($processingFolder->hasFile($task->getTargetFileName())) {

            // When the processed file already exists set it as processed file
            $task->getTargetFile()->setName($task->getTargetFileName());

            // If the processed file is stored on a remote server, we must fetch a local copy of the file, as we
            // have no API for fetching file metadata from a remote file.
            $localProcessedFile = $storage->getFileForLocalProcessing($task->getTargetFile(), false);
            $task->setExecuted(true);

            $imageDimensions = $this->getGraphicalFunctionsObject()->getImageDimensions($localProcessedFile);
            /*
            $properties = [
                'width' => $imageDimensions[0] ?? 0,
                'height' => $imageDimensions[1] ?? 0,
                'size' => filesize($localProcessedFile),
                'checksum' => $task->getConfigurationChecksum(),
            ];

            $task->getTargetFile()->updateProperties($properties);
            */

            print("<hr>HAS FILE<hr>");
            return true;
        }
        return false;
    }

    /**
     * @param string $taskName
     * @return LocalCropScaleMaskHelper|LocalPreviewHelper
     * @throws \InvalidArgumentException
     */
    protected function getHelperByTaskName($taskName)
    {
        switch ($taskName) {

            // Video CropScale
            case 'CropScale':
                $helper = GeneralUtility::makeInstance(LocalVideoPreviewHelper::class);
                break;

            case 'Preview':
                $helper = GeneralUtility::makeInstance(LocalPreviewHelper::class);
                break;
            case 'CropScaleMask':
                $helper = GeneralUtility::makeInstance(LocalCropScaleMaskHelper::class);
                break;
            default:
                throw new \InvalidArgumentException('Cannot find helper for task name: "' . $taskName . '"', 1353401352);
        }

        return $helper;
    }

    /**
     * @return GraphicalFunctions
     */
    protected function getGraphicalFunctionsObject(): GraphicalFunctions
    {
        return GeneralUtility::makeInstance(GraphicalFunctions::class);
    }
}
