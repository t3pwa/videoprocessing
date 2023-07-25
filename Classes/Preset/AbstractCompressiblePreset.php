<?php

namespace Faeb\Videoprocessing\Preset;


abstract class AbstractCompressiblePreset extends AbstractPreset
{
    const QUALITY_BEST = 1.0;
    const QUALITY_BETTER = 0.9;
    const QUALITY_GOOD = 0.8;
    const QUALITY_ACCEPTABLE = 0.7;
    const QUALITY_TOLERABLE = 0.6;
    const QUALITY_BORDERLINE = 0.5;
    const QUALITY_BAD = 0.4;
    const QUALITY_WORSE = 0.3;
    const QUALITY_HORRIBLE = 0.2;
    const QUALITY_UNACCEPTABLE = 0.1;
    const QUALITY_WORST = 0.0;

    const QUALITY_DEFAULT = self::QUALITY_GOOD;

    /**
     * The implementation has the chance to check if the stream is already matching expectations.
     * If it does than a transcode will only reduce the quality.
     * ffmpeg has the ability to "remux" a stream. That means it will be copied to a new container with zero losses.
     *
     * However (as always) there might be situations not foreseeable where the input stream matches the preset
     * but for some reason won't work or have other kinks. If this happens to you than you can force a transcode here.
     * If it happens, I'd be curious to know what situation that is, open an issue and tell me what i've overlooked.
     *
     * @var bool
     */
    private $forceTranscode = false;

    /**
     * The quality is a value > 0.0 and <= 1.0.
     * The preset must try to adjust the tradeoff between quality and size according to this value.
     *
     * This value is roughly based on jpeg's quality in imagemagick. Here are some points of reference:
     * 1.0 (100%) isn't lossless but effectively as good as you could ever need.
     * 0.8 (80%) should be a good compromise to show at 96dpi at 1:1 scale.
     * 0.3 (30%) should still be acceptable when viewed with 192dpi.
     * A great reference to jpeg quality is here:
     * http://pieroxy.net/blog/2016/05/01/jpeg_compression_is_80_the_magic_quality_part_1_the_retina_screens.html
     * Be careful when implementing this value as some devices don't like too high or too low a bitrate.
     *
     * @var float
     */
    private $quality = self::QUALITY_GOOD;

    /**
     * The short name of this codec.
     * It must match with the codec type ffprobe reports.
     *
     * @return string
     */
    public abstract function getCodecName(): string;

    /**
     * This method checks if transcoding is necessary or if simple remuxing will be sufficient.
     *
     * @param array $sourceStream
     *
     * @return bool
     */
    public function requiresTranscoding(array $sourceStream): bool
    {
        if ($this->isForceTranscode()) {
            return true;
        }

        $isCorrectCodec = isset($sourceStream['codec_name']) && strcasecmp($sourceStream['codec_name'], $this->getCodecName()) === 0;
        if (!$isCorrectCodec) {
            return true;
        }

        return false;
    }

    /**
     * Creates the parameter list for ffmpeg.
     *
     * This method is final so you don't accidentally override it.
     * The method you probably want to override is #getTranscodingParameters
     *
     * @param array $sourceStream
     *
     * @return array
     * @see AbstractCompressiblePreset::getTranscodingParameters()
     */
    public final function getParameters(array $sourceStream): array
    {
        if ($this->requiresTranscoding($sourceStream)) {
            return $this->getTranscodingParameters($sourceStream);
        } else {
            return $this->getRemuxingParameters($sourceStream);
        }
    }

    /**
     * Creates the parameters used if no conversion is necessary.
     * In this case the video stream will just be repackaged into the new container format.
     *
     * @param array $sourceStream
     *
     * @return array
     * @see AbstractCompressiblePreset::requiresTranscoding
     */
    protected abstract function getRemuxingParameters(array $sourceStream): array;

    /**
     * Creates the parameters used to transcode the stream into the desired format.
     *
     * If you create a codec specific implementation than this is the method you want to override to add your parameters.
     * Be sure that ::requiresTranscoding does check your codec and conditions.
     *
     * @param array $sourceStream
     *
     * @return array
     * @see AbstractCompressiblePreset::requiresTranscoding
     */
    protected abstract function getTranscodingParameters(array $sourceStream): array;

    /**
     * @return bool
     */
    public function isForceTranscode(): bool
    {
        return $this->forceTranscode;
    }

    /**
     * @param bool $forceTranscode
     */
    public function setForceTranscode(bool $forceTranscode): void
    {
        $this->forceTranscode = $forceTranscode;
    }

    /**
     * @return float
     * @see AbstractCompressiblePreset::$quality
     */
    public function getQuality(): float
    {
        return $this->quality;
    }

    /**
     * @param float $quality
     *
     * @see AbstractCompressiblePreset::$quality
     */
    public function setQuality(float $quality): void
    {
        if ($quality < 0.0) {
            throw new \RuntimeException("Quality must be equal or above 0.0");
        }

        if ($quality > 1.0) {
            throw new \RuntimeException("Quality must be equal or below 1.0");
        }

        $this->quality = round($quality * 20) / 20;
    }
}
