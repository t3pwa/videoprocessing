<?php


// namespace Hn\Video\ViewHelpers;
namespace Faeb\Videoprocessing\Controller;


use function GuzzleHttp\Psr7\build_query;
use function GuzzleHttp\Psr7\stream_for;
use Hn\Video\Processing\VideoProcessingTask;
use Hn\Video\Processing\VideoProcessor;
use Hn\Video\Processing\VideoTaskRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Error\Http\BadRequestException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// class ProgressEid
class ProgressController
{
    const EID = 'tx_video_progress';

    public static function render(ServerRequestInterface $request, ResponseInterface $response)
    {
        $queryParams = $request->getQueryParams();

        $uids = $queryParams['uids'];
        if (empty($uids)) {
            throw new BadRequestException("At least one uid must be given.");
        }

        /** @var VideoProcessingTask $highestTask */
        $highestTask = null;
        $videoTaskRepository = GeneralUtility::makeInstance(VideoTaskRepository::class);


        foreach ((array)$uids as $uid) {
            $task = $videoTaskRepository->findByUid($uid);

            // get the newest information
            VideoProcessor::getConverter()->update($task);
            if ($highestTask === null || $highestTask->getLastProgress() < $task->getLastProgress()) {
                $highestTask = $task;
            }
        }

        $content = json_encode(self::parameters($highestTask), JSON_UNESCAPED_SLASHES);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withBody(stream_for($content));
    }

    public static function parameters(VideoProcessingTask $task)
    {
        return [
            'progress' => round($task->getLastProgress(), 5),
            'remaining' => round($task->getEstimatedRemainingTime() * 1000),
            // TODO don't transfer an exact timestamp as the client may have a wrong clock
            'lastUpdate' => $task->getLastUpdate() * 1000,
            'uid' => $task->getUid(),
            'progressSteps' => $task->getProgressSteps(),
            'processingDuration' => $task->getProcessingDuration()



        ];
    }

    public static function getUrl(int ...$uids)
    {
        return rtrim(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'), '/')
            . '/index.php?' . build_query(['eID' => self::EID, 'uids' => $uids]);
    }
}

