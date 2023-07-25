<?php

namespace Faeb\Videoprocessing\Converter;


use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\CommandUtility;

class LocalCommandRunner implements SingletonInterface
{
    /**
     * This is the same as the command utility function.
     * However, it isn't static and therefore testable.
     *
     * @param string $cmd The command that should be executed. eg: "convert"
     * @param string $handler Handler (executor) for the command. eg: "perl"
     * @param string $handlerOpt Options for the handler, like '-w' for "perl"
     *
     * @return mixed Returns command string, or FALSE if cmd is not found, or -1 if the handler is not found
     * @see \TYPO3\CMS\Core\Utility\CommandUtility::getCommand
     */
    public function getCommand($cmd, $handler = '', $handlerOpt = '')
    {
        return CommandUtility::getCommand($cmd, $handler, $handlerOpt);
    }

    /**
     * This runs the given command.
     * You can use the returned iterator to parse the output while the command is running.
     * The Generator return value will be the status code.
     *
     * @param string $command
     *
     * @return \Generator
     */
    public function run(string $command): \Generator
    {
        $process = proc_open("$command 2>&1", [1 => ['pipe', 'w']], $pipes);
        stream_set_blocking($pipes[1], false);

        try {
            do {
                usleep(20 * 1000);
                while ($string = fgets($pipes[1])) {
                    yield $string;
                }
                $status = proc_get_status($process);
            } while ($status['running']);
        } finally {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
            return $status['exitcode'] ?? -1;
        }
    }
}
