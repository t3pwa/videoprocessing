<?php

namespace Faeb\Videoprocessing\Preset;

class CustomWMVFormat extends FFMpeg\Format\Video\DefaultVideo
{
public function __construct($audioCodec = 'wmav2', $videoCodec = 'wmv2')
{
$this
->setAudioCodec($audioCodec)
->setVideoCodec($videoCodec);
}

public function supportBFrames()
{
return false;
}

public function getAvailableAudioCodecs()
{
return array('wmav2');
}

public function getAvailableVideoCodecs()
{
return array('wmv2');
}
}