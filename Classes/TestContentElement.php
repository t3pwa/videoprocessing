<?php

namespace Faeb\Videoprocessing;

use Faeb\Videoprocessing\Processing\VideoProcessingTask;
use Faeb\Videoprocessing\Processing\VideoTaskRepository;
use Faeb\Videoprocessing\ViewHelpers\ProgressViewHelper;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Resource\FileCollector;

class TestContentElement
{
    /**
     * Reference to the parent (calling) cObject set from TypoScript
     * @var ContentObjectRenderer
     */
    protected $cObj;

    public function render(string $content, array $config)
    {
        $configStr = $this->cObj->stdWrapValue('configurations', $config);

        $configurations = [];
        $limit = 1;
        for ($i = 0; $i < $limit; ++$i) {
            $replace = function ($match) use ($i, &$limit) {
                $variants = GeneralUtility::trimExplode(',', $match[1]);
                $limit = max($limit, count($variants));
                return $variants[$i % count($variants)];
            };
            $parsedConfig = preg_replace_callback('#%([^%]+)%#', $replace, $configStr);
            $configurations[] = json_decode($parsedConfig, true);
            if (json_last_error()) {
                return json_last_error_msg() . ':' . $parsedConfig;
            }
        }

        $fileCollector = GeneralUtility::makeInstance(FileCollector::class);
        $fileCollector->addFilesFromRelation($this->cObj->getCurrentTable(), $config['field'] ?? 'media', $this->cObj->data);

        $content .= '<div id="carouselExampleSlidesOnly" class="carousel slide" data-bs-ride="carousel">';


        // $content .= count($configurations);

        if (count($configurations) > 1) {
            $content .= '<div 
                class="carousel-indicators"
                style="
                    position: absolute;
                    top: 50%;
                    // margin-top: 1em;
                    padding-top: 0.5em;
                    // border: 1px dotted greenyellow;
                    float: none;
                    height: 3em;
                    
                "
            >';
            for ($x = 0; $x < count($configurations); $x++) {
                $content .= '<button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="'.$x.'"';
                if ($x == 0) {
                    $content .= ' class="active" ';
                }
                $content .= 'aria-current="true" aria-label="Slide '.$x.'"></button>';
            };
            $content .= '</div>';
        }





    $content .= "<div class='carousel-inner' xmlns='http://www.w3.org/1999/html'>";

        $iterator = 0;

        /** @var FileInterface $file */
        foreach ($fileCollector->getFiles() as $file) {
            foreach ($configurations as $configuration) {
                // $content .= '<figure>';
                if ($file instanceof FileReference) {
                    $file = $file->getOriginalFile();
                }

                $configuration = FormatRepository::normalizeOptions($configuration);
                // $processedFile = $file->process('Video.CropScale', $configuration);
                $processedFile = $file->process('Video.CropScale', $configuration);
                $task = GeneralUtility::makeInstance(VideoTaskRepository::class)->findByFile($file->getUid(), $configuration);
                $json = json_encode($configuration, JSON_UNESCAPED_SLASHES);

                if ($processedFile->exists()) {

                    if ($processedFile->hasProperty('ffmpeg')) {
                        $command = $processedFile->getProperty('ffmpeg');
                        /* $content .= "
                            <div>
                                <code>ffmpeg -i {input-file} $command {output-file}</code>
                            </div>
                            ";
                        */
                    } else {
                        // $content .= "<h3>no ffmpg property?</h3>";
                    }

                    $size = GeneralUtility::formatSize($processedFile->getSize());


                    $renderer = GeneralUtility::makeInstance(RendererRegistry::class)->getRenderer($processedFile);
                    // $header = GeneralUtility::formatSize($processedFile->getT    );
                    // https://mdbootstrap.com/docs/standard/extended/video/

                    // $content .= '<div class="carousel-content">';
                    $content .= '<div class="carousel-item';
                    if ($iterator == 0) {
                        $content .= ' active';
                    }
                    $content .= '">';
                    //$content .= "<figure>";

                    $content .= $renderer->render($processedFile, 0, 0, $configuration);
                    // $content .= "</figure>\n";

                    $content .= '
                    <div class="carousel-caption d-none d-md-block w-100"
                        style="
                        //border: 1px solid red;
                        // padding-bottom: 0.5em; 
                        padding: 0.5em 1em ;
                        margin-bottom: 2em; 
                        margin-left: -5.5em; 
                        background: -webkit-gradient(linear, left top, left bottom, color-stop(15%,rgba(0,0,0,0)), color-stop(100%,rgba(0,0,0,1)));"
                    >';

                    // $content .= '<h5 class="fa-ice-cream">'. $iterator .' </h5>';
                    $content .= '<h2 
                        class="card-header headline-slidertext align-content-start"
                        style="
                            text-align: left;
                        //    border: 1px solid blue;
                            padding-left: 1em;
                            margin-top: -2em;
                            top: -2em; 
                            
                            "
                        >
                        <i class="fa-ice-cream"></i> 
                       '. $iterator .' Forrest <small>(' . $size . ')</small>
                       </h2>
                       ';


                    $content .= '
                    <hr />
                    ';


                    $content .= '<code>' . htmlspecialchars($json) . '</code>';


                    $content .= '
                        <span>
                        <strong>ext:</strong>' . $task->getTargetFileExtension() . '
                        <strong>status:</strong>' . $task->getStatus() . '
                        
                        </span>
                        
                        '. $task->getTargetFileExtension() .'
                        ';


                    if ($task instanceof VideoProcessingTask) {
                        $duration = intval($task->getProcessingDuration()) . ' s';
                        $content .= "
                            <span class='alert-info'>processing duration: $duration</span>
                        ";

                    }


                    $content .= '
                    </div> <!-- caption end -->
                    </div> <!-- carousle item en -->
                    <!-- *********************** -->';

                } else {
                    $content .= '<span>file is still processing</span>';
                    // $content .= ProgressViewHelper::renderHtml($processedFile);
                    // $content .= "</figure> <!-- figure end -->

                    $content .= '</div>';
                };

                $iterator = $iterator + 1;

            }
        }
        $content .= '</div> <!-- carousel inner end -->';
        if (count($configurations) > 1) {
            $content .= '
            <!-- Controls -->
            <div style="padding-top: 0em;">
              <button class="carousel-control-prev" type="button" data-mdb-target="#carouselVideoExample"
                data-mdb-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
              </button>
              <button class="carousel-control-next" type="button" data-mdb-target=""#carouselVideoExample"
                data-mdb-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
              </button>
              </div>
            ';
        }
        $content .= "</div> <!-- carousel in special video test media --> ";
        return $content;
    }

    public function setContentObjectRenderer(ContentObjectRenderer $cObj): void
    {
        $this->cObj = $cObj;
    }
}
