<?php

declare(strict_types=1);

namespace Faeb\Videoprocessing\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Faeb\Videoprocessing\Processing\VideoTaskRepository;
use Faeb\Videoprocessing\Processing\VideoProcessor;
use Faeb\Videoprocessing\Processing\VideoProcessingTask;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
// use TYPO3\CMS\Core\Utility\DebugUtility;

class Process extends Command
{

    private VideoTaskRepository $VideoTaskRepository;

    private VideoProcessor $videoProcessor;

    public function __construct(
        VideoTaskRepository $VideoTaskRepository,
        VideoProcessor $VideoProcessor
    ) {
        $this->VideoTaskRepository = $VideoTaskRepository;
        $this->videoProcessor = $VideoProcessor;
        parent::__construct();
        // $this->storedTasks = $this->VideoTaskRepository->findByStatus(VideoProcessingTask::STATUS_NEW);
    }

    protected function configure(): void
    {
        $this->setDescription('desc process videos');
    }

    protected function execute(InputInterface $input, OutputInterface $output, float $timeout = INF): int
    {
        $output->writeln("Video Process Command execute ...");

        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->notice('Video Process Command');


        /** @var TYPE_NAME $storedTasks */

        $storedTasks = $this->VideoTaskRepository->findByStatus(VideoProcessingTask::STATUS_NEW);
        //$this->output("Search for new tasks... ");

        $count = count($storedTasks);
        if ($count <= 0) {
        //    $this->outputLine("no task found.");
            $output->writeln("no tasks found");
            // return;
            return Command::SUCCESS;
        }

        //$this->outputLine("found <info>%s</info> tasks:", [$count]);
        // $output->writeln("found ... >>>", $count, "<<<");
        $output->writeln($count);

        foreach ($storedTasks as $storedTask) {

            $output->writeln("doProcessTask");
            $this->videoProcessor->doProcessTask($storedTask);
            $output->writeln("after doProcessTask");

            $timePassed = time() - $_SERVER['REQUEST_TIME'];

            if ($timePassed > $timeout * 3600) {
                // $this->outputLine("Abort because of the timeout ($timeout minutes).");
                $output->writeln("timeout");

                return Command::FAILURE;
                // better continue then break?
                // break;
            }
            // $this->output->progressAdvance();
            // not working like this
            //$output->progressAdvance();

        }
        // $this->output->progressFinish();
        // not working like this
        //$output->progressFinish();
        $output->writeln("doProcessTask finished");
        $output->writeln("Video Process Command execute return ");
        return Command::SUCCESS;
    }
}