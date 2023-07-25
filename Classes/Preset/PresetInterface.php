<?php


namespace Faeb\Videoprocessing\Preset;


interface PresetInterface
{
    /**
     * The constructor must accept an array of options.
     * These options may be specific to the preset.
     *
     * @param array $options
     */
    public function __construct(array $options = []);

    /**
     * This method must return you ffmpeg parameters for the stream this preset is targeting.
     *
     * The parameter is the stream configuration from the source.
     * The preset may optimize around this information but it must be able to work without it.
     *
     * @param array $sourceStream
     *
     * @return array
     */
    public function getParameters(array $sourceStream): array;

    /**
     * Has to build the current codec mime parameter necessary.
     * This is necessary for the html type parameter.
     *
     * @return string
     * @see https://www.ietf.org/rfc/rfc4281.txt
     * @see https://wiki.whatwg.org/wiki/video_type_parameters
     * @see https://chromium.googlesource.com/chromium/src/media/+/master/base/mime_util_internal.cc
     */
    public function getMimeCodecParameter(array $sourceStream): ?string;
}
