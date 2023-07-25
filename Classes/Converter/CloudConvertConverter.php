<?php

namespace Faeb\Videoprocessing\Converter;


use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use GuzzleHttp\Client;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\UriResolver;

use Faeb\Videoprocessing\Exception\ConversionException;
use Faeb\Videoprocessing\FormatRepository;
use Faeb\Videoprocessing\Processing\VideoProcessingEid;
use Faeb\Videoprocessing\Processing\VideoProcessingTask;
use Faeb\Videoprocessing\Processing\VideoTaskRepository;
use Faeb\Videoprocessing\ViewHelpers\ProgressViewHelper;

use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Locking;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function GuzzleHttp\Psr7\try_fopen;
use function GuzzleHttp\Psr7\uri_for;

class CloudConvertConverter extends AbstractVideoConverter
{
    const DB_TABLE = 'tx_video_cloudconvert_process';

    const LOCKING_STRATEGY = LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE
    | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK;

    const MODE_INFO = 'info';
    const MODE_CONVERT = 'convert';

    // define the progress ranges for the different stages of the conversion
    const PROGRESS_RANGES = [
        self::MODE_INFO => [
            'input' => [0.0, 0.1],
            'wait' => [0.1, 0.1],
            'convert' => [0.1, 0.1],
            'output' => [0.1, 0.1],
            'finished' => [0.1, 0.1],
        ],
        self::MODE_CONVERT => [
            'input' => [0.1, 0.2],
            'wait' => [0.2, 0.2],
            'convert' => [0.2, 0.8],
            'output' => [0.9, 1.0],
            'finished' => [1.0, 1.0],
        ],
    ];

    /**
     * @var Client
     */
    private $guzzle;

    /**
     * @var Locking\LockFactory
     */
    private $lockFactory;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * This decides if this typo3 instance is publicly available.
     *
     * if defined
     *  - files are downloaded by cloudconvert so no blocking php process is required
     *  - callback urls can be used to notify about finished tasks
     * if null
     *  - files will be uploaded by php which blocks processes and therefor won't be done during requests.
     *  - polling has to be used to figure out if the process is done
     *
     * @var UriInterface|null
     */
    private $baseUrl = null;

    /**
     * @param string $apiKey
     * @param string|null $baseUrl
     */
    public function __construct(string $apiKey)
    {
        $this->guzzle = GeneralUtility::makeInstance(Client::class, [
            'base_uri' => 'https://api.cloudconvert.com/',
            'timeout' => 5.0,
            'headers' => [
                'User-Agent' => 'video typo3 extension',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);

        $this->lockFactory = GeneralUtility::makeInstance(LockFactory::class);
        $this->db = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::DB_TABLE);
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        // if we are in a publicly accessible frontend environment than define the base url
        // this allows the implementation to use the "download" way of delivering the video file
        $ip = gethostbyname(GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY'));
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $this->baseUrl = uri_for(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'));
        }
    }

    public function isPublic(): bool
    {
        return $this->baseUrl !== null;
    }

    protected function request(string $method, string $uri, array $options = []): PromiseInterface
    {
        $context = ['uri' => $uri, 'method' => $method] + $options;
        $this->logger->debug('start request', $context);
        return $this->guzzle->requestAsync($method, $uri, $options)
            ->then(function (Response $response) use ($context) {
                $context += ['body' => $response->getBody()->read(1024)];
                $this->logger->debug('decode response', $context);

                $body = json_decode((string)$response->getBody(), true);
                if (json_last_error()) {
                    $this->logger->critical('decode error', $context);
                    throw new ConversionException(json_last_error_msg());
                }

                return $body;
            });
    }

    public function start(VideoProcessingTask $task): void
    {
        $context = [
            'file' => $task->getSourceFile()->getUid(),
            'configuration' => $task->getConfiguration(),
        ];

        // if the instance is public than the process can start immediately.
        if ($this->isPublic()) {
            $this->logger->notice("got a start signal in public mode.", $context);
            $this->process($task);
        } else {
            $this->logger->notice("got a start signal in private mode.", $context);
        }
    }

    public function update(VideoProcessingTask $task): void
    {
        // throttle the update process
        $time = $_SERVER['REQUEST_TIME'] ?? time();
        if ($task->getLastUpdate() + ProgressViewHelper::POLLING_INTERVAL > $time) {
            return;
        }

        // there is nothing that blocks too long
        // and typo3 excepts a timeout of 240 seconds which should be enough to download even bigger videos
        // if it isn't than i need to add a flag to prevent the download from running within the frontend request
        $this->process($task);
    }

    public function process(VideoProcessingTask $task): void
    {
        $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);
        $definition = $formatRepository->findFormatDefinition($task->getConfiguration());
        if ($definition === null) {
            throw new ConversionException("Can't find format for: " . print_r($task->getConfiguration(), true));
        }

        $info = $this->getInfo($task);
        $this->logger->debug('polled for info', ['result' => $info]);
        if ($info === null) {
            return;
        }

        $command = $formatRepository->buildParameterString('{INPUTFILE}', '{OUTPUTFILE}', $task->getConfiguration(), $info['streams']);
        // remove possible escaping from the inputfile/outputfile segments since they are actually placeholders
        $command = strtr($command, [
            escapeshellarg('{INPUTFILE}') => '{INPUTFILE}',
            escapeshellarg('{OUTPUTFILE}') => '{OUTPUTFILE}',
        ]);

        $result = $this->pollProcess($task, self::MODE_CONVERT, ["command" => $command]);
        $this->logger->debug('polled for convert', ['result' => $result, 'command' => $command]);
        if ($result === null) {
            return;
        }

        if ($result['step'] !== 'finished' || !isset($result['output']['url'])) {
            return;
        }

        // prevent 2 processes from downloading the file
        if (!$lock = $this->acquireLock($result['output']['url'])) {
            return;
        }

        // actually download the file
        $tempFilename = GeneralUtility::tempnam('video');
        try {
            $this->guzzle->get($result['output']['url'], [
                'sink' => $tempFilename,
                'timeout' => $task->getSourceFile()->getSize() / 1024 / 1024,
            ]);

            $this->finishTask($task, $tempFilename, $info['streams']);
        } finally {
            GeneralUtility::unlink_tempfile($tempFilename);
            $lock->release();
        }
    }

    public function getInfo(VideoProcessingTask $task): ?array
    {
        $result = $this->pollProcess($task, self::MODE_INFO);
        if ($result['step'] !== 'finished' || !isset($result['info'])) {
            return null;
        }

        return $result['info'];
    }

    protected function pollProcess(VideoProcessingTask $task, string $mode, array $converteroptions = []): ?array
    {
        $context = [
            'file' => $task->getSourceFile()->getUid(),
            'configuration' => $task->getConfiguration(),
        ];

        $serializedOptions = serialize($converteroptions);
        $serializedOptionsLength = strlen($serializedOptions);
        if ($serializedOptionsLength > 767) {
            $msg = "The options passed to create this job were $serializedOptionsLength bytes long.";
            $msg .= " There is a limit of 767 bytes for the mysql unique key to work. Sorry.";
            throw new \RuntimeException($msg);
        }

        $statement = $this->db->select(
            ['uid', 'status', 'failed'],
            self::DB_TABLE, [
            'file' => $task->getSourceFile()->getUid(),
            'mode' => $mode,
            'options' => $serializedOptions,
        ]);

        // TODO make sure not to spam the api with tons of queries... maybe limit to one every 5 seconds

        $info = $statement->fetch() ?: [];
        $this->logger->debug('request process info from db', ['result' => $info] + $context);
        if (isset($info['status'])) {
            $info['status'] = unserialize($info['status']);
        }

        if ($info['failed'] ?? false) {
            throw new ConversionException("Process error: " . json_encode($info), 1554038915);
        }

        // do nothing if this task is already done
        if (isset($info['status']['step']) && $info['status']['step'] === 'finished') {
            return $info['status'];
        }

        // do nothing it the task was already checked within this second
        // this could potentially create a problems when the callback eid is called very often.
        if (isset($info['tstamp']) && $info['tstamp'] >= $_SERVER['REQUEST_TIME']) {
            return $info['status'];
        }

        // TODO check expired

        $identifier = $task->getSourceFile()->getSha1() . $mode . sha1($serializedOptions);
        if (!$lock = $this->acquireLock($identifier)) {
            return null;
        }

        // TODO handle edge case in which the lock could be aquired just after the last process finished

        $processContext = ['mode' => $mode, 'options' => $converteroptions] + $context;
        if (isset($info['status'])) {
            $processContext['url'] = $info['status']['url'];
            $this->logger->debug('update cloud convert process', $processContext);
            $promise = $this->updateCloudConvertProcess($info['status']['url']);
        } else {
            $this->logger->debug('create cloud convert process', $processContext);
            $promise = $this->createCloudConvertProcess($task, $mode, $converteroptions);
        }

        $promise->then(function (array $response) use ($task, $mode) {
            // https://cloudconvert.com/api/conversions#status
            // TODO test this step check
            if (in_array($response['step'], ['input', 'wait', 'convert', 'output', 'finished'])) {
                $this->updateProgressInformation($task, $mode, $response);
                return $response;
            }

            if ($response['step'] === 'error') {
                throw new ConversionException("Conversion failed. Message: {$response['message']}");
            }

            throw new ConversionException("Unknown step: {$response['step']}. Message: {$response['message']}");
        });

        // save the result from the promise
        $promise = $promise->then(
            function (array $response) use ($task, $mode, $serializedOptions, $info, $lock) {
                $values = [
                    'file' => $task->getSourceFile()->getUid(),
                    'mode' => $mode,
                    'options' => $serializedOptions,
                    'status' => serialize($response),
                    'failed' => 0,
                    'tstamp' => $_SERVER['REQUEST_TIME'],
                ];
                if (isset($info['uid'])) {
                    $this->db->update(self::DB_TABLE, $values, ['uid' => $info['uid']]);
                } else {
                    $this->db->insert(self::DB_TABLE, $values + ['crdate' => $_SERVER['REQUEST_TIME']]);
                }
                $lock->release();
                return $response;
            },
            function (\Throwable $error) use ($task, $mode, $serializedOptions, $info, $lock, $context) {
                if (
                    $error instanceof ServerException
                    && $error->hasResponse()
                    && $error->getResponse()->getStatusCode() === 503
                ) {
                    $this->logger->notice('got 503, try again later', $context);
                    // just ignore that for now
                    return null;
                }

                $status = ['message' => $error->getMessage(), 'step' => 'exception'];

                if ($error instanceof RequestException && $error->hasResponse()) {
                    $status['body'] = $error->getResponse()->getBody()->read(1024);
                }

                $values = [
                    'file' => $task->getSourceFile()->getUid(),
                    'mode' => $mode,
                    'options' => $serializedOptions,
                    'status' => serialize($status),
                    'failed' => 1,
                    'tstamp' => $_SERVER['REQUEST_TIME'],
                ];
                if (isset($info['uid'])) {
                    $this->db->update(self::DB_TABLE, $values, ['uid' => $info['uid']]);
                } else {
                    $this->db->insert(self::DB_TABLE, $values + ['crdate' => $_SERVER['REQUEST_TIME']]);
                }
                $lock->release();
                return new RejectedPromise(new ConversionException("Communication Error", 1554565455, $error));
            }
        );

        // i originally intended for requests to run while the rest of the page is rendering
        // it turned out that guzzle does not work like that so here i am: waiting for the promises to resolve
        return $promise->wait();
    }

    protected function createCloudConvertProcess(VideoProcessingTask $task, string $mode, array $converteroptions = []): PromiseInterface
    {
        $loggingContext = [
            'file' => $task->getSourceFile()->getUid(),
            'configuration' => $task->getConfiguration(),
        ];

        $createOptions = [];
        $startOptions = [];

        $createOptions['inputformat'] = $task->getSourceFile()->getExtension();

        if ($mode === self::MODE_CONVERT) {
            $createOptions['outputformat'] = $task->getTargetFileExtension();
            $startOptions['outputformat'] = $task->getTargetFileExtension();
            $startOptions['converteroptions'] = $converteroptions;
        } else {
            $createOptions['mode'] = $mode;
            $startOptions['mode'] = $mode;
        }

        if ($this->isPublic()) {
            $startOptions += [
                'input' => 'download',
                // TODO ensure that this file has the domain prepended
                'filename' => $task->getSourceFile()->getName(),
                'file' => (string)UriResolver::resolve($this->baseUrl, uri_for($task->getSourceFile()->getPublicUrl())),
                'callback' => (string)UriResolver::resolve($this->baseUrl, uri_for(VideoProcessingEid::getUrl())),
            ];
        } else {
            $startOptions += [
                'input' => 'upload',
                'filename' => $task->getSourceFile()->getName(),
            ];
        }

        return $this->request('post', '/process', ['json' => $createOptions])
            ->then(function (array $response) use ($task, $mode, $startOptions, $loggingContext) {
                // TODO maxconcurrent? is there anything i should check there?

                $sizeInMb = ceil($task->getSourceFile()->getSize() / 1024 / 1024);
                if ($sizeInMb > $response['maxsize']) {
                    $msg = "File to big for cloud convert. Max size is {$response['maxsize']} MB.";
                    throw new ConversionException($msg);
                }

                return $this->request('post', $response['url'], ['json' => $startOptions]);
            })
            // if cloud convert gives us an upload url, than upload the file there
            ->then(function (array $response) use ($task, $loggingContext) {
                if (empty($response['upload']['url'])) {
                    $this->logger->debug('no upload url', $loggingContext);
                    return $response;
                }

                // upload the file if necessary

                $resource = try_fopen($task->getSourceFile()->getForLocalProcessing(false), 'rb');
                $uploadUrl = rtrim($response['upload']['url'], '/') . '/' . $task->getSourceFile()->getName();

                $uploadOptions = [
                    'body' => $resource,
                    'timeout' => fstat($resource)['size'] / 1024 / 1024 // expect at least 1 mb/s
                ];

                return $this->request('put', $uploadUrl, $uploadOptions)
                    ->then(function (array $uploadResponse) use ($task, $response) {
                        $expectedSize = $task->getSourceFile()->getSize();
                        $uploadedSize = $uploadResponse['size'];
                        if ($uploadedSize !== $expectedSize) {
                            $msg = "The uploaded filesize mismatches, expected $expectedSize but got $uploadedSize.";
                            throw new ConversionException($msg);
                        }

                        // return the last response since it contains the process status information
                        // the actual upload does not contain this information
                        return $response;
                    });
            });
    }

    protected function updateCloudConvertProcess(string $processUrl): PromiseInterface
    {
        return $this->request('get', $processUrl);
    }

    protected function updateProgressInformation(VideoProcessingTask $task, string $mode, array $response)
    {
        $range = self::PROGRESS_RANGES[$mode][$response['step']] ?? null;
        if (!$range) {
            return;
        }

        $progress = (floatval($response['percent']) ?: 0.0) / 100;
        $totalProgress = $range[0] + ($range[1] - $range[0]) * $progress;
        $task->addProgressStep($totalProgress);

        $videoTaskRepository = GeneralUtility::makeInstance(VideoTaskRepository::class);
        $videoTaskRepository->store($task);
    }

    private function acquireLock(string $identifier): ?LockingStrategyInterface
    {
        try {
            $lock = $this->lockFactory->createLocker($identifier, self::LOCKING_STRATEGY);
            if (!$lock->acquire(self::LOCKING_STRATEGY)) {
                $this->logger->debug('lock not acquired');
                return null;
            }

            return $lock;
        } catch (Exception $e) {
            // it seems that the noblock implementation is not really tested
            // passing noblock to acquire will not block on supported platforms
            // and will block on unsuported platforms while throwing this exception afterwards
            // i simply ignore this problem for now and don't do anything without a lock
            $this->logger->notice('ignored locking exception', ['exception' => $e]);
            return null;
        }
    }
}
