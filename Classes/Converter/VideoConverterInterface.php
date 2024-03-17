<?php

namespace Faeb\Videoprocessing\Converter;

use Faeb\Videoprocessing\Exception\ConversionException;
use Faeb\Videoprocessing\Processing\VideoProcessingTask;

interface VideoConverterInterface
{
    /**
     * This method will start the conversion process using the provided options.
     *
     * It must not block the process. If the process can't run async, than it must not run here.
     *
     * @param VideoProcessingTask $task
     * @throws ConversionException if something went wrong while starting the process. The task will be marked as failed.
     */
    public function start(VideoProcessingTask $task): void;

    /**
     * This method is called new tasks to update their status and potentially process them in a blocking fashion.
     *
     * If you process files in a local process do that here.
     * You can repeatably persist the task object with a new status update for feedback.
     *
     * If you use an api or external service to process the file you can ask for a status update here.
     * This method will be called every time the process command is executed until the task is finished or failed.
     *
     * @param VideoProcessingTask $task
     * @throws ConversionException
     */
    public function process(VideoProcessingTask $task): void;

    /**
     * This method is called for the live progress display and should therefore add progress information.
     *
     * This method must not be long running as it is executed though the frontend.
     * This method may start web requests if necessary as long as they don't take too long.
     *
     * Implementing this method is optional and is only necessary if you can update progress information.
     * You can also implement completely different approaches to update the status,
     * this method here is just useful if you need some form of polling.
     *
     * If you implement it you must make sure that your implementation can handle concurrency.
     * There might be multiple people watching the process and all of them poll the progress.
     *
     * @param VideoProcessingTask $task
     */
    public function update(VideoProcessingTask $task): void;
}
