<?php

namespace label\Spider;

use label\RedisProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class QueueSpiderCommand
 * Daemon, used to listen queue and run jobs to run through pages.
 * This command can not be ran with cron - could be issues with multi-threads.
 */
class QueueSpiderCommand extends Command
{
    const OPTION_TRACK_RESPONSE = 'track-response';
    const OPTION_EMULATE = 'emulate';
    const OPTION_SHOW_DEBUG = 'show-debug';
    const OPTION_IGNORE_CERT = 'ignore-cert';
    const OPTION_THREADS = 'threads';

    /**
     * @var InputInterface
     */
    private static $input;

    /**
     * @var OutputInterface
     */
    private static $output;

    /**
     * Show and log messages
     * @param string $level
     * @param string $message
     */
    public function processMessage($level, $message)
    {
        if (
            ($level === 'report')
            || (
                ($level === 'debug')
                && self::$input->getOption(self::OPTION_SHOW_DEBUG)
            )
        ) {
            $milliseconds = intval(explode(' ', microtime())[0] * 1000);
            $record = date('Y-m-d H:i:s') . '.' . $milliseconds
                . '|p' . str_pad(getmypid(), 7, '0', STR_PAD_LEFT)
                . '|' . $message;
            self::$output->writeln($record);
            file_put_contents(TMP_DIR . '/spider_queue.txt', $record . PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * Configure the current command
     */
    protected function configure()
    {
        $this
            ->setName('queue:spider')
            ->setDescription('run spider over all urls in queue')
            ->addOption(
                self::OPTION_TRACK_RESPONSE,
                null,
                InputOption::VALUE_NONE,
                'track response of call'
            )
            ->addOption(
                self::OPTION_EMULATE,
                null,
                InputOption::VALUE_NONE,
                'emulate but do not actually call url and do not clear cache'
            )
            ->addOption(
                self::OPTION_SHOW_DEBUG,
                null,
                InputOption::VALUE_NONE,
                'show debug info'
            )
            ->addOption(
                self::OPTION_IGNORE_CERT,
                null,
                InputOption::VALUE_NONE,
                'ignore certificate on crawled host'
            )
            ->addOption(
                self::OPTION_THREADS,
                null,
                InputOption::VALUE_REQUIRED,
                'run several threads'
            );
    }/** @noinspection PhpMissingParentCallCommonInspection */

    /**
     * Run the current command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        self::$output = $output;
        self::$input = $input;

        if (!empty($input->getOption(self::OPTION_THREADS))) {
            self::runThreads($input->getOption(self::OPTION_THREADS));
        } else {
            self::runJob();
        }

        return 0;
    }

    private static function runJob()
    {
        SpiderJob::setTrackResponse(self::$input->getOption(self::OPTION_TRACK_RESPONSE));
        SpiderJob::setEmulate(self::$input->getOption(self::OPTION_EMULATE));
        SpiderJob::setCallbackMessage(['label\Spider\QueueSpiderCommand', 'processMessage']);
        SpiderJob::setIgnoreCertificate(self::$input->getOption(self::OPTION_IGNORE_CERT));

        \Resque::setBackend(REDIS_HOST, RedisProvider::getDatabaseIndex(RedisProvider::USAGE_QUEUE));
        $worker = new \Resque_Worker('spider');
        $worker->work();//infinite run
    }

    /**
     * Run separate threads
     * @param int $n count of threads
     */
    private function runThreads($n)
    {
        $command = 'php console.php queue:spider';
        if (!empty(self::$input->getOption(self::OPTION_TRACK_RESPONSE))) {
            $command .= ' --' . self::OPTION_TRACK_RESPONSE;
        }
        if (!empty(self::$input->getOption(self::OPTION_EMULATE))) {
            $command .= ' --' . self::OPTION_EMULATE;
        }
        if (!empty(self::$input->getOption(self::OPTION_SHOW_DEBUG))) {
            $command .= ' --' . self::OPTION_SHOW_DEBUG;
        }
        if (!empty(self::$input->getOption(self::OPTION_IGNORE_CERT))) {
            $command .= ' --' . self::OPTION_IGNORE_CERT;
        }

        $command .= ' > /dev/null &';
        
        exec('ps auxwww|grep "resque"|grep "spider"|grep -v grep|grep -v php-fpm| grep -v bash|grep -v "su -"', $res);
        $n = $n - count($res);
        if ($n > 0) {
            for ($i = 0; $i < $n; $i++) {
                exec($command);
            }
        }
    }
}