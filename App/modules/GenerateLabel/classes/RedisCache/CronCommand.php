<?php

namespace label\RedisCache;

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
        file_put_contents(TMP_DIR . '/cron_redis.txt', $record . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Configure the current command
     */
    protected function configure()
    {
        $this
            ->setName('cron:redis')
            ->setDescription('run redis clear cache in queue')
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
        
        exec('ps auxwww|grep "cron:redis"|grep -v grep|grep -v php-fpm| grep -v bash|grep -v "su -"', $res);
        if (count($res) > 4) {
            self::$output->writeln('You can not run more threads');
            exit;
        }
        
        $db = DB::getInstance(DB::USAGE_WRITE);
        $dbr = DB::getInstance(DB::USAGE_READ);
        
        $time = time();
        
        \Resque::setBackend(REDIS_HOST, RedisProvider::getDatabaseIndex(RedisProvider::USAGE_QUEUE));
        $worker = new \Resque_Worker('redis');
        
        while (true) {
            $jobs = [];
            
            while ($data = $worker->reserve()) {
                if ($data && isset($data->payload['args'])) {
                    foreach ($data->payload['args'] as $job) {
                        $md5 = md5(serialize($job));
                        $jobs[$md5] = $job;
                    }
                }
            }
            
            if ($jobs) {
                file_put_contents(TMP_DIR . '/cron_redis.txt', '');
            }
            
            $caches = [];
            foreach ($jobs as $job) {
                $functions = $job['function'];
                $functions = is_array($functions) ? $functions : [$functions];

                if ($job['action'] == 'clear') {
                    foreach ($functions as $fn) {
                        $this->processMessage('', "cacheClear: $fn, {$job['shop']}, {$job['lang']}");
                        $_deleted = cacheClear($fn, $job['shop'], $job['lang'], true);
                        $caches = array_merge($caches, $_deleted);
                        
                        $this->processMessage('', "Items: " . print_r($_deleted, true));
                    }
                } 

                $caches = array_values(array_unique($caches));
            }

            if ($caches) {
                $db->disconnect();
                $db->connect();
                $db->query("SET NAMES 'utf8'");

                $dbr->disconnect();
                $dbr->connect();
                $dbr->query("SET NAMES 'utf8'");
                
                foreach ($caches as $function) {
                    if (stripos($function, 'getOffer') === false) {
                        continue;
                    }

                    if (preg_match('/^~~(.*?)~~(.*?)~~(.*?)~~$/iu', $function, $matches)) {
                        $shop_id = $matches[1];
                        $fn = $matches[2];
                        $lang = $matches[3];
                        
                        $this->processMessage('', "Function RAW " . $fn);

                        $fn = $this->parseFn($fn);
                        if ( ! $fn) {
                            $this->processMessage('', "Function not found");
                            continue;
                        }

                        $this->processMessage('', "Function " . print_r($fn, true));

                        if ($shop_id) {
                            $shopCatalogue = new \Shop_Catalogue($db, $dbr, $shop_id, $lang);
                        }

                        $this->processMessage('', "launch functions...");

                        if (count(explode('::', $fn['fn'])) == 2) {
                            array_unshift($fn['params'], $db, $dbr);
                        }

                        if ($shop_id && method_exists($shopCatalogue, $fn['fn'])) 
                        {
                            $response = call_user_func_array([$shopCatalogue, $fn['fn']], $fn['params']);
                        } 
                        else if (function_exists($fn['fn'])) 
                        {
                            $response = call_user_func_array($fn['fn'], $fn['params']);
                        } 
                        else if (count(explode('::', $fn['fn'])) == 2)
                        {
                            $response = call_user_func_array('\\' . $fn['fn'], $fn['params']);
                        }

                        $this->processMessage('', 'Response: ' . print_r($response, true));
                    } else {
                        $this->processMessage('', "Key is wrong");
                    }
                }
            }
                        
            sleep(5);

            if ($time < time() - 3600) {
                break;
            }
        }
    }
    
    /**
     * 
     * @param string $fn
     * @return mixed
     */
    private function parseFn($fn) {
        if (preg_match('#(.+)\((.*)\)#iu', $fn, $matches)) {
            $params = $matches[2];
            $params = strpos($params, chr(0)) !== false ? explode(chr(0), $params) : $params;

            if ($params && is_array($params)) {
                return [
                    'fn' => $matches[1],
                    'params' => $params,
                ];
            }

            return [
                'fn' => $matches[1],
                'params' => explode(',', $matches[2]),
            ];
        }

        return false;
    }
}

