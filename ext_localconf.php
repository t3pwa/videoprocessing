<?php

use Faeb\Videoprocessing\Converter;
use Faeb\Videoprocessing\Preset;
use Faeb\Videoprocessing\Processing\VideoProcessingEid;
use Faeb\Videoprocessing\Processing\VideoProcessingTask;
use TYPO3\CMS\Core\Http\ApplicationType;

if (!defined('TYPO3')) {
    die('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processingTaskTypes']['Video.CropScale'] = VideoProcessingTask::class;


call_user_func(function () {
    $conf = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('videoprocessing');

    // parse performance syntax
    // a great chart comparing encoding speeds vs quality can be found here:
    // https://blogs.gnome.org/rbultje/2015/09/28/vp9-encodingdecoding-performance-vs-hevch-264/
    $performanceOptions = [
        // @formatter:off
        'h264' => ['ultrafast', 'medium', 'slow', 'veryslow', 'slow', 'veryslow'][$conf['preset'] ?? 2],
        'vp9' =>  [ null      ,  null   ,  null ,  null     ,  2    ,  1        ][$conf['preset'] ?? 2],
        // @formatter:on
    ];

    // these are global defaults for the different presets
    // if you want to change all h264 streams, defaults is your way
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['defaults'] = array_replace_recursive(
        [
            Preset\H264Preset::class => [
                'preset' => $performanceOptions['h264'] ?? 'slow',
                'level' => $conf['level'] ?? '3.1',
                'quality' => $conf['videoQuality'] ?? 0.8,
            ],
            Preset\VP9Preset::class => [
                'speed' => $performanceOptions['vp9'] ?? 2,
                'level' => $conf['level'] ?? '3.1',
                'quality' => $conf['videoQuality'] ?? 0.8,
            ],
            /*
            Preset\AacPreset::class => [
                'fdkAvailable' => !empty($conf['fdkAvailable']) || ($conf['converter'] ?? '') === 'CloudConvert',
                'quality' => $conf['audioQuality'] ?? 0.8,
            ],
            */
            Preset\OpusPreset::class => [
                'quality' => $conf['audioQuality'] ?? 0.8,
            ],
        ],
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['defaults'] ?? []
    );

    // mp4 general
    // it should work almost anywhere ~ except maybe old low-cost android devices and feature phones
    if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['formats']['mp4'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['formats']['mp4'] = [
            'fileExtension' => 'mp4',
            'mimeType' => 'video/mp4',
            'video' => [Preset\H264Preset::class],
            'audio' => [Preset\AacPreset::class],
            'additionalParameters' => ['-movflags', '+faststart', '-map_metadata', '-1', '-f', 'mp4'],
        ];
    }

    // webm video
    // higher efficiency than h264 but lacks support in safari
    if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['formats']['webm'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['formats']['webm'] = [
            'fileExtension' => 'webm',
            'mimeType' => 'video/webm',
            'video' => [Preset\VP9Preset::class],
            'audio' => [Preset\OpusPreset::class],
            'additionalParameters' => ['-map_metadata', '-1', '-f', 'webm'],
            'priority' => -10,
        ];
    }

    // m4a audio
    // this should be your choice for audio files
    // ~ it is more efficient than mp3 and has nearly the same browser support
    // there is, however, no audio renderer at this moment.
    if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['formats']['m4a'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['formats']['m4a'] = [
            'fileExtension' => 'm4a',
            'mimeType' => 'audio/mp4',
            'audio' => [Preset\AacPreset::class],
            'additionalParameters' => ['-movflags', '+faststart', '-map_metadata', '-1', '-f', 'mp4'],
        ];
    }

    // this is the default format list used for video
    // the order will be the same as in the final source definition
    // it should reflect which format the browser should choose
    if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['default_video_formats'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['default_video_formats'] = [];

        if (isset($performanceOptions['vp9'])) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['default_video_formats']['webm'] = [];
        }

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['default_video_formats']['mp4'] = [];
    }

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['video_converters'] = [
        'LocalFFmpeg' => [Converter\LocalFFmpegConverter::class]
        // [depricated]
        // 'CloudConvert' => [Converter\CloudConvertConverter::class, $conf['cloudConvertApiKey'] ?? '']
    ];

//    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['video_converter'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['video_converter']
            = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['video_converters'][$conf['converter']];
//    }


    // TODO double check this list
    // TODO check if this list actually triggers the VideoTagRenderer
    //$GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext'] .= ',aac,ac3,aif,aifc,aiff,amr,au,caf,flac,m4a,m4b,mp3,oga,ogg,sfark,voc,wav,weba,wma';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext'] .= ',3g2,3gp,3gpp,avi,cavs,dv,dvr,flv,gif,m2ts,m4v,mkv,mod,mov,mp4,mpeg,mpg,mts,mxf,ogg,rm,rmvb,swf,ts,vob,webm,wmv,wtv';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext'] = \TYPO3\CMS\Core\Utility\StringUtility::uniqueList($GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext']);

    // added
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processors']['VideoProcessor'] = [
        'className' => \Faeb\Videoprocessing\Processing\VideoProcessor::class,
        'before' => ['LocalImageProcessor']
    ];

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processingTaskTypes']['Video.CropScale'] = VideoProcessingTask::class;



    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][VideoProcessingEid::EID]
        = VideoProcessingEid::class ;
    // . '::process';

    // $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include'][\Faeb\Videoprocessing\ViewHelpers\ProgressEid::EID]
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['tx_video_progress']
        // = \Faeb\Videoprocessing\ViewHelpers\ProgressEid::class . '::render';
        = \Faeb\Videoprocessing\ViewHelpers\ProgressEid::class;
    // . '::process';


    // TODO replace with service event listener, moved to EventListener Class

    // $dispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
    /*
    $dispatcher->connect(
        \TYPO3\CMS\Core\Resource\ResourceStorage::class,
//        \TYPO3\CMS\Core\Resource\Service\FileProcessingService::SIGNAL_PreFileProcess,
	    'preFileProcess',
        \Faeb\Videoprocessing\Slot\FileProcessingServiceSlot::class,
        'preFileProcess'
    );

    $dispatcher->connect(
        \TYPO3\CMS\Core\Resource\Index\MetaDataRepository::class,
        'recordPostRetrieval',
        \Faeb\Videoprocessing\Slot\MetaDataRepositorySlot::class,
        'recordPostRetrieval'
    );
    */

    \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::class)
        ->registerRendererClass(\Faeb\Videoprocessing\Rendering\VideoTagRenderer::class);

    \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::class)
        ->registerExtractionService(\Faeb\Videoprocessing\VideoMetadataExtractor::class);


    // if (TYPO3_MODE === 'BE') {
    //if (ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend()) {
        /*
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] =
            \Faeb\Videoprocessing\Command\VideoCommandController::class;
        */
    //}


    if (empty($GLOBALS['TYPO3_CONF_VARS']['LOG']['Faeb']['Videoprocessing'])) {
        $isDev = \TYPO3\CMS\Core\Core\Environment::getContext()->isDevelopment();
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['Faeb']['Videoprocessing']['writerConfiguration'] = [
            $isDev ? \TYPO3\CMS\Core\Log\LogLevel::DEBUG : \TYPO3\CMS\Core\Log\LogLevel::INFO => [
                /*
                \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                    'logFile' => 'typo3temp/logs/video.log',
                ],
                */


            ],
        ];
    }

    if (!empty($conf['testElement'])) {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(<<<PageTSConfig
mod.wizards.newContentElement.wizardItems.special.elements.video {
    iconIdentifier = content-media
    title = video testing utility
    description = This elements helps you find the right options for video compression.
    tt_content_defValues {
        CType = video
    }
}
mod.wizards.newContentElement.wizardItems.special.show := addToList(video)
PageTSConfig
        );

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(<<<TypoScript
tt_content.video = USER
tt_content.video.userFunc = Faeb\Videoprocessing\TestContentElement->render
tt_content.video.configurations.data = flexform:pi_flexform:settings.options
TypoScript
        );
    };
});
