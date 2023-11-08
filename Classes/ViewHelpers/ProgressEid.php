<?php

namespace Faeb\Videoprocessing\ViewHelpers;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

// use function GuzzleHttp\Psr7\build_query;
// use function GuzzleHttp\Psr7\stream_for;
use Faeb\Videoprocessing\Processing\VideoProcessingTask;
use Faeb\Videoprocessing\Processing\VideoProcessor;
use Faeb\Videoprocessing\Processing\VideoTaskRepository;
use TYPO3\CMS\Core\Error\Http\BadRequestException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// Insted with pagenum
// https://stackoverflow.com/questions/21139769/typo3-extbase-ajax-without-page-typenum

class ProgressEid
{
    const EID = 'tx_video_progress';

    /** @var ResponseFactory */
    private $responseFactory;

    /** @var RequestFactory */
    private $requestFactory;

    /** @var ClientInterface */
    private $client;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        RequestFactoryInterface $requestFactory,
        ClientInterface $client
    ) {
        $this->responseFactory = $responseFactory;
        $this->requestFactory = $requestFactory;
        $this->client = $client;
    }

    /*
     * https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/RequestLifeCycle/Middlewares.html
     */
    public function __invoke(
        ServerRequestInterface $request,
        // RequestHandlerInterface $handler
    ): ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        // \TYPO3\CMS\Core\Utility\DebugUtility::debug($queryParams);

        if (empty($queryParams)){
            throw new BadRequestException("no query params.");
        } else {
            $uids = $queryParams['uids'];
            if (empty($uids)) {
                throw new BadRequestException("At least one uid must be given.");
            }
        }

        /** @var VideoProcessingTask $highestTask */
        $highestTask = null;
        $videoTaskRepository = GeneralUtility::makeInstance(VideoTaskRepository::class);

        foreach ((array)$uids as $uid) {
            $task = $videoTaskRepository->findByUid($uid);
            // get the newest information

            if ($task) {
                VideoProcessor::getConverter()->update($task);
                if ($highestTask === null || $highestTask->getLastProgress() < $task->getLastProgress()) {
                    $highestTask = $task;
                }
            }

        }

        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'application/json; charset=utf-8');

        if ($highestTask) {
            $response->getBody()->write(
                json_encode(self::parameters($highestTask), JSON_UNESCAPED_SLASHES)
            );
        } else {
            $response->getBody()->write(
                "{\"status\": \"missing\"}"
            );
        }


        return $response;

    }

    public static function parameters(VideoProcessingTask $task)
    {
        return [
            'progress' => round($task->getLastProgress(), 5),
            'remaining' => round($task->getEstimatedRemainingTime() * 1000),
            // TODO don't transfer an exact timestamp as the client may have a wrong clock
            'lastUpdate' => $task->getLastUpdate() * 1000,
            'status' => $task->getStatus(),
            'uid' => $task->getUid(),
            'progressSteps' => $task->getProgressSteps(),
            'processingDuration' => $task->getProcessingDuration()



        ];
    }

    // public static function getUrl(int ...$uids)


    public static function getUrl(int ...$uids)
    {
        $new_uids = [];
        foreach ($uids as $uid) {
            $new_uids[] = $uid;
            break;
        }

        return rtrim(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'), '/')
            . '/index.php?'
            // working?
            // https://t3v9.kukurtihar.com/index.php?eID=tx_video_progress&uids=20
            // TODO https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/ExtensionArchitecture/Extbase/Reference/UriBuilder.html
            // . build_query(['eID' => self::EID, 'uids' => $uids])
            . http_build_query( ['eID' => self::EID, 'uids' => $new_uids ] )
            // . $query
            // . 'eID=tx_video_progress&uids=123'

            ;
    }

}
