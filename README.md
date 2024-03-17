[![Packagist Version](https://img.shields.io/packagist/v/hn/video.svg)](https://packagist.org/packages/hn/video)
[![Packagist](https://img.shields.io/packagist/l/hn/video.svg)](https://packagist.org/packages/hn/video)
[![Packagist](https://img.shields.io/packagist/dt/hn/video.svg)](https://packagist.org/packages/hn/video)
[![Packagist](https://img.shields.io/packagist/dm/hn/video.svg)](https://packagist.org/packages/hn/video)
[![Build status](https://img.shields.io/bitbucket/pipelines/hauptsachenet/video/master.svg)](https://bitbucket.org/hauptsachenet/video/addon/pipelines/home)

# Video compression for TYPO3

This extension adds video conversion/compression capability to TYPO3.

## Why?

There are valid reasons to host videos yourself but correct video compression isn't too easy.
TYPO3 already handles image compression (at least sometimes). So wouldn't it be awesome if videos are managed too?  

## Features

- **integrates seamlessly** though a FileRenderer into every element that uses typo3's `<f:media>` view helper
- automatically converts many video formats like mov and mkv into browser friendly formats like **mp4** and **webm**
- **custom dimensions** and cropping for specific use cases like background videos or animated thumbnails 
- **backend module** with overview over all processed videos
- live updating **progress information** as a placeholder and in the dashboard until the video is processed
- **caching aware**: (v11: not working yet) the cache of the page with the video will be cleared as soon as the processing is done
- (the way videos are processed can be swapped. By default between local ffmpeg (and [CloudConvert]) )
  but you can roll out your own converters that run though ssh or some other service 

## How does it work

- It starts with a new `FileRenderer` which automatically kicks in if you use the `<f:media>` view helper.
- This renderer will go through the normal TYPO3 file processing pipeline using a new `Video.CropScale` task.
- Videos are then processed either by the `ffmpeg` command ([depricated in v11 or by [CloudConvert]. ) 
- During processing, the `FileRenderer` will render a simple progress percentage.
- After processing is done the video will be rendered similar to the normal html5 video renderer.

## How to install

- Install the extension using `composer require (hn/video) for TYPO3 <= 9`.
- Install the extension using `composer require (t3pwa/videoprocessing) for TYPO3 >= 10`.
- via git repository https://github.com/t3pwa/videoprocessing TYPO3 >= 10
- 
  
- Either make sure that ffmpeg is available
  [depricated or configure a [CloudConvert] api key in the extension settings.]

## composer req ffmpeg

##php-ffmpeg
may be used (for poster image generation), dev)


  
- Make sure that the `video:process` command is run regularly.
  
- This command will run the conversion ( if you use local `ffmpeg` or php-gffmpeg )
 
  (If you use CloudConvert, this command is technically not required since everything can be handled though callbacks
  but it will increase the accuracy of the progress information and act as a fallback if the callbacks
  don't come though for whatever reason.)
 
- Ensure that the php configurations `upload_max_filesize` and `post_max_size` are set properly.
  1GB are recommended. 
- (If you use CloudConvert for free then that is also the max size they allow you.)
  
## Simple Configuration

There are some basic configuration options within the ext_conf which you can set though the TYPO3 backend globally.

- how to use ffmpeg (CloudConvert or ffmpeg command, or php-ffmpeg)
- choose between performance presets like h264 slow, veryslow and if you want to also encode vp9 (not implemented with php-ffmpeg, yet)
- change the codec level to change resolution, filesize and compatibility (only local ffmpeg)
- decide on video/audio compression using an easy percentage value that's similar to the jpeg quality percentage (only local ffmpeg)


These options are read using TYPO3 9's `ExtensionConfiguration` class so if you use TYPO3 9,
you can also define these options programmatically in you `AdditionalConfiguration.php` like in this example:


<!--
obsolete!
```php
<?php
if (getenv('CLOUDCONVERT_APIKEY')) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['video']['converter'] = 'CloudConvert';
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['video']['cloudConvertApiKey'] = getenv('CLOUDCONVERT_APIKEY');
}
```
-->

## Usage

Just use the `<f:media>` view helper like textmedia does.

```html
<f:media file="{video}" />
```

You can change a parameters about the resulting file using the `additionalParameters` attribute.

This is an example of an autoplaying video as you might use it in a stage or a content element as animated image.

```html
<f:media file="{video}" additionalParameters="{
    autoplay: 3,
    duration; 30,
    video: {quality: 0.6},
    audio: {disabled: 1}
}" />
```
<!-- ToDo: additional Parameters? not valid, additionalAttributes or AdditionalConfig -->


### options

- *autoplay*: 0, 1, 2, 3; these options are also available in the file reference
    - 0: disables autoplay. (default)
    - 1: enables autoplay and mutes the video.
    - 2: like 1 but loops the video.
    - 3: like 2 but disables controls. This is similar to gif animations.
- *muted*: false, true; sets the muted attribute and is set by default if autplay is `>= 1`.
  Note that this won't remove the audio stream from the video since the video can be unmuted using the controls.
  To actually disable audio set `{audio: {disabled: 1}}`
- *start*: seconds; a time offset. 
- *duration*: seconds; the max duration of the video.
- *video*: array; these options will be passed to the VideoPreset
    - disabled: false, true; Disables the video stream.
    - quality: 0.0 - 1.0; Basic Quality abstraction over the video quality.
      This should be roughly comparable to jpeg settings. The default is 0.8.
    - maxWidth: null, int; Note that the max video resolution is also limited by the level and by the framerate.
    When null is used, then either the source video or the level restriction will dictate the result resolution.
    - maxHeight: null, int; Same comment as maxWidth.
    - maxFramerate: int; Sets a limit to the framerate. The default is 30.
      You may need to increase the level in order to sustain acceptable resolutions.
      The Framerate may be lower to reduce stuttering. Here are a few examples:
        - if the source has a framerate of 48, then 24 will be used
        - if the source has a framerate of 50, then 25 will be used
        - if the source has a framerate of 32, then 30 will be used since dropping down to 16 would be a huge jump
    - crop: false, true; By default the video keep it's aspect ratio.
      If this is set the video will be cropped to fill the aspect ratio.
      Note that the video will never be upscaled.
    - level: 1.0 - 6.2; The compatibility level of the specific codec.
      [h264 level] and [VP9 level] are not the same. If you plan to use both codecs, use levels which are similar.
      Here are my recommendations:
        - 3.0: the default; ~480p@30, a bit more for VP9
        - 3.1: ~720p@30 and ~576p@60
        - 4.0: ~1080p@30 and ~720p@60
        - 4.1: ~1080p@30 and ~720p@60; ~1080p@60 for VP9
- *audio*: array; these options will be passed to the AudioPreset
    - disabled: false, true; Disables the audio stream.
    - quality: 0.0 - 1.0; Basic Quality abstraction over the video quality.
- *formats*: array; A map of formats defined as `{[format1]: {video: {}, audio: {}}, [format2]: ... }`.
  This will override the default formats. The video/audio part are the same as on the root level.
  Available formats are `mp4` and `webm`. For more information read the in-depth configuration.
  
Note that the `width=""` and `height=""` attributes of `<f:media>` are ignored for the encoding process.
This is to prevent unnecessary transcoding and because you can maximize the video so scaling
to fit the frame is not always the best idea.

  
## In-Depth Configuration

To understand the the configuration, you'll need to know some basics first.
There are 3 levels:

1. the *format*: eg. mp4 or webm.
   The format defines what the file is supposed to be, which streams are in it, what mime type it is
   and format specific ffmpeg parameters. The streams are the interesting part.
2. the stream *preset*: which defines an audio or video stream. Examples: `H264Preset` and `AacPreset`.
   Presets are classes which define how the ffmpeg command will look like.
   They are fairly complex but can create your own ones if you need a specific format that i haven't created.
   But most likely you want to configure the existing presets.
3. *preset configuration*: Is a simple array which maps onto the setters of the Preset.
   There you can tune compatibility, resolution, framerate and quality.


### The format definition

```php
<?php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['formats']['mp4'] = [
    'fileExtension' => 'mp4',
    'mimeType' => 'video/mp4',
    
    <!-- todo namespace -->
    
    'video' => [\Hn\Video\Preset\H264Preset::class],
    'audio' => [\Hn\Video\Preset\AacPreset::class],
    'additionalParameters' => ['-movflags', '+faststart', '-map_metadata', '-1', '-f', 'mp4'],
];
```

That is the default format definition of the mp4 video container. A format definition consists of these parts:

- `fileExtension` which simply defines what file extension the resulting file must have.
  While the default format definitions use the file extension also as the identifier, yours don't have to.
- `mimeType` for the `<source type="">`. Although a codec extension will be added.
- `video` defines a *preset* for the video stream. Omit or set to null if your format does not require/support video.
  You can add a second argument with options which will be passed to the constructor
  (if not overridden in other places).
- `audio` defines a *preset* for the audio stream.
  Omit or set to null if your format does not require/support audio.
- `subtitle` defines a *preset* for the subtitle stream.
  There are none implemented by default but the option is there.
- `data` defines a *preset* for the data stream.
  There are none implemented by default but the option is there.
- `additionalParameters` is an array of parameters that are added to the ffmpeg command
- `priority` an integer by which the created tasks are sorted.
  This is used to defer the webm format compared to mp4 since the mp4 will be done much more quickly.

You can configure formats in `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['formats']['{format-name}']`.
There is a list of formats that is used by default.
It is defined in `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['default_video_formats']` and looks like this:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['default_video_formats'] = [
    // 'webm' => [], // this format is by default disabled but can be enabled in ext_conf.
    'mp4' => [],
    
    // you can even pass options to the presets within here
    'mp4' => ['level' => '4.0']
    // in this case i increate the compatibility level to 4.0 which allows full-hd.
    // read more about that in the preset configuration.
];
```

The other way of using the format is ad-hoc within the media view helper.

```html
<f:media file="{file}" additionalConfig="{formats: {mp4: {}}}" />
<!-- you can pass preset options here as well -->
<f:media file="{file}" additionalConfig="{formats: {mp4: {video: {quality: 0.6, width: 400, height: 400, crop: 1}}}}" />
```

### The preset

The presets are classes which define how a stream is handled.
You probably want to understand the basic concept of them
because it'll make it easier to understand the preset configuration.

- `PresetInterface` is the base and explains what you need.
  A minimal preset would just define `getParameters`
  which must return an array of ffmpeg arguments like `['-c:v', 'libx264']`.
  The preset configuration is simply passed as an array to the constructor.
  A result of `ffprobe` is passed as an argument to `getParameters`
  so that decisions can be implemented based on the source material.
- `AbstractPreset` is a base implementation that handles options by searching a setter method for them.
  So that the option `quality` is passed as `setQuality`.
- `AbstractCompressiblePreset` sits on top of the `AbstractPreset` and adds a 2 concepts
    - an abstraction over the quality using a value `> 0.0` and `<= 1.0` which should roughly equal jpeg's options
    - the "this stream does not need to be touched" so that a stream with equal or lower quality doesn't get re-encoded
- `AbstractVideoPreset` and `AbstractAudioPreset` start to go into specifics of the stream type.
  The video preset handles framerate, video resolution and cropping.
  The audio preset handles channels and sample rates.
  If you want/need to implement eg. H265 support, you probably want to extend one of those.
- `AacPreset`, `H264Preset`, `OpusPreset`, `VP9Preset` are the concrete implementations of formats.
  Use them as example implementations if you need to.

### The preset configuration

These configurations allow you in multiple places to tweak the streams within a video/file.

You can define them globally for a specific stream type:
```php
<?php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['defaults'][\Hn\Video\Preset\H264Preset::class]['quality'] = 0.6;
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['defaults'][\Hn\Video\Preset\AacPreset::class]['quality'] = 1.0;
```

You can define them within the format definition itself:

```php
<?php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['formats']['mp4'] = [
    'fileExtension' => 'mp4',
    'mimeType' => 'video/mp4',
    
    //  @ToDo Namespace
    'video' => [\Hn\Video\Preset\H264Preset::class, ['quality' => 0.6]],
    'audio' => [\Hn\Video\Preset\AacPreset::class, ['quality' => 1.0]],
    
    'additionalParameters' => ['-movflags', '+faststart', '-map_metadata', '-1', '-f', 'mp4'],
];
```

You can define them on the default set of formats used. Here you target them by there type eg. video, audio:
```php
<?php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['default_video_formats']['mp4'] = [
    'video' => ['quality' => 0.6],
    'audio' => ['quality' => 1.0]
];
```

And you can define them within the media view helper similar to the definition above.
Note that by defining the `formats` key, the `default_video_formats` configurations is overridden. 

```html
<f:media file="{file}" additionalConfig="{formats: {mp4: {video: {quality: 0.6}, audio: {quality: 1.0}}}}" />
```

You can also define them within the view helper without overriding the format list.
Look into the _usage_ section for an easy explanation.

```html
<f:media file="{file}" additionalConfig="{video: {quality: 0.6}, audio: {quality: 1.0}}" />
```

## Run the tests

This project has some basic tests to ensure that it works in all typo3 versions described in the composer.json.
These tests are run by bitbucket and defined in `bitbucket-pipelines.yml`.

To run them locally, there are some composer scripts provided in this project.
Just clone the project, run `composer install` and then `composer db:start`, wait a few seconds, then `composer test`.
You can also run `composer test -- --filter TestCase` to run specific text classes/methods/datasets.

Here is a list of available commands:

- `composer db:start` will start a database using a docker command.
  You don't have to use it if you have a database available but you'll need to define the `typo3Database*` variables.
- `composer db:stop` unsurprisingly stops the database again... and removes it.
- `composer test` will run all available tests. If your first run fails then you might want to run `cc`.
- `composer test:unit` will just run the unit tests.
- `composer test:functional` will just run the functional tests.
- `composer cc` will remove some temp files. If your functional test fail for no apparat reason try this.


// ***************************************
// the php-ffmpeg way, works with t3v11, php82, php-ffmpeg:1.1
// https://stackoverflow.com/questions/2043007/generate-preview-image-from-video-file
// https://github.com/PHP-FFMpeg/PHP-FFMpeg


## Missing features

- automatic poster generation, probably even multiple posters with the capability to select one, ani-gif as preview
- an interface to crop and cut videos (it's already possible, just not though the interface)
- multiple resolutions (adaptive streaming) with something like [HLS] or [MPEG-DASH]
  although i'm not sure about either [HLS royalties] nor [DASH royalties].
- optionally process files within the fileadmin to reduce project footprint
- iundd/a11y-me-videoprod, lochmueller/html5videoplayerprod compatibilität

[CloudConvert]: https://cloudconvert.com
[h264 level]: https://de.wikipedia.org/wiki/H.264#Level
[VP9 level]: https://www.webmproject.org/vp9/levels/#vp9-levels
[HLS]: https://developer.apple.com/streaming/
[MPEG-DASH]: https://en.wikipedia.org/wiki/Dynamic_Adaptive_Streaming_over_HTTP
[HLS royalties]: http://www.overdigital.com/2012/04/17/the-hidden-licensing-costs-of-hls-video-playback/
[DASH royalties]: https://www.streamingmedia.com/Articles/ReadArticle.aspx?ArticleID=114903
