<?php

namespace Faeb\Videoprocessing\Rendering;


use TYPO3\CMS\Core\Resource\Rendering\FileRendererInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\File;

use Faeb\Videoprocessing\FormatRepository;
use Faeb\Videoprocessing\TypeUtility;
use Faeb\Videoprocessing\ViewHelpers\ProgressViewHelper;

use TYPO3\CMS\Core\Resource;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class VideoTagRenderer implements FileRendererInterface
{
    /**
     * Returns the priority of the renderer
     * This way it is possible to define/overrule a renderer
     * for a specific file type/context.
     *
     * For example create a video renderer for a certain storage/driver type.
     *
     * Should be between 1 and 100, 100 is more important than 1
     *
     * @return int
     */
    public function getPriority()
    {
        return 2; // +1 over the typo3 native renderer
    }

    /**
     * Check if given File(Reference) can be rendered
     *
     * @param Resource\FileInterface $file File or FileReference to render
     *
     * @return bool
     */
    public function canRender(FileInterface $file)
    {
        return TypeUtility::inList($file->getMimeType(), TypeUtility::VIDEO_MIME_TYPES)
            && $file->getProperty('width') && $file->getProperty('height');
    }

    /**
     * Render for given File(Reference) HTML output
     *
     * @param Resource\FileInterface $file
     * @param int|string $width TYPO3 known format; examples: 220, 200m or 200c
     * @param int|string $height TYPO3 known format; examples: 220, 200m or 200c
     * @param array $options
     * @param bool $usedPathsRelativeToCurrentScript See $file->getPublicUrl()
     *
     * @return string
     */
    public function render(FileInterface $file, $width, $height, array $options = [], $usedPathsRelativeToCurrentScript = false)
    {
        $attributes = [];

        $width = $width ?: $file->getProperty('width');
        $height = $height ?: $file->getProperty('height');

        // remove "m" from width and height
        if (preg_match('/m$/', $width)) {
            $width = preg_replace("/m$/", "$1", $width);
            $height = preg_replace("/m$/", "$1", $height);
            $width = min($width, $height * $file->getProperty('width') / $file->getProperty('height'));
        }

        if (preg_match('/m$/', $height)) {
            preg_replace("m", "", $height);
            $height = min($height, $width * $file->getProperty('height') / $file->getProperty('width'));
        }
/*
        $attributes['width'] = 'width="' . round($width) . '"';
        $attributes['height'] = 'height="' . round($height) . '"';
*/
        $attributes['width'] = 'width="100%"';
        $attributes['height'] = 'height="auto"';


        // TODO ... only
        // var_dump ( "file prop autoplay", $file->getProperty('autoplay') );

        $autoplay = intval($options['autoplay'] ?? $file->getProperty('autoplay'));
        // what does this even do?
        self::dispatch('autoplay', [&$autoplay], func_get_args());
        // var_dump("autoplay", $autoplay);

        // Options only available per default in TCA flexform settings.options in test content element
        // if ($autoplay > 0) {

/*
        if ($file->getProperty('preview_image')) {
            $attributes['poster'] = 'poster';
        }
*/

        if ($file->getProperty('autoplay') > 0) {
            $attributes['autoplay'] = 'autoplay';
        }

//        if ($options['muted'] ?? $autoplay > 0) {
        if ($file->getProperty('autoplay') > 0) {
            $attributes['muted'] = 'muted';
        }


        // var_dump($file->hasProperty('muted')); die();

        // ToDo if ext ... is installed
        if ($file->hasProperty('muted') && $file->getProperty('muted') > 0) {
            $attributes['muted'] = 'muted';
        }

        // if ($options['loop'] ?? $autoplay > 1) {
        if ($file->hasProperty('autoplay') && $file->getProperty('autoplay') > 1) {
            $attributes['loop'] = 'loop';
        }

        if ($file->hasProperty('loop') && $file->getProperty('loop') > 0) {
            $options['loop'] = 'loop';
        }

        // if ($options['controls'] ?? $autoplay < 3) {
        if ($file->hasProperty('autoplay') && $file->getProperty('autoplay') < 3) {
            // ToDo: is it options or attributes?
            $attributes['controls'] = 'controls';
        }

//        if ($options['playsinline'] ?? $autoplay >= 1) {
        if ($file->hasProperty('autoplay') && $file->getProperty('autoplay') >= 1) {
            $attributes['playsinline'] = 'playsinline';
        }

        foreach ($this->getAttributes() as $key) {
            if (!empty($options[$key])) {
                $attributes[$key] = $key . '="' . htmlspecialchars($options[$key]) . '"';
            }
        }
        // var_dump("attributes", $attributes);

        [$sources, $videos] = $this->buildSources(
            $file,
            $options,
            $usedPathsRelativeToCurrentScript
        );
        self::dispatch('beforeTag', [&$attributes, &$sources], func_get_args());

        $attributes['preview_image'] = $this->getPosterImageFromSources($sources);

        // var_dump($videos);
        // ToDo add function has VideoSources,
        // var_dump( " has processed video sources: ", $this->hasProcessedVideoSources($sources) );

        // if (empty($sources) && ($options['progress'] ?? true)) {


        // has PROCESSED Video Sources, that are not the original upload? !?!? what do then? Preview?

        if ( !$this->hasProcessedVideoSources($sources) && ($options['progress'] ?? true)) {
            // TODO Process Render refactoring, still clumsy

            // $sources[] = ProgressViewHelper::renderHtml($videos);
            $progress = ProgressViewHelper::renderHtml($videos);



//            $attributes['preview_image'] = $this->getPosterImageFromSources($sources);

            // $tag = sprintf('is processing');
            $tag = sprintf('<video data-status="still-processing" %s>%s</video>',
                implode(' ', $attributes),
                implode('', $sources)
            );
            $tag .= $progress;
            self::dispatch('afterProgressTag', [&$tag, $attributes, $sources], func_get_args());
        } else {


//            $attributes['preview_image'] = $this->getPosterImageFromSources($sources);

            $tag = sprintf('<video %s data-status="has-processed-sources">%s</video>',
                implode(' ', $attributes),
                implode('', $sources)
            );
            self::dispatch('afterTag', [&$tag, $attributes, $sources], func_get_args());
        }

        return $tag;
    }


    protected function getPosterImageFromSources ($sources) {


            // Todo this->getPosterImagefromSources()
            preg_match_all( '/<\s*source[^>]src="(.*?)"\s?(.*?)>/i', $sources[0], $match);
            // orginial file, not processed
            //var_dump( "first source B", $file->getPublicUrl($usedPathsRelativeToCurrentScript) ) ;
            // TODO $poster = $file->getPublicUrl($usedPathsRelativeToCurrentScript);
            $poster = $match[1][0];
            // var_dump("poster: >>>>", $poster);
            $posterImageFilePath = $poster;
            // $attributes['preview_image'] = 'poster="' . $posterImageFilePath . '"';
            return 'poster="' . $posterImageFilePath . '"';


    }

    protected function hasProcessedVideoSources(array $sources): bool {

        foreach ($sources as $source) {
            preg_match_all( '/<\s*source[^>]src="(.*?)"\s?.*?>/i', $source, $sourcematch);
            preg_match_all( '/<\s*source[^>].*?type="(.*?)"\s?.*?>/i', $source, $typematch);

            /*
            if ($sourcematch) {
                // var_dump("<hr>sourcematch: >>>" , $sourcematch[0][0] , "<<<<hr>");
                // print ("<hr><strong>sourcematch</strong>");
                // var_dump( $sourcematch[1] );
                // str_contains( strval( $sourcematch[1][0] ), "processed");
                // print ("<hr>");
            }
            */
/*
            if ($typematch) {
                // print ("<hr><strong>typematch</strong>");
                // var_dump($typematch[1]);
                // print ("<hr>");
                // var_dump("typematch: " , $typematch[0][0] , "<hr>");
            }
*/
            // ToDo if in list of video filetypes
            //if ( $typematch[1] == "video/mp4" || $typematch[1] == "video/webm" ) {
            if  (
                    (
                        // ToDo mimetype from list of videos
                        str_contains( strval( $typematch[1][0] ), "video/mp4")
                        || str_contains( $typematch[1][0], "video/webm")
                    )
                    // ToDo smarter preg matching
                    && (
                        str_contains( strval( $sourcematch[1][0] ), "processed")
                    )
            ) {
                return true;
            }
        }
        return false;
    }

    protected function buildSources(FileInterface $file, array $options, $usedPathsRelativeToCurrentScript): array
    {
        // do not process a processed file
        if ($file instanceof ProcessedFile) {


            if ($GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
                $GLOBALS['TSFE']->addCacheTags(["processed_video_{$file->getUid()}"]);
            }

            $source = sprintf(
                '<source class="processed-file" src="%s" type="%s" />',
                htmlspecialchars($file->getPublicUrl($usedPathsRelativeToCurrentScript)),
                htmlspecialchars($file->getMimeType())
            );

            return [[$source], [$file]];
        }

        if ($file instanceof FileReference) {
            $file = $file->getOriginalFile();
        }

        if (!$file instanceof File) {
            $type = is_object($file) ? get_class($file) : gettype($file);
            throw new \RuntimeException("Expected " . File::class . ", got $type");
        }

        $sources = [];
        $videos = [];

        $configurations = $this->getConfigurations($options);
        foreach ($configurations as $configuration) {

            //var_dump($configuration);

            // $videos[] = $video = $file->process('Video.CropScale', $configuration);

            $videos[] = $video = $file->process('Video.CropScale', $configuration);
            // Get the name of the image preview task
            // $videoImages[] = $videoImages = $file->process('Video.CropScale', $configuration);

            // only add video sources.
            // comment out, add poster image resource, if added to config, open ToDo !!!!
            if (!$video->exists()) {
                continue;
            }

            /*
             * TODO depricated call of $GLOBALS['TSFE'], see also Videpprocessor l. 100
            if ($GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
                $GLOBALS['TSFE']->addCacheTags(["processed_video_{$video->getUid()}"]);
            }
            */

            $sources[] = sprintf(
                // simple poster image video test, acctually it's the video starttime
                // '<source src="%s#t=5" type="%s" />',
                '<source src="%s" type="%s" class="configuration" />',
                htmlspecialchars($video->getPublicUrl($usedPathsRelativeToCurrentScript)),
                htmlspecialchars($video->getMimeType())
            );
        }

        return [$sources, $videos];
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function getConfigurations(array $options): array
    {
        $formats = $options['formats'] ?? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['default_video_formats'];
        self::dispatch('formats', [&$formats], func_get_args());

        $configurations = [];
        foreach ($formats as $formatKey => $formatOptions) {
            $configurations[] = FormatRepository::normalizeOptions(array_replace(
                $options,
                ['format' => $formatKey],
                $formatOptions
            ));
        }

        return $configurations;
    }

    /**
     * @return array
     */
    protected function getAttributes(): array
    {
        return [
            'class',
            'dir',
            'id',
            'lang',
            'style',
            'title',
            'accesskey',
            'tabindex',
            'onclick',
            'controlsList',
            'preload'
        ];
    }

    private static function dispatch(string $name, array $arguments, array ...$furtherArguments)
    {
        if (!empty($furtherArguments)) {
            $arguments = array_merge($arguments, ...$furtherArguments);
        }

        GeneralUtility::makeInstance(Dispatcher::class)->dispatch(__CLASS__, $name, $arguments);
    }
}
