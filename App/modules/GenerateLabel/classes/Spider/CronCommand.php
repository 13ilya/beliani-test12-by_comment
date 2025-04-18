<?php

namespace label\Spider;

use label\DB;
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
class CronCommand extends Command
{
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
        $milliseconds = intval(explode(' ', microtime())[0] * 1000);
        $record = date('Y-m-d H:i:s') . '.' . $milliseconds
            . '|p' . str_pad(getmypid(), 7, '0', STR_PAD_LEFT)
            . '|' . $message;
        self::$output->writeln($record);
        file_put_contents(TMP_DIR . '/cron_spider.txt', $record . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Configure the current command
     */
    protected function configure()
    {
        $this
            ->setName('cron:spider')
            ->setDescription('run spider over all urls in queue')
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
        
        exec('ps auxwww|grep "cron:spider"|grep -v grep|grep -v php-fpm| grep -v bash|grep -v "su -"', $res);
        if (count($res) > 2) {
            self::$output->writeln('You can not run more threads');
            exit;
        }
        
        $db = DB::getInstance(DB::USAGE_WRITE);
        
        if (mt_rand(1, 100) <= 1) {
            $db->query('DELETE FROM `sa_shop_url` WHERE `date` < DATE_ADD(NOW(), INTERVAL -14 DAY)');
        }
        
        $time = time();
        
        \Resque::setBackend(REDIS_HOST, RedisProvider::getDatabaseIndex(RedisProvider::USAGE_QUEUE));
        $worker = new \Resque_Worker('spider');
        
        while (true) {
            $jobs = [];
            
            $counter = 0;
            while ($data = $worker->reserve()) {
                if ($data && isset($data->payload['args'])) {
                    foreach ($data->payload['args'] as $_jobs) {
                        foreach ($_jobs as $job) {
                            $md5 = md5(serialize($job));
                            $jobs[$md5] = $job;
                        }
                    }
                }
                
                if ($counter++ > 250)
                {
                    break;
                }
            }

            if ($jobs) {
                file_put_contents(TMP_DIR . '/cron_spider.txt', \Resque::size('spider') . "\n");
                
                $spider = new SpiderJob();
                $spider->setCallbackMessage(['label\Spider\CronCommand', 'processMessage']);
                //$spider->setCallbackMessage('processMessage');

                try {
                    $spider->args = $jobs;
                    $spider->perform();
                } catch( Exception $e ) {
                } catch(JobException $e ) {
                }

                $spider = null;
                unset($spider);
            }
            
            sleep(15);

            if ($time < time() - 3600) {
                break;
            }
        }
    }

}

