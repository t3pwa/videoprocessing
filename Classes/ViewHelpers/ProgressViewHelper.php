<?php

namespace Faeb\Videoprocessing\ViewHelpers;

use Faeb\Videoprocessing\Processing\VideoProcessingTask;
use Faeb\Videoprocessing\Processing\VideoTaskRepository;

use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class ProgressViewHelper extends AbstractViewHelper
{
    const POLLING_INTERVAL = 5;
    const MAX_PREDICTED_PROGRESS = 20;
    private static $counter = 0;

    private static $processCounter = 0;

    protected $escapeChildren = false;
    protected $escapeOutput = false;

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument(
            'subject',
            'mixed',
            "one or multiple task uid's (or proccessed files).", true);
    }

    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    )
    {
        return static::renderHtml(
            $arguments['subject']
        );
    }

    /**
     * @param mixed $argument A task id or an array of task ids, tasks or processed files.
     *
     * @return string
     */
    public static function renderHtml($argument)
    {
        // $content = 'ProgressViewhelper::renderHtml()';
//        \TYPO3\CMS\Core\Utility\DebugUtility::debug($argument);
        $content = '';

        if ($argument instanceof \Iterator) {
            $argument = iterator_to_array($argument);
        }

        if (!is_array($argument)) {
            $argument = $argument !== null ? [$argument] : [];
        }

        if (empty($argument)) {
            return '';
        }

        $uids = [];
        foreach ($argument as $item) {

            // var_dump($item);

            if (is_numeric($item)) {
                $uids[] = intval($item);
                continue;
            }

            if ($item instanceof ProcessedFile) {
                $item = $item->getTask();
            }

            if ($item instanceof VideoProcessingTask) {

                if (!$item->getUid()) {
                    $item = GeneralUtility::makeInstance(VideoTaskRepository::class)->findByTask($item);
                }


                // var_dump(gettype($item), $item);

                if (gettype($item) == NULL) {
                    // var_dump($item->getUid());
                    throw new \RuntimeException("The given VideoProcessingTask has no item.");
                }

                if ($item == NULL) {
                    return false;
                    // var_dump($item->getUid());
                    // throw new \RuntimeException("The given VideoProcessingTask has no item.");
                }

                /*
                                if (gettype($item) != NULL) {
                                    // var_dump($item->getUid());
                                    throw new \RuntimeException("The given VideoProcessingTask has no item.");
                                }
                */




                if ($item->getUid()) {
                    $uids[] = $item->getUid();
                    continue;
                } else {
                    throw new \RuntimeException("The given VideoProcessingTask has no id. You must start the process first.");
                }
            }
            $type = is_object($item) ? get_class($item) : gettype($item);
            throw new \RuntimeException("Got unknown $type as a task identifier.");
        }
/*
        $content .= '<span>
            $uids:&nbsp;'. implode(', ', $uids)
            . '</span>';
*/

//        $id = 'tx_videoprocessing_progress_' . self::$counter++;
        $class = 'tx_videoprocessing_progress';

        /* not that way, old way works
        $content .= '
        {namespace h=Helhum\TyposcriptRendering\ViewHelpers}
        <script>
        var getParticipationsUri = \'<h:uri.ajaxAction controller="Participation" action="listByCompetition" arguments="{competition:competition}" />\';
        </script>
        ';
        */

        // $content .= var_dump ( "eID attributes", $attributes);
        // $content .= implode(' ', $attributes);

        // $item->getSourceFile();
        // var_dump($item->getSourceFile());

/*
        $videoAttributes = [
            'id="' . $id . '"',
            'data-update-url="' . htmlspecialchars(ProgressEid::getUrl(...$uids)) . '"',
            'data-uids="' . implode('-', $uids) . '"',
        ];
*/
/*
 *
 * only in frontend ?
 *
        $videoContent = sprintf(
            '<video %s>%s</video>',
            implode(' ', $videoAttributes),
            '<source src="'. $item->getSourceFile()->getPublicUrl() . '" />'
        );
*/



        $progressHtml = "<!-- multiple progress bars -->";

        foreach ($uids as &$uid) {

            $id = 'tx_videoprocessing_progress_' . self::$counter++;

            $progressAttributes = [
//                'id="' . $id . '"',
//                'class="' . $class . '"',
//                'data-uid="' .  $uid . '"',
                'data-num=' . self::$processCounter++ ,
//                'data-update-url="' . htmlspecialchars(ProgressEid::getUrl($uid)) . '"',
            ];

            $progressBarAttributes = [
                'id="' . $id . '"',
                'class="' . $class . '"',

                'data-uid="' .  $uid . '"',
                'data-uids="' . implode('-', $uids) . '"',

//                'data-num=' . self::$processCounter++ ,
                'data-update-url="' . htmlspecialchars(ProgressEid::getUrl($uid)) . '"',
            ];



            $progressHtml .= sprintf('
             <div %s class="progress">
                <div %s class="progress-bar" role="progressbar">%s</div>
             </div>
            ',
                // htmlspecialchars(ProgressEid::getUrl(...$uids)),

                implode(
                    ' ',
                    $progressAttributes
                ),


                implode(
                    ' ',
                    $progressBarAttributes
                ),

                '<a href="' . htmlspecialchars(ProgressEid::getUrl($uid)) . '">...</a>'
            );

        }



        $content .= $progressHtml;
//        $content .= $videoContent;
        $content .= '<script>' . self::renderJavaScript($id) . '</script>';

        return $content;
    }

    private static function renderJavaScript(string $id)
    {
        $jsonId = json_encode($id, JSON_UNESCAPED_SLASHES);
        $pollingInterval = json_encode(self::POLLING_INTERVAL * 1000, JSON_UNESCAPED_SLASHES);
        $maxPredictedProgress = json_encode(self::MAX_PREDICTED_PROGRESS * 1000, JSON_UNESCAPED_SLASHES);


//        var_dump($pollingInterval); // 15 * 1000
//        var_dump($maxPredictedProgress); // 20 * 1000


        $script = <<<JavaScript
var renderProgress = [];
(renderProgress[$jsonId] = function (jsonId = $jsonId) {
    
    this.jsonId = jsonId;
    console.log("this.jsonId, jsonId:", this.jsonId, jsonId);
    
    console.log(">>>>>000 jsonId", jsonId, $jsonId, this.jsonId);
    
    this.element = document.getElementById(this.jsonId),
    this.latestProgress = 0.0,
    this.remaining = 0,
    this.lastUpdate = 0,
    this.updateProperties = function (o) {
        
        this.o = o
        console.log("update props:", this.jsonId, $jsonId, this.o);
    
        console.log("11111", $jsonId, this.jsonId, jsonId, o, this.o);
        
        this.uid = String(o.uid);
        console.log("!!! this.uid:", this.uid, "jsonId:", jsonId, $jsonId, this.jsonId);
        
        console.log("updateProperties", jsonId, $jsonId, this.jsonId)
    
        this.latestProgress = Number(o.progress);
        // console.log("latestProgress", this.uid, this.latestProgress, jsonId);
        
        this.remainingTime = Number(o.remaining) || Infinity;
        // console.log("this.remaining time" , this.remainingTime, jsonId);
        
        this.lastUpdate = Number(o.lastUpdate, jsonId);
        this.lastStatus = String(o.status, jsonId);
        
        this.processingDuration = String(o.processingDuration);
        // console.log("processingDuration ", jsonId, this.uid, this.processingDuration);
        
    },
    this.lastContent = this.element.textContent,
    this.updateTimeout = 0,
    this.requestProperties = function (callback, jsonId) {
        console.log("requestProperties", jsonId)
        clearTimeout(this.updateTimeout);
        xhr = new XMLHttpRequest();
        xhr.onload = function (jsonId) {
            
            this.response = xhr.responseText;
            console.log("this.response", jsonId, this.response);
            
            this.updateProperties( JSON.parse( this.response ) );
            
            this.updateTimeout = setTimeout(
                this.requestProperties, 
                $pollingInterval
                );
                
            callback && callback();
        };
        
        console.log("updateUrl", this.element.dataset.updateUrl);
        
        xhr.open('GET', this.element.dataset.updateUrl, true);
        xhr.send();
    },
    render = function () {
        // this.uid = uid;
        // check if the target node is still within the document and stop everything if not

        /*
        if (document.getElementById($jsonId) !== this.element) {
            clearTimeout(updateTimeout);
            console.log("not in focus?")
            return;
        }
        */
    
        // calculate the progress until it should be finished
        // console.log("maxPredictedProgress "+uid+"", $maxPredictedProgress);
        
        this.progress = Math.min(1.0, Math.min($maxPredictedProgress, Date.now() - lastUpdate) / this.remainingTime),
            newContent = ((latestProgress + (1.0 - latestProgress) * progress) * 100).toFixed(1) + '%';
            
        console.log("render: this.progress " + this.uid + ": ", this.progress);                
        
        this.newContent = ((this.latestProgress + (1.0 - this.latestProgress) * this.progress) * 100).toFixed(1) + '%';
        this.progressInt = ((this.latestProgress + (1.0 - this.latestProgress) * this.progress) * 100).toFixed(1);
        
        console.log(this.uid, this.progress, this.progressInt, this.newContent, this.processingDuration);
        
//            this.element.style.width = newContent;
//            this.element.style.background = 'hsl('+progressInt+' 100% 50%)'

        if (this.lastContent !== this.newContent) {
            this.element.dataset.progress = this.newContent;
            this.element.dataset.uid = this.uid;
            this.element.style.width = this.newContent
            this.element.textContent = this.newContent;
            this.element.style.background = 'hsl('+progressInt+' 100% 50%)'
            this.lastContent = this.newContent;
        } else {
            console.log("no change")
        }

        if (this.progressInt < 0.01) {
            this.element.textContent = '['+uid+']: '+ this.newContent;
        }

        if (this.progress < 1.0) {
            
            this.milliseconds = this.remainingTime / (1.0 - this.latestProgress) / 1000;
            
            // console.log("this ms", this.milliseconds);
            // console.log("set timeout while still in progress", Math.max(2000, Math.min(1000, this.milliseconds)) )
            
            setTimeout(
                this.render()
                // Math.max(4000, Math.min(3000, this.milliseconds))
            ).bind(Math.max(4000, Math.min(3000, this.milliseconds)));
            
        } else {
            console.log("finished?");
            // clearTimeout(updateTimeout);
            
            if (document.hasFocus() && this.lastUpdate + 5000 > Date.now()) {
                console.log("setTimeout");
                setTimeout(function () {
                    if (!window.video_is_reloading) {
                        location.reload();
                        window.video_is_reloading = true;
                    }
                // }, 5000);
                }.bind(2500));
            }
            
            
        }
            
    } // render()
    ;
    
    this.requestProperties( render() );
})();

if ($jsonId == "tx_videoprocessing_progress_0") {
    renderProgress[$jsonId]();
}

JavaScript;

        // minify a bit
        // TODO dont minify on development environment for debugging
        // return preg_replace('#\s*\n\s*|\s*//[^\n]*\s*|\s*([,;!=()*/\n+-])\s*#', '\\1', $script);

//        return $script;
    }
}
