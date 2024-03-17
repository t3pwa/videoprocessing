<?php

namespace Faeb\Videoprocessing\Preset;


abstract class AbstractPreset implements PresetInterface
{
    public function __construct(array $options = [])
    {
        if (!empty($options)) {
            $this->setOptions($options);
        }
    }

    public function setOptions(array $options): void
    {
        $possibleOptions = static::getPossibleOptions();

        $noneExistingOptions = array_diff_key($options, $possibleOptions);
        if (count($noneExistingOptions) > 0) {
            $noneExistingOptionsStr = implode(', ', array_keys($noneExistingOptions));
            $possibleOptionsStr = implode(', ', array_keys($possibleOptions));
            $msg = "The option(s) $noneExistingOptionsStr do not exist, possible options are: $possibleOptionsStr";
            throw new \RuntimeException($msg);
        }

        foreach ($options as $name => $value) {
            try {
                $this->{$possibleOptions[$name]}($value);
            } catch (\Exception $e) {
                $className = get_class($this);
                $msg = "Error while configuring $name in $className: " . $e->getMessage();
                throw new \RuntimeException($msg, 1553159340, $e);
            }
        }
    }

    /**
     * This method returns which options are available.
     *
     * The key will be the option name while the value is the setter for setting it after the preset is created.
     * eg. array("quality" => "setQuality")
     *
     * @return array
     */
    protected static function getPossibleOptions(): array
    {
        $possibleOptions = [];
        foreach (get_class_methods(static::class) as $method) {
            if (substr($method, 0, 3) !== 'set') {
                continue;
            }

            if ($method === 'setOptions') {
                continue;
            }

            $optionName = lcfirst(substr($method, 3));
            $possibleOptions[$optionName] = $method;
        }

        return $possibleOptions;
    }
}
