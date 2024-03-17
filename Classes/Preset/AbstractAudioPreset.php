<?php

namespace Faeb\Videoprocessing\Preset;


abstract class AbstractAudioPreset extends AbstractCompressiblePreset
{
    /**
     * This is a tolerance for a higher bitrate.
     * If the source bitrate is below the target bitrate*1.25 than no transcode will happen.
     * If a transcode is necessary than the bitrate will be *1.25 higher than the source if that is lower than the target.
     *
     * Examples with a 128 kbit/s target (2 channels with 64 kbit/s each):
     * - If the source is a 192 kbit/s aac than it will be transcoded to 128 kbit/s.
     * - If the source is a 160 kbit/s aac than no transcode will happen.
     * - If the source is a 128 kbit/s aac than no transcode will happen.
     * - If the source is a 96 kbit/s aac than no transcode will happen.
     * - If the source is a 192 kbit/s mp3 than the aac stream will have 128 kbit/s.
     * - If the source is a 128 kbit/s mp3 than the aac stream will have 128 kbit/s.
     * - If the source is a 64 kbit/s mp3 than the aac stream will have 80 kbit/s.
     */
    const BITRATE_TOLERANCE = 1.25;

    /**
     * Returns the maximum number of channels supported by this preset.
     *
     * This is usually 2.
     *
     * @return int
     */
    protected function getMaxChannels(): int
    {
        return 2;
    }

    public function getChannels(array $sourceStream): int
    {
        $maxChannels = $this->getMaxChannels();
        if (!isset($sourceStream['channels'])) {
            return $maxChannels;
        }

        return min($sourceStream['channels'], $maxChannels);
    }

    /**
     * The sample rates allowed.
     *
     * If there is an exact match with the source than it will be used.
     * If there isn't than a check is run which tries to find the sample rate which is a multiple of the source.
     * If that fails than the next higher sample rate will be chosen
     * and if that also fails the highest one will be chosen.
     *
     * Make sure the first item is your preferred sample rate since it will be used if the source is unknown.
     *
     * @return array
     */
    protected abstract function getSampleRates(): array;

    public function getSampleRate(array $sourceStream): int
    {
        $sampleRates = $this->getSampleRates();
        if (!isset($sourceStream['sample_rate']) || !is_numeric($sourceStream['sample_rate'])) {
            return reset($sampleRates);
        }

        $sourceSampleRate = (int)$sourceStream['sample_rate'];

        // try to find an exactly matching sample rate
        // or use a sample rate that is a multiple of the source
        sort($sampleRates); // make sure to check from the lowest to the highest
        foreach ($sampleRates as $sampleRate) {
            if ($sampleRate % $sourceSampleRate === 0) {
                return $sampleRate;
            }
        }

        // use the next higher sample rate
        foreach ($sampleRates as $sampleRate) {
            if ($sampleRate > $sourceSampleRate) {
                return $sampleRate;
            }
        }

        // use the highest allowed sample rate
        return end($sampleRates);
    }

    /**
     * The expected bitrate per channel in kbit/s.
     *
     * @return int
     */
    protected abstract function getBitratePerChannel(): int;

    /**
     * the target bitrate in kbit/s.
     *
     * @param array $sourceStream
     *
     * @return int
     */
    public function getBitrate(array $sourceStream): int
    {
        $maxBitrate = $this->getBitratePerChannel() * $this->getChannels($sourceStream);
        if (!isset($sourceStream['bit_rate'])) {
            return $maxBitrate;
        }

        return min($sourceStream['bit_rate'] * self::BITRATE_TOLERANCE, $maxBitrate);
    }

    public function requiresTranscoding(array $sourceStream): bool
    {
        if (parent::requiresTranscoding($sourceStream)) {
            return true;
        }

        if (!isset($sourceStream['sample_rate']) || $sourceStream['sample_rate'] !== (string)$this->getSampleRate($sourceStream)) {
            return true;
        }

        if (!isset($sourceStream['channels']) || $sourceStream['channels'] !== $this->getChannels($sourceStream)) {
            return true;
        }
        // TODO maybe check channel layout? it should not matter up until stereo but ~ i don't know

        if (!isset($sourceStream['bit_rate']) || ($sourceStream['bit_rate'] / 1024) > ($this->getBitrate($sourceStream) * self::BITRATE_TOLERANCE)) {
            return true;
        }

        return false;
    }

    protected abstract function getEncoderParameters(array $sourceStream): array;

    protected function getTranscodingParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-ar', (string)$this->getSampleRate($sourceStream));
        array_push($parameters, '-ac', (string)$this->getChannels($sourceStream));
        array_push($parameters, ...$this->getEncoderParameters($sourceStream));

        return $parameters;
    }

    protected function getRemuxingParameters(array $sourceStream): array
    {
        return ['-c:a', 'copy'];
    }
}
