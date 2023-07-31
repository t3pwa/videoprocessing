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

        // var_dump("renderer options autoplay?", $options['autoplay']);
        // var_dump($width, $height);
        // var_dump($file->getProperty('width'), $file->getProperty('height') );

        $width = $width ?: $file->getProperty('width');
        $height = $height ?: $file->getProperty('height');

        // $poster = $file->getProperty('poster');

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

        $attributes['width'] = 'width="' . round($width) . '"';
        $attributes['height'] = 'height="' . round($height) . '"';

        // orginal file doesnt help us here, should be keept hidden?!

        $posterImageFilePath = $file->getOriginalFile()->getIdentifier();

//        if ($posterImageFilePath != NULL) {
//            $posterImageFilePath = preg_replace("mp4", "png", $posterImageFilePath);
//        }

        //$attributes['preview_image'] = 'poster="/fileadmin' . $file->getOriginalFile()->getIdentifier() . '"';
        $attributes['preview_image'] = 'poster="/fileadmin' . $posterImageFilePath . '"';
        // var_dump($file->getProperty('preview_image'));
        // var_dump($file->getProperty('preview_image')->getIdentifier());

        // $attributes['identifier'] = $file->getIdentifier();

        // TODO only if in ffrontend
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

        // if ($options['loop'] ?? $autoplay > 1) {
        if ($file->getProperty('autoplay') > 1) {
            $attributes['loop'] = 'loop';
        }

        // if ($options['controls'] ?? $autoplay < 3) {
        if ($file->getProperty('autoplay') < 3) {
            $attributes['controls'] = 'controls';
        }

//        if ($options['playsinline'] ?? $autoplay >= 1) {
        if ($file->getProperty('autoplay') >= 1) {
            $attributes['playsinline'] = 'playsinline';
        }

        foreach ($this->getAttributes() as $key) {
            if (!empty($options[$key])) {
                $attributes[$key] = $key . '="' . htmlspecialchars($options[$key]) . '"';
            }
        }

        // var_dump("attributes", $attributes);

        [$sources, $videos] = $this->buildSources($file, $options, $usedPathsRelativeToCurrentScript);
        self::dispatch('beforeTag', [&$attributes, &$sources], func_get_args());

        if (empty($sources) && ($options['progress'] ?? true)) {
            // TODO Process Render refactoring, still clumsy
            $sources[] = ProgressViewHelper::renderHtml($videos);

            // $tag = sprintf('is processing');
            $tag = sprintf('<div %s>%s</div>', implode(' ', $attributes), implode('', $sources));
            self::dispatch('afterProgressTag', [&$tag, $attributes, $sources], func_get_args());
        } else {
            // $tag = sprintf('<span style="font-color: #fff;">finished processing</span>');
            // var_dump("video tag renderer attributes for video tag", $attributes);
            // var_dump( "first source", $sources[0]);

            $str = $sources[0];
            // var_dump("first source string:", $sources[0]);
            preg_match_all( '/<\s*source[^>]src="(.*?)"\s?(.*?)>/i', $str, $match);

            // var_dump( "match:", $match[1][0] );

            // orginial file, not processed
            //var_dump( "first source B", $file->getPublicUrl($usedPathsRelativeToCurrentScript) ) ;

            // TODO $poster = $file->getPublicUrl($usedPathsRelativeToCurrentScript);
            $poster = $match[1][0];

            $thumbnail_png = substr_replace($poster , 'png', strrpos($poster , '.') +1);
            $posterImageFilePath = $thumbnail_png;

            $attributes['preview_image'] = 'poster="' . $posterImageFilePath . '"';

            $tag = sprintf('<video %s>%s</video>',
                implode(' ', $attributes),
                implode('', $sources)
            );
            self::dispatch('afterTag', [&$tag, $attributes, $sources], func_get_args());
        }

        return $tag;
    }

    protected function buildSources(FileInterface $file, array $options, $usedPathsRelativeToCurrentScript): array
    {
        // do not process a processed file
        if ($file instanceof ProcessedFile) {
            if ($GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
                $GLOBALS['TSFE']->addCacheTags(["processed_video_{$file->getUid()}"]);
            }

            $source = sprintf(
                '<source src="%s" type="%s" />',
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
            // $videos[] = $video = $file->process('Video.CropScale', $configuration);

            $videos[] = $video = $file->process('Video.CropScale', $configuration);


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
                '<source src="%s" type="%s" />',
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
