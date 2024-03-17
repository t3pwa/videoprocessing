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
        // do this always for now
        // if ($file->hasProperty('width') || $file->hasProperty('height')) {
//            return false;
//        }

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

        // in order of appearance, better sorted by relevance

        if (!empty($raw['filesize'])) {
            $previousExtractedData['filesize'] = intval($raw['filesize']);
        }
        if (!empty($raw['filepath'])) {
            $previousExtractedData['filepath'] = strval($raw['filepath']);
        }
        if (!empty($raw['filename'])) {
            $previousExtractedData['filename'] = strval($raw['filename']);
        }
        if (!empty($raw['filenamepath'])) {
            $previousExtractedData['filenamepath'] = strval($raw['filenamepath']);
        }

        if (!empty($raw['avdataoffset'])) {
            $previousExtractedData['avdataoffset'] = intval($raw['avdataoffset']);
        }
        if (!empty($raw['avdataend'])) {
            $previousExtractedData['avdataend'] = intval($raw['avdataend']);
        }

        if (!empty($raw['fileformat'])) {
            $previousExtractedData['fileformat'] = strval($raw['fileformat']);
        }


        // ToDo keep orginal structure/ array format
//        if (empty($raw["video"])) {
//        $previousExtractedData["video"] = [];
//        }

            if (!empty($raw['video']['dataformat'])) {
                $previousExtractedData['dataformat'] = strval($raw['video']['dataformat']);
                $previousExtractedData['video']['dataformat'] = strval($raw['video']['dataformat']);
            }
            if (!empty($raw['video']['rotate'])) {
                $previousExtractedData['rotate'] = intval($raw['video']['rotate']);
    //            $previousExtractedData['video']['rotate'] = intval($raw['video']['rotate']);
            }
            if (!empty($raw['video']['resolution_x'])) {
                $previousExtractedData['width'] = intval($raw['video']['resolution_x']);
                $previousExtractedData['video']['resolution_x'] = intval($raw['video']['resolution_x']);
            }
            if (!empty($raw['video']['resolution_y'])) {
                $previousExtractedData['height'] = intval($raw['video']['resolution_y']);
                $previousExtractedData['video']['resolution_y'] = intval($raw['video']['resolution_y']);

            }
            if (!empty($raw['video']['fourcc'])) {
                $previousExtractedData['fourcc'] = strval($raw['video']['fourcc']);
                $previousExtractedData['video']['fourcc'] = intval($raw['video']['fourcc']);
            }
            if (!empty($raw['video']['fourcc_lookup'])) {
                $previousExtractedData['fourcc_lookup'] = strval($raw['video']['fourcc_lookup']);
                $previousExtractedData['video']['fourcc_lookup'] = strval($raw['video']['fourcc_lookup']);
            }
            if (!empty($raw['video']['framerate'])) {
                $previousExtractedData['framerate'] = intval($raw['video']['framerate']);
                $previousExtractedData['video']['framerate'] = intval($raw['video']['framerate']);

            }




        if (!empty($raw['comments']['language'])) {
            // ToDo check against some constants list, static-info-table, check for "und", undefined, empty and null

            // comments is array
//            $previousExtractedData['language'] = strval($raw['comments']['language']);

        }

        if (!empty($raw['encoding'])) {
            // ToDo Check against constants
            $previousExtractedData['encoding'] = strval($raw['encoding']);
        }

        if (!empty($raw['mime_type'])) {
            $previousExtractedData['mime_type'] = strval($raw['mime_type']);

        }

/*
        if (empty($raw["quicktime"])) {
            $previousExtractedData["quicktime"] = [];
        }
*/


        if (!empty($raw['quicktime']['hinting'])) {
            $previousExtractedData['quicktime']['hinting'] = boolval($raw['quicktime']['hinting']);
        }
        if (!empty($raw['quicktime']['controller'])) {
            $previousExtractedData['quicktime']['controller'] = strval($raw['quicktime']['controller'] );
        }

        if (!empty($raw['quicktime']['ftyp'])) {
            $previousExtractedData['quicktime']['ftyp'] = array(( $raw['quicktime']['ftyp'] ));
        }



        /*
         *

          ["quicktime"]=>          array(9) {
            ["hinting"]=>            bool(false)
            ["controller"]=>            string(8) "standard"

                ["ftyp"]=>            array(7) {
                    ["hierarchy"]=>              string(4) "ftyp"
                    ["name"]=>              string(4) "ftyp"
                    ["size"]=>              int(32)
                    ["offset"]=>              int(0)
                    ["signature"]=>              string(4) "mp42"
                    ["unknown_1"]=>              int(0)
                    ["fourcc"]=>              string(4) "mp42"
               }

            ["timestamps_unix"]=>            array(2) {
                    ["create"]=>              array(3) {
                        ["moov mvhd"]=>                int(1640126674)
                        ["moov trak tkhd"]=>                int(1640126674)
                        ["moov trak mdia mdhd"]=>                int(1640126674)
                    }
                    ["modify"]=>            array(3) {
                        ["moov mvhd"]=>                int(1640126674)
                ["moov trak tkhd"]=>                int(1640126674)
                ["moov trak mdia mdhd"]=>                int(1640126674)
              }
            }
            ["time_scale"]=>            int(24)
            ["display_scale"]=>            float(1)

            ["video"]=>            array(5) {
              ["rotate"]=>              int(0)
              ["resolution_x"]=>              int(1920)
              ["resolution_y"]=>              int(1080)
              ["frame_rate"]=>              float(24)
              ["frame_count"]=>              int(131)
            }
            ["stts_framecount"]=>
            array(1) {
                    [0]=>              int(131)
            }
            ["mdat"]=>
            array(4) {
                    ["hierarchy"]=>              string(4) "mdat"
                    ["name"]=>              string(4) "mdat"
                    ["size"]=>              int(3354459)
              ["offset"]=>              int(2564)
            }
          }

          ["playtime_seconds"]=>          float(5.458333333333333)
          ["bitrate"]=>          float(4916447.267175573)
        */

        if (!empty($raw['playtime_seconds'])) {
            $previousExtractedData['playtime_seconds'] = floatval($raw['playtime_seconds']);
        }
        if (!empty($raw['bitrate'])) {
            $previousExtractedData['bitrate'] = floatval($raw['bitrate']);
        }


        // var_dump($raw, $previousExtractedData);
        // ToDo dump return in PhpFFmpegConverter process()
        return $previousExtractedData;
    }
}
