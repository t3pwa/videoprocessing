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
    const POLLING_INTERVAL = 15;
    const MAX_PREDICTED_PROGRESS = 20;
    private static $counter = 0;

    protected $escapeChildren = false;
    protected $escapeOutput = false;

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('subject', 'mixed', "one or multiple task id's or proccessed files.", true);
    }

    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        return static::renderHtml($arguments['subject']);
    }

    /**
     * @param mixed $argument A task id or an array of task ids, tasks or processed files.
     *
     * @return string
     */
    public static function renderHtml($argument)
    {
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

        $id = 'tx_videoprocessing_progress_' . self::$counter++;
        $attributes = [
            'id="' . $id . '"',
            'data-update-url="' . htmlspecialchars(ProgressEid::getUrl(...$uids)) . '"',
        ];

        $content = '<code ' . implode(' ', $attributes) . '></code>';
        $content .= '<script>' . self::renderJavaScript($id) . '</script>';

        return $content;
    }

    private static function renderJavaScript(string $id)
    {
        $jsonId = json_encode($id, JSON_UNESCAPED_SLASHES);
        $pollingInterval = json_encode(self::POLLING_INTERVAL * 1000, JSON_UNESCAPED_SLASHES);
        $maxPredictedProgress = json_encode(self::MAX_PREDICTED_PROGRESS * 1000, JSON_UNESCAPED_SLASHES);
        $script = <<<JavaScript
(function () {
    var element = document.getElementById($jsonId),
        latestProgress = 0.0,
        remaining = 0,
        lastUpdate = 0,
        updateProperties = function (o) {
            latestProgress = Number(o.progress);
            remainingTime = Number(o.remaining) || Infinity;
            lastUpdate = Number(o.lastUpdate);
        },
        lastContent = element.textContent,
        updateTimeout = 0,
        requestProperties = function (callback) {
            clearTimeout(updateTimeout);
            var xhr = new XMLHttpRequest();
            xhr.onload = function () {
                updateProperties(JSON.parse(xhr.responseText));
                updateTimeout = setTimeout(requestProperties, $pollingInterval);
                callback && callback();
            };
            xhr.open('GET', element.dataset.updateUrl, true);
            xhr.send();
        },
        render = function () {
            // check if the target node is still within the document and stop everything if not
            if (document.getElementById($jsonId) !== element) {
                clearTimeout(updateTimeout);
                return;
            }
        
            // calculate the progress until it should be finished
            var progress = Math.min(1.0, Math.min($maxPredictedProgress, Date.now() - lastUpdate) / remainingTime),
                newContent = ((latestProgress + (1.0 - latestProgress) * progress) * 100).toFixed(1) + '%';
            if (lastContent !== newContent) {
                element.textContent = newContent;
                lastContent = newContent;
            }
            
            if (progress < 1.0) {
                var milliseconds = remainingTime / (1.0 - latestProgress) / 1000;
                setTimeout(render, Math.max(100, Math.min(1000, milliseconds)));
            } else {
                clearTimeout(updateTimeout);
                if (document.hasFocus() && lastUpdate + 20000 > Date.now()) {
                    setTimeout(function () {
                        if (!window.video_is_reloading) {
                            location.reload();
                            window.video_is_reloading = true;
                        }
                    }, 5000);
                }
            }
        }
    ;
    requestProperties(render);
})();
JavaScript;

        // minify a bit
        return preg_replace('#\s*\n\s*|\s*//[^\n]*\s*|\s*([,;!=()*/\n+-])\s*#', '\\1', $script);
    }
}
