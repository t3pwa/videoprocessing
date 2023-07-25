<?php

namespace Faeb\Videoprocessing;


use Faeb\Videoprocessing\Exception\FormatException;
use Faeb\Videoprocessing\Preset\AbstractVideoPreset;
use Faeb\Videoprocessing\Preset\PresetInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FormatRepository implements SingletonInterface
{
    public function findFormatDefinition(array $options): ?array
    {
        $formats = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['formats'] ?? [];
        $format = $options['format'] ?? 'mp4';




        if (isset($formats[$format])) {
            return $formats[$format];
        }

        if (isset($formats["$format:default"])) {
            return $formats["$format:default"];
        }

        return null;
    }

    protected function getPresets(array $options = [], array $sourceStreams = null): array
    {
        $result = [];

        $options = $this->normalizeOptions($options);
        $definition = $this->findFormatDefinition($options);

        foreach (['video', 'audio', 'subtitle', 'data'] as $steamType) {
            if (empty($definition[$steamType])) {
                continue;
            }

            if ($options[$steamType]['disabled'] ?? false) {
                continue;
            }

            $sourceStream = [];
            if ($sourceStreams !== null) {
                $sourceStreamIndex = array_search($steamType, array_column($sourceStreams, 'codec_type'));
                if ($sourceStreamIndex === false) {
                    continue;
                } else {
                    $sourceStream = $sourceStreams[$sourceStreamIndex];
                }
            }

            $presetOptions = array_replace(
                $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['videoprocessing']['defaults'][$definition[$steamType][0]] ?? [],
                $definition[$steamType][1] ?? [],
                $options[$steamType] ?? []
            );

            $preset = GeneralUtility::makeInstance($definition[$steamType][0], $presetOptions);
            if (!$preset instanceof PresetInterface) {
                $type = is_object($preset) ? get_class($preset) : gettype($preset);
                throw new \RuntimeException("Expected " . PresetInterface::class . ", got $type");
            }

            $result[$steamType] = [
                'preset' => $preset,
                'stream' => $sourceStream,
            ];
        }

        return $result;
    }

    public function getProperties(array $options = [], array $sourceStreams = null): array
    {
        $properties = [
            'mime_type' => $this->buildMimeType($options, $sourceStreams),
        ];

        $presets = $this->getPresets($options, $sourceStreams);
        if (isset($presets['video']) && $presets['video']['preset'] instanceof AbstractVideoPreset) {
            $dimensions = $presets['video']['preset']->getDimensions($presets['video']['stream']);
            list($properties['width'], $properties['height']) = $dimensions;
        }

        return $properties;
    }

    public function buildParameters(?string $input, ?string $output, array $options = [], array $sourceStreams = null): array
    {
        $parameters = [];
        $options = $this->normalizeOptions($options);
        $definition = $this->findFormatDefinition($options);
        if ($definition === null) {
            throw new FormatException("No format defintion found for configuration: " . print_r($options, true));
        }

        if ($input !== null) {

            // TODO does this set start options if empty?

            if (isset($options['start'])) {
                array_push($parameters, '-ss', $options['start']);
            } else {
                $options['start'] = 0;
            }

            if (isset($options['duration'])) {
                array_push($parameters, '-t', $options['duration']);
            }

            array_push($parameters, '-i', $input);
        }

        $presets = $this->getPresets($options, $sourceStreams);
        $streamTypes = ['video' => '-vn', 'audio' => '-an', 'subtitle' => '-sn', 'data' => '-dn'];
        foreach ($streamTypes as $steamType => $disableParameter) {
            if (!isset($presets[$steamType])) {
                array_push($parameters, $disableParameter);
                continue;
            }

            $preset = $presets[$steamType];
            if (!$preset['preset'] instanceof PresetInterface) {
                $type = is_object($preset['preset']) ? get_class($preset['preset']) : gettype($preset['preset']);
                throw new \RuntimeException("Expected PresetInterface, got $type");
            }

            array_push($parameters, ...$preset['preset']->getParameters($preset['stream']));
        }

        if (!empty($definition['additionalParameters'])) {
            array_push($parameters, ...$definition['additionalParameters']);
        }

        if ($output !== null) {
            array_push($parameters, '-y', $output);
        }

        return $parameters;
    }

    public function buildParameterString(?string $input, ?string $output, array $options = [], array $sourceStreams = null): string
    {
        $escapeShellArg = static function ($parameter) {
            return preg_match('#^[\w:.-]+$#', $parameter) ? $parameter : escapeshellarg($parameter);
        };

        $parameters = $this->buildParameters($input, $output, $options, $sourceStreams);
        $parameters = array_map($escapeShellArg, $parameters);
        return implode(' ', $parameters);
    }

    /**
     * Builds the source type parameter.
     *
     * @param array $options
     * @param array $sourceStream
     *
     * @return string
     * @see https://wiki.whatwg.org/wiki/video_type_parameters
     */
    public function buildMimeType(array $options, array $sourceStream = null): string
    {
        $options = $this->normalizeOptions($options);
        $definition = $this->findFormatDefinition($options);
        if (!isset($definition['mimeType'])) {
            throw new \RuntimeException("A format is missing it's mimeType: " . print_r($definition, true));
        }

        $result = [$definition['mimeType']];

        $codecs = [];
        /** @var PresetInterface $preset */
        foreach ($this->getPresets($options, $sourceStream) as list('preset' => $preset)) {
            $codec = $preset->getMimeCodecParameter($sourceStream ?? []);
            if ($codec !== null) {
                $codecs[] = $codec;
            }
        }

        if (!empty($codecs)) {
            $result[] = 'codecs="' . implode(', ', $codecs) . '"';
        }

        return implode('; ', $result);
    }

    /**
     * This method normalizes the given options. This is important to prevent unnecessary reencodes.
     *
     * It is currently not possible to hook into the typo3 processing pipeline before it searches for a processed file.
     * That means that you must do the normalization yourself before asking typo3 for a processed video.
     *
     * @param array $options
     *
     * @return array
     * @todo this method must take much more effort to normalize the parameters because unnecessary encodes are horrible
     */
    public static function normalizeOptions(array $options): array
    {
        $result = [
            'format' => $options['format'] ?? 'mp4',
            // set start default, if not set
            'start' => $options['start'] ?? 1,
            'priority' => (int)($options['priority'] ?? 0),
        ];

        foreach (['audio', 'video', 'subtitles', 'data'] as $streamType) {
            if (!empty($options[$streamType])) {
                $result[$streamType] = $options[$streamType];
            }
        }
/*
        if (!empty($options['start'])) {
            $result['start'] = $options['start'];
        }
*/
        /*
        else {
            $result['start'] = 0;
        }
        */

        if (!empty($options['duration'])) {
            $result['duration'] = $options['duration'];
        }

        return array_filter($result); // remove all empty/falsy values from the first level
    }
}
