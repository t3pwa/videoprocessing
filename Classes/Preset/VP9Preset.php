<?php

namespace Faeb\Videoprocessing\Preset;


use TYPO3\CMS\Core\Utility\MathUtility;

class VP9Preset extends AbstractVideoPreset
{
    /**
     * Defines the limits of the different vp9 levels.
     * 0. luma samples per frame
     * 1. luma samples per second
     * 2. max dimensions
     * 3. max bitrate per second in kbit/s
     * 4. max tiles
     *
     * @see https://www.webmproject.org/vp9/levels/
     */
    private const LEVEL_DEFINITION = [
        '1.0' => [36864, 829440, 512, 200, 1],
        '1.1' => [73728, 2764800, 768, 800, 1],
        '2.0' => [122880, 4608000, 960, 1800, 1],
        '2.1' => [245760, 9216000, 1344, 3600, 2],
        '3.0' => [552960, 20736000, 2048, 7200, 4],
        '3.1' => [983040, 36864000, 2752, 12000, 4],
        '4.0' => [2228224, 83558400, 4160, 18000, 4],
        '4.1' => [2228224, 160432128, 4160, 30000, 4],
        '5.0' => [8912896, 311951360, 8384, 60000, 8],
        '5.1' => [8912896, 588251136, 8384, 120000, 8],
        '5.2' => [8912896, 1176502272, 8384, 180000, 8],
        '6.0' => [35651584, 1176502272, 16832, 180000, 16],
        '6.1' => [35651584, 2353004544, 16832, 240000, 16],
        '6.2' => [35651584, 4706009088, 16832, 480000, 16],
    ];

    /**
     * @var string
     */
    private $level = '3.0';

    /**
     * Encoding speed, a number between 0 and 5 where 5 is the fastest
     *
     * @var int
     */
    private $speed = 2;

    public function getCodecName(): string
    {
        return 'vp9';
    }

    public function getMimeCodecParameter(array $sourceStream): string
    {
        return 'vp9.0';
    }

    /**
     * @return array
     */
    protected function getLevelDefinition(): array
    {
        return self::LEVEL_DEFINITION[$this->getLevel()];
    }

    /**
     * @return int
     */
    protected function getMaxLumaSamples(): int
    {
        $levelDefinition = self::LEVEL_DEFINITION[$this->getLevel()];
        return min($levelDefinition[0], floor($levelDefinition[1] / $this->getMaxFramerate()));
    }

    protected function getMaxDimension(): int
    {
        return $this->getLevelDefinition()[2];
    }

    /**
     * The maximum bitrate allowed by the configured level.
     *
     * @return int
     */
    protected function getBitrateLimit(): int
    {
        return $this->getLevelDefinition()[3];
    }

    /**
     * @return int
     */
    protected function getMaxTiles(): int
    {
        return $this->getLevelDefinition()[4];
    }

    protected function getScaleFactor(array $sourceDimensions): float
    {
        $maxLumaSamples = $this->getMaxLumaSamples();
        $maxDimension = $this->getMaxDimension();
        $divisor = $this->getDimensionDivisor();
        $maxDimensions = [
            min($maxDimension, floor(sqrt($maxLumaSamples * $sourceDimensions[0] / $sourceDimensions[1]) / $divisor) * $divisor),
            min($maxDimension, floor(sqrt($maxLumaSamples * $sourceDimensions[1] / $sourceDimensions[0]) / $divisor) * $divisor),
        ];
        return min(
            $maxDimensions[0] / $sourceDimensions[0],
            $maxDimensions[1] / $sourceDimensions[1],
            parent::getScaleFactor($sourceDimensions)
        );
    }

    public function getTargetBitrate(array $sourceStream): int
    {
        $pixels = array_product($this->getDimensions($sourceStream));
        $framerate = MathUtility::calculateWithParentheses($this->getFramerate($sourceStream));
        $quality = $this->getBoostedQuality($sourceStream) ** 2 * 0.9 + 0.1;
        $bitrate = round($pixels ** 0.85 * $framerate ** 0.5 * $quality * 0.005);
        return min($bitrate, $this->getBitrateLimit());
    }

    public function getCrf(array $sourceStream): float
    {
        $max = 23;
        $min = $max + 14 / 0.2; // +14 every 0.2 for less than half the bitrate (+10 for actually half in my testing but vp9 can take more compared to h264)
        $result = $min + ($max - $min) * $this->getBoostedQuality($sourceStream);
        return min($result, 63); // this formula actually overshoots the minimum quality possible in vp9
    }

    public function requiresTranscoding(array $sourceStream): bool
    {
        if (parent::requiresTranscoding($sourceStream)) {
            return true;
        }

        if (!isset($sourceStream['profile']) || strcasecmp($sourceStream['profile'], 'Profile 0') !== 0) {
            return true;
        }

        // the level of a vp9 stream cannot be determined ~ ffprobe always returns -99
        //if (!isset($sourceStream['level']) || $sourceStream['level'] > $this->getLevel() || $sourceStream['level'] < 1) {
        //    return true;
        //}

        return false;
    }

    /**
     * The parameters specific to this encoder like bitrate.
     *
     * @param array $sourceStream
     *
     * @return array
     * @see https://developers.google.com/media/vp9/settings/vod/
     */
    protected function getEncoderParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-c:v', 'libvpx-vp9');

        array_push($parameters, '-quality:v', 'good');
        array_push($parameters, '-speed:v', $this->getSpeed());

        array_push($parameters, '-profile:v', '0');
        array_push($parameters, '-level:v', $this->getLevel());

        array_push($parameters, '-crf:v', round($this->getCrf($sourceStream)));
        $maxrate = $this->getTargetBitrate($sourceStream);
        array_push($parameters, '-maxrate:v', round($maxrate * 1.5) . 'k');
        array_push($parameters, '-b:v'/*  */, round($maxrate * 1.0) . 'k');
        array_push($parameters, '-minrate:v', round($maxrate * 0.5) . 'k');

        $dimensions = $this->getDimensions($sourceStream);
        $log2columns = floor(log(min($this->getMaxTiles(), $dimensions[0] / 256), 2));
        array_push($parameters, '-tile-columns:v', $log2columns);
        array_push($parameters, '-threads:v', 2 ** $log2columns * 2);
        array_push($parameters, '-g:v', 240); // it seems to not always be set

        return $parameters;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): void
    {
        if (!isset(self::LEVEL_DEFINITION[$level])) {
            throw new \RuntimeException("Level $level unknown");
        }

        $this->level = $level;
    }

    public function getSpeed(): int
    {
        return $this->speed;
    }

    public function setSpeed(int $speed): void
    {
        if ($speed < 0 || $speed > 5) {
            throw new \RuntimeException("Speed must be between 0 and 5, got $speed.");
        }

        $this->speed = $speed;
    }
}
