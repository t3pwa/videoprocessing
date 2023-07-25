<?php

namespace Faeb\Videoprocessing;


use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource;
use TYPO3\CMS\Core\Resource\Index\ExtractorInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Implementation of a metadata extractor.
 * Of course, the typo3 implementation is broken which is why this only runs while adding files.
 * Except for images since they are hardcoded everywhere: \TYPO3\CMS\Core\Resource\Index\MetaDataRepository::findByFile
 *
 */
class VideoMetadataExtractor implements ExtractorInterface
{
    private $getID3;

    public function __construct()
    {
        $this->getID3 = new \getID3();
        $this->getID3->option_tag_lyrics3 = false;
        $this->getID3->option_tags_process = false;
        $this->getID3->option_tags_html = false;
        $this->getID3->option_extra_info = false;
        $this->getID3->option_save_attachments = false;

        $this->getID3->tempdir = Environment::getPublicPath() . '/' . 'typo3temp/var/transient/';
        if (!is_dir($this->getID3->tempdir)) {
            GeneralUtility::mkdir_deep($this->getID3->tempdir);
        }
    }

    /**
     * Returns an array of supported file types;
     * An empty array indicates all filetypes
     *
     * @return array
     */
    public function getFileTypeRestrictions()
    {
        return [AbstractFile::FILETYPE_AUDIO, AbstractFile::FILETYPE_VIDEO];
    }

    /**
     * Get all supported DriverClasses
     *
     * Since some extractors may only work for local files, and other extractors
     * are especially made for grabbing data from remote.
     *
     * Returns array of string with driver names of Drivers which are supported,
     * If the driver did not register a name, it's the classname.
     * empty array indicates no restrictions
     *
     * @return array
     */
    public function getDriverRestrictions()
    {
        return [];
    }

    /**
     * Returns the data priority of the extraction Service.
     * Defines the precedence of Data if several extractors
     * extracted the same property.
     *
     * Should be between 1 and 100, 100 is more important than 1
     *
     * @return int
     */
    public function getPriority()
    {
        return 11;
    }

    /**
     * Returns the execution priority of the extraction Service
     * Should be between 1 and 100, 100 means runs as first service, 1 runs at last service
     *
     * @return int
     */
    public function getExecutionPriority()
    {
        return 11;
    }

    /**
     * Checks if the given file can be processed by this Extractor
     *
     * @param Resource\File $file
     *
     * @return bool
     */
    public function canProcess(File $file)
    {
        if (!$file->exists()) {
            return false;
        }

        // if with and height are already known do nothing
        if ($file->hasProperty('width') || $file->hasProperty('height')) {
            return false;
        }

        return TypeUtility::inList($file->getMimeType(), TypeUtility::VIDEO_MIME_TYPES);
    }

    /**
     * The actual processing TASK
     *
     * Should return an array with database properties for sys_file_metadata to write
     *
     * @param Resource\File $file
     * @param array $previousExtractedData optional, contains the array of already extracted data
     *
     * @return array
     */
    public function extractMetaData(File $file, array $previousExtractedData = [])
    {
        $raw = $this->getID3->analyze($file->getForLocalProcessing(false), $file->getSize(), $file->getName());

        if (!empty($raw['video']['resolution_x'])) {
            $previousExtractedData['width'] = intval($raw['video']['resolution_x']);
        }

        if (!empty($raw['video']['resolution_y'])) {
            $previousExtractedData['height'] = intval($raw['video']['resolution_y']);
        }

        // TODO there is probably more meta data available

        return $previousExtractedData;
    }
}
