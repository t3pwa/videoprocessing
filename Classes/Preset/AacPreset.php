<?php

namespace Faeb\Videoprocessing\Preset;


class AacPreset extends AbstractAudioPreset
{
    /**
     * This table contains a very rough estimate what bitrates to expect from what vbr setting.
     * This is in no way scientific and there should be a lot more testing.
     * I noticed that the table that ffmpeg provides is way of ~ i'd like to know what im doing wrong.
     *
     * The table contains 2 conditions and one value
     * 1 = the fdk profile for which to use this vbr value
     * 2 = lower threshold bitrate per channel, use the row of which this value is lower than your target (only tested with stereo)
     * 3 = the vbr value to use if the previous conditions are met
     * The conditions must be processed in the order provided.
     * If none of these match than a fallback is used using constant bitrate.
     *
     * @see https://hydrogenaud.io/index.php/topic,95989.0.html
     * @see https://wiki.hydrogenaud.io/index.php?title=Fraunhofer_FDK_AAC#Bitrate_Modes
     * @see \Faeb\Videoprocessing\Preset\AacPreset::getFdkVbrValue
     */
    private const FDK_VBR_MAPPING = [
        ['aac_low', 72, 5],
        ['aac_low', 56, 4],
        ['aac_low', 48, 3],
        ['aac_low', 40, 2],
        ['aac_low', 32, 1],
        ['aac_he', 36, 3],
        ['aac_he', 30, 2], // actually 32 but i wanted to use it at 50% quality
        ['aac_he', 18, 1],
    ];

    /**
     * Weather ot not to use libfdk for audio encoding.
     * libfdk will sound better especially at lower bitrates than the native ffmpeg encoder.
     * However it is probably not present on your system unless you compiled ffmpeg yourself.
     *
     * @var bool
     */
    private $fdkAvailable = true;

    public function getCodecName(): string
    {
        return 'aac';
    }

    public function getMimeCodecParameter(array $sourceStream): string
    {
        if ($this->requiresTranscoding($sourceStream)) {
            $profile = ['aac_low' => '2', 'aac_he' => '5'][$this->getProfile()];
        } else {
            $profile = ['LC' => '2', 'HE-AAC' => '5'][$sourceStream['profile']];
        }

        return sprintf('mp4a.40.%s', $profile);
    }

    protected function getSampleRates(): array
    {
        return [48000, 44100, 32000];
    }

    /**
     * Here are some considerations:
     * - Apple claims to only support 160 kbit/s aac-lc audio within video (although this spec seems to have never changed ~ and are probably outdated)
     * - Youtube used to use 152 kbit/s back when they combined video and audio streams which is what this preset is for
     * - Youtube now (since ~ 2013) uses 128 kbit/s audio next to the video stream
     * - Spotify uses 96/160 and 320 kbit/s vorbis depending on your quality setting and platform
     * - Android recommends between between 128 kbit/s and 192 kbit/s and in my experience can completely fail to decode audio otherwise
     *
     * Here some examples of the bitrate using different quality settings (with fdk available)
     * - 100% = 192 kbit/s the highest android recommends
     * - 80% = 128 kbit/s default ~ usually a safe bet and the quality youtube uses
     * - 60% = 80 kbit/s at this point HE-AAC will be used which might be a compatibility consideration
     * - 56% = 72 kbit/s this is the bitrate DAB+ uses with HE-AAC
     * - 50% = 60 kbit/s
     * - 42% = 48 kbit/s the lowest recommended bitrate i'd even consider ~ after this it'll start to sound really bad
     * - 30% = 32 kbit/s
     * - 0% = 16 kbit/s
     *
     * I also give the native ffmpeg encoder a little bitrate boost at the lower end
     * because it does not support he-aac and even with lc-aac it's noticeably worse at lower bitrates.
     * At higher bitrates the difference is negligible.
     * - 100% = 192 kbit/s
     * - 80% = 134 kbit/s
     * - 50% = 72 kbit/s
     * - 30% = 46 kbit/s
     * - 00% = 32 kbit/s
     *
     * @see http://fooplot.com/#W3sidHlwZSI6MCwiZXEiOiIyKk1hdGgucm91bmQoOCsoOTYtOCkqeCoqMikiLCJjb2xvciI6IiMwMDAwMDAifSx7InR5cGUiOjAsImVxIjoiMSpNYXRoLnJvdW5kKDgrKDk2LTgpKngqKjIpIiwiY29sb3IiOiIjMDAwMDAwIn0seyJ0eXBlIjoxMDAwLCJ3aW5kb3ciOlsiMCIsIjEiLCIwIiwiMTkyIl0sImdyaWQiOlsiIiwiMTYiXX1d
     */
    protected function getBitratePerChannel(): int
    {
        $max = 96;
        $min = $this->isFdkAvailable() ? 8 : 16;
        return round($min + ($max - $min) * $this->getQuality() ** 2);
    }

    /**
     * Determines the aac profile to use.
     *
     * Note that this is the name of the profile that the fdk uses.
     * mp4 metadata has a different name.
     *
     * @return string
     */
    protected function getProfile(): string
    {
        // with 40 kbit/s per channel (80 kbit/s stereo) use he-aac since it'll sound better
        return $this->getBitratePerChannel() <= 40 ? 'aac_he' : 'aac_low';
    }

    public function requiresTranscoding(array $sourceStream): bool
    {
        if (parent::requiresTranscoding($sourceStream)) {
            return true;
        }

        // I allow LC and HE
        if (!in_array(strtoupper($sourceStream['profile']), ['HE-AAC', 'LC'], true)) {
            return true;
        }

        return false;
    }

    protected function getFdkVbrValue(): ?int
    {
        $profile = $this->getProfile();
        $bitrate = $this->getBitratePerChannel();
        foreach (self::FDK_VBR_MAPPING as list($targetProfile, $targetBitrate, $vbrValue)) {
            if ($profile !== $targetProfile) {
                continue;
            }

            if ($targetBitrate > $bitrate) {
                continue;
            }

            return $vbrValue;
        }

        return null;
    }

    protected function getFdkEncoderParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-c:a', 'libfdk_aac');
        array_push($parameters, '-profile:a', $this->getProfile());

        $vbrValue = $this->getFdkVbrValue();
        if ($vbrValue !== null) {
            array_push($parameters, '-vbr:a', $vbrValue);
        } else {
            array_push($parameters, '-b:a', $this->getBitrate($sourceStream) . 'k');
        }

        return $parameters;
    }

    protected function getNativeEncoderParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-c:a', 'aac');
        array_push($parameters, '-b:a', $this->getBitrate($sourceStream) . 'k');
        // TODO experiment with a high-pass filter for lower bitrates ~ just like fdk does natively

        return $parameters;
    }

    protected function getEncoderParameters(array $sourceStream): array
    {
        if ($this->isFdkAvailable()) {
            return $this->getFdkEncoderParameters($sourceStream);
        } else {
            return $this->getNativeEncoderParameters($sourceStream);
        }
    }

    public function isFdkAvailable(): bool
    {
        return $this->fdkAvailable;
    }

    public function setFdkAvailable(bool $fdkAvailable): void
    {
        $this->fdkAvailable = $fdkAvailable;
    }
}
