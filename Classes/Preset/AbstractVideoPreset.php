<?php

namespace Faeb\Videoprocessing\Preset;


use TYPO3\CMS\Core\Utility\MathUtility;

/**
 */
abstract class AbstractVideoPreset extends AbstractCompressiblePreset
{
    /**
     * The maximum framerate allowed within this video.
     * Videos must always be encoded with a constant framerate
     * but be sure to reference the source stream to avoid frame duplication.
     *
     * @var float
     */
    private $maxFramerate = 30.0;

    /**
     * @var int|null
     */
    private $maxWidth = null;

    /**
     * @var int|null
     */
    private $maxHeight = null;

    /**
     * If true than the video will be cropped.
     *
     * @var bool
     * @todo implement
     */
    private $crop = false;

    /**
     * The scaling algorithm to use.
     *
     * @see https://ffmpeg.org/ffmpeg-scaler.html
     * @var string
     */
    private $scalingAlgorithm = 'bicubic';

    protected function getPixelFormat(): string
    {
        // most players don't support other pixel formats
        // and if they do than hardware support is probably also missing
        // just encode everything with 4:2:0 chroma ~ it saves space too
        return 'yuv420p';
    }

    public function getMaxFramerate(): float
    {
        return $this->maxFramerate;
    }

    public function setMaxFramerate(float $maxFramerate): void
    {
        if ($maxFramerate <= 0.0) {
            throw new \RuntimeException("Framerate must be higher than 0.0");
        }

        $this->maxFramerate = $maxFramerate;
    }

    public function getFramerate(array $sourceStream): string
    {
        $maxFramerate = $this->getMaxFramerate();
        if (!isset($sourceStream['avg_frame_rate'])) {
            return $maxFramerate;
        }

        $avgFrameRate = MathUtility::calculateWithParentheses($sourceStream['avg_frame_rate']);
        if ($avgFrameRate <= $maxFramerate) {
            // return the source string so that the ffmpeg fraction is preserved
            return $sourceStream['avg_frame_rate'];
        }

        // if the framerate is more than 50% over our target than start dividing it evenly
        // this should result in less stutter, here a few examples with a 30 fps limit:
        // 32 fps will be ignored and result in 30 fps and probably stuttering but dropping it to 16 fps would be insane
        // 48 fps will result in 24 fps
        // 50 fps will result in 25 fps
        // 144 fps will result in 28,8 fps
        $targetFrameRate = $avgFrameRate;
        for ($divisor = 1; $targetFrameRate > $maxFramerate * (1.0 + 0.5 / $divisor);) {
            $targetFrameRate = $avgFrameRate / ++$divisor;
        }

        return min($targetFrameRate, $maxFramerate);
    }

    public function getMaxWidth(): ?int
    {
        return $this->maxWidth;
    }

    public function setMaxWidth(?int $maxWidth): void
    {
        if ($maxWidth < 8 && $maxWidth !== null) {
            throw new \RuntimeException("width must be 8 or higher0");
        }

        $this->maxWidth = $maxWidth;
    }

    public function getMaxHeight(): ?int
    {
        return $this->maxHeight;
    }

    public function setMaxHeight(?int $maxHeight): void
    {
        if ($maxHeight < 8 && $maxHeight !== null) {
            throw new \RuntimeException("height must be 8 or higher");
        }

        $this->maxHeight = $maxHeight;
    }

    public function isCrop(): bool
    {
        return $this->crop;
    }

    public function setCrop(bool $crop): void
    {
        $this->crop = $crop;
    }

    public function getScalingAlgorithm(): string
    {
        return $this->scalingAlgorithm;
    }

    public function setScalingAlgorithm(string $scalingAlgorithm): void
    {
        $this->scalingAlgorithm = $scalingAlgorithm;
    }

    /**
     * This final resolution will be divisible by this value.
     * This is required to get chroma sub-sampling to work.
     *
     * It'll probably be 2.
     *
     * @return int
     */
    protected function getDimensionDivisor(): int
    {
        return 2;
    }

    /**
     * Determine the scale factor for the video.
     * If the video is supposed to be cropped than the source dimensions are already modified.
     * This method must be modified by implementing presets if there are limitations present by the codec.
     *
     * @param float[] $sourceDimensions
     *
     * @return float
     */
    protected function getScaleFactor(array $sourceDimensions): float
    {
        $candidates = [1.0];

        if ($this->getMaxWidth() !== null) {
            $candidates[] = $this->getMaxWidth() / $sourceDimensions[0];
        }

        if ($this->getMaxHeight() !== null) {
            $candidates[] = $this->getMaxHeight() / $sourceDimensions[1];
        }

        return min($candidates);
    }

    /**
     * Returns the dimensions for the final video.
     *
     * @param array $sourceStream
     *
     * @return int[]
     */
    public function getDimensions(array $sourceStream): array
    {
        if (isset($sourceStream['width']) && isset($sourceStream['height'])) {
            $sourceDimensions = [$sourceStream['width'], $sourceStream['height']];
        } else if ($this->getMaxWidth() && $this->getMaxHeight()) {
            $sourceDimensions = [$this->getMaxWidth(), $this->getMaxHeight()];
        } else {
            $sourceDimensions = [1280, 720]; // ¯\_(ツ)_/¯
        }

        if ($this->getMaxWidth() && $this->getMaxHeight() && $this->isCrop()) {
            $sourceDimensions[0] = min($sourceDimensions[0], $sourceDimensions[1] / $this->getMaxHeight() * $this->getMaxWidth());
            $sourceDimensions[1] = min($sourceDimensions[1], $sourceDimensions[0] / $this->getMaxWidth() * $this->getMaxHeight());
        }

        $scaleFactor = $this->getScaleFactor($sourceDimensions);
        $divisor = $this->getDimensionDivisor();

        return [
            (int)(round($sourceDimensions[0] * $scaleFactor / $divisor) * $divisor),
            (int)(round($sourceDimensions[1] * $scaleFactor / $divisor) * $divisor),
        ];
    }

    /**
     * This method returns the same as #getQuality except in one special case:
     * If the source video is smaller than what the max dimension are expecting than the quality will be boosted.
     * There are multiple reasons why that is useful:
     * - if you want to fill a specific space but only get a low-res video
     *   than it would be a bad idea to compress it as hard as you would the high-res video since it will be upscaled
     * - if you build some form of adaptive streaming but only have a low-res video
     *   than the higher quality settings will still increase the quality without wasting bandwidth by upscaling
     *
     * The quality is multiplied by the dimension difference to the power of 0.25.
     * So if the source video is 720p but you wanted 1080p than the boost will be 1.5 ** 0.25 so ~1.1.
     * If your requested quality was 0.8 than the quality would be quality ~0.9
     * If your requested quality was 0.6 than the quality would be quality ~0.65
     *
     * @param array $sourceStream
     *
     * @return float
     */
    public function getBoostedQuality(array $sourceStream): float
    {
        $scaleFactors = [];

        if ($this->getMaxWidth() !== null && !empty($sourceStream['width'])) {
            $scaleFactors[] = $this->getMaxWidth() / $sourceStream['width'];
        }

        if ($this->getMaxHeight() !== null && !empty($sourceStream['height'])) {
            $scaleFactors[] = $this->getMaxHeight() / $sourceStream['height'];
        }

        if (empty($scaleFactors) || min($scaleFactors) <= 1.0) {
            return $this->getQuality();
        }

        $boostFactor = min($scaleFactors) ** 0.25;
        return min(1.0, round($this->getQuality() * $boostFactor * 20) / 20);
    }

    /**
     * Calculates the target bitrate in kbit/s.
     *
     * How this value is interpreted depends on the specific implementation
     * but in general you can think of it as the maximum average bitrate.
     *
     * @param array $sourceStream
     *
     * @return int
     */
    public abstract function getTargetBitrate(array $sourceStream): int;

    public function requiresTranscoding(array $sourceStream): bool
    {
        if (parent::requiresTranscoding($sourceStream)) {
            return true;
        }

        $hasCorrectPixelFormat = isset($sourceStream['pix_fmt']) && strcasecmp($sourceStream['pix_fmt'], $this->getPixelFormat()) === 0;
        if (!$hasCorrectPixelFormat) {
            return true;
        }

        $hasFramerateInformation = isset($sourceStream['avg_frame_rate']) && isset($sourceStream['r_frame_rate']);
        if (!$hasFramerateInformation) {
            return true;
        }

        $isConstantFramerate = $sourceStream['avg_frame_rate'] === $sourceStream['r_frame_rate'];
        if (!$isConstantFramerate) {
            return true;
        }

        $hasTargetedFramerate = $this->getFramerate($sourceStream) === (string)$sourceStream['avg_frame_rate'];
        if (!$hasTargetedFramerate) {
            return true;
        }

        $hasDimensions = isset($sourceStream['width']) && isset($sourceStream['height']);
        if (!$hasDimensions) {
            return true;
        }

        $dimensions = $this->getDimensions($sourceStream);
        $hasTargetedSize = (int)$sourceStream['width'] === $dimensions[0] && (int)$sourceStream['height'] === $dimensions[1];
        if (!$hasTargetedSize) {
            return true;
        }

        if (!isset($sourceStream['bit_rate']) || $sourceStream['bit_rate'] > $this->getTargetBitrate($sourceStream)) {
            return true;
        }

        // TODO check and handle pixel aspect-ratios... someone somewhere will mess with that

        return false;
    }

    /**
     * The parameters specific to this encoder like bitrate.
     *
     * @param array $sourceStream
     *
     * @return array
     */
    protected abstract function getEncoderParameters(array $sourceStream): array;

    protected function getTranscodingParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-pix_fmt', $this->getPixelFormat());
        array_push($parameters, '-sws_flags', $this->getScalingAlgorithm());

        $filters = $this->getFilters($sourceStream);
        if (!empty($filters)) {
            array_push($parameters, '-vf', implode(',', $filters));
        }

        array_push($parameters, ...$this->getEncoderParameters($sourceStream));

        // I have todo some experimentation if there is a difference with the fps filter to the -r option
        //$framerate = $this->getFramerate($sourceStream);
        //array_push($parameters, '-r', $framerate);
        //array_push($parameters, '-vsync', 'cfr');

        return $parameters;
    }

    public function getFilters(array $sourceStream): array
    {
        $filters = [];

        // specifying fps here will prevent ffmpeg from scaling a lot of frames which are than dropped.
        $framerate = $this->getFramerate($sourceStream);
        $filters[] = "fps={$framerate}";

        $dimensions = $this->getDimensions($sourceStream);
        if ($this->isCrop()) {
            $filters[] = "scale={$dimensions[0]}:{$dimensions[1]}:force_original_aspect_ratio=increase";
            $filters[] = "crop={$dimensions[0]}:{$dimensions[1]}";
        } else {
            $filters[] = "scale={$dimensions[0]}:{$dimensions[1]}";
        }

        return $filters;
    }

    protected function getRemuxingParameters(array $sourceStream): array
    {
        return ['-c:v', 'copy'];
    }
}
