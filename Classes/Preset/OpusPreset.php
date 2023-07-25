<?php

namespace Faeb\Videoprocessing\Preset;


class OpusPreset extends AbstractAudioPreset
{
    public function getCodecName(): string
    {
        return 'opus';
    }

    public function getMimeCodecParameter(array $sourceStream): string
    {
        return 'opus';
    }

    protected function getSampleRates(): array
    {
        return [48000];
    }

    /**
     * The expected bitrate per channel in kbit/s.
     *
     * @return int
     * @see http://fooplot.com/#W3sidHlwZSI6MCwiZXEiOiIyKk1hdGgucm91bmQoNisoNzItNikqeCoqMikiLCJjb2xvciI6IiMwMDAwMDAifSx7InR5cGUiOjAsImVxIjoiMSpNYXRoLnJvdW5kKDYrKDcyLTYpKngqKjIpIiwiY29sb3IiOiIjMDAwMDAwIn0seyJ0eXBlIjoxMDAwLCJ3aW5kb3ciOlsiMCIsIjEiLCIwIiwiMTkyIl0sImdyaWQiOlsiIiwiMTYiXX1d
     */
    protected function getBitratePerChannel(): int
    {
        $max = 72;
        $min = 6;
        return round($min + ($max - $min) * $this->getQuality() ** 2);
    }

    protected function getEncoderParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-c:a', 'libopus');
        array_push($parameters, '-vbr:a', 'on'); // it's the default but i like to have this explicit
        array_push($parameters, '-b:a', $this->getBitrate($sourceStream) . 'k');

        return $parameters;
    }
}
