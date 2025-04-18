<?php

namespace label\Recache;

use label\DB;
use label\RedisProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RecacheSACommand
 * That command used to recache and recreate export for all SA
 */
class RecacheSACommand extends Command
{
    private $debugTimer1;
    private $debugTimer2;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var string
     */
    private $queueFile;

    /**
     * Flag - is extra hours
     * @var boolean
     */
    private $is_extra = false;
    
    private static $reTiming1 = [
        'Start:',
        'before list:',
        'iteration start:',
        'iteration parsed:',
        'iteration pos 1.1:',
        'iteration pos 1.3:',
        'iteration pos 1.4:',
        'iteration pos 1.5:',
        'iteration pos 2:',
        'iteration pos 2.1 after offer:',
        'iteration pos 3:',
        'iteration pos 4:',
        'iteration pos 4-0-1:',
        'iteration pos 4-0-2:',
        'iteration pos 4-0-3:',
        'iteration pos 4-0-4:',
        'iteration pos 4-1:',
        'iteration pos 4-2:',
        'iteration pos 4-3:',
        'iteration pos 4-5:',
        'iteration pos 6:',
    ];

    private static $showTiming1 = [
        'Start:',
        'before list:',
        'iteration start:',
        'iteration parsed:',
        'iteration pos 1.1:',
        'iteration pos 1.3:',
        'iteration pos 1.4:',
        'iteration pos 1.5:',
        'iteration pos 2:',
        'iteration pos 2.1 after offer:',
        'iteration pos 3:',
        'iteration pos 4:',
        'iteration pos 4-0-1:',
        'iteration pos 4-0-2:',
        'iteration pos 4-0-3:',
        'iteration pos 4-0-4:',
        'iteration pos 4-1:',
        'iteration pos 4-2:',
        'iteration pos 4-3:',
        'iteration pos 4-5:',
        'iteration pos 6:',
        'after circle:',
    ];

    private static $reTiming2 = [
        'iteration start:',
    ];

    private static $showTiming2 = [
        'iteration end:',
    ];

    /**
     * Configure the current command
     */
    protected function configure()
    {
        $this
            ->setName('recache:sa')
            ->setDescription('Makes export files with all SA')
            ->setHelp('Example debug' . PHP_EOL . 'To debug SA use next command: php console.php recache:sa command --id-sa=[some id sa] --no-cache --show-debug --show-report')
            ->addArgument(
                'id-source',
                null,
                InputArgument::REQUIRED,
                'where get ids, could be one of: command, queue, all, extra'
            )
            ->addOption(
                'id',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'export only that id (you can use it multiple times)'
            )
            ->addOption(
                'id-sa',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'export only that sa id'
            )
            ->addOption(
                'no-cache',
                null,
                InputOption::VALUE_NONE,
                'don\'t use cache'
            )
            ->addOption(
                'show-debug',
                null,
                InputOption::VALUE_NONE,
                'show debug info'
            )
            ->addOption(
                'limit-sa',
                null,
                InputOption::VALUE_REQUIRED,
                'count of sa for every export file'
            )
            ->addOption(
                'show-report',
                null,
                InputOption::VALUE_NONE,
                'show intermediate reports'
            )
            ->addOption(
                'threads',
                null,
                InputOption::VALUE_REQUIRED,
                'run recache in n threads'
            )
            ->addOption(
                'main',
                null,
                InputOption::VALUE_NONE,
                'run main thread that runs another threads'
            )
            ->addOption(
                'unshift',
                null,
                InputOption::VALUE_NONE,
                'add current ids to the head of the queue'
            );

        $this->queueFile = TMP_DIR . '/recacheSAqueue.txt';
    }/** @noinspection PhpMissingParentCallCommonInspection */

    /**
     * Run the current command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $idsSa = null;
        
        switch ($this->input->getArgument('id-source')) {
            case 'command':
                if (!empty($this->input->getOption('id'))) {
                    $ids = $this->input->getOption('id');
                } else {
                    $ids = array_map(function($element) {return $element->id;}, $this->getRowsToExport());
                }
                if (!empty($this->input->getOption('id-sa'))) {
                    $idsSa = $this->input->getOption('id-sa');
                }
                break;
            case 'extra':
                $this->is_extra = true;
                $ids = $this->getExtraRowsToExport();
                break;
            case 'queue':
                if (!empty($this->input->getOption('id'))) {
                    throw new \Exception('You can not use option `id` with argument `all`');
                }
                if ($input->getOption('main')) {
                    throw new \Exception('You can not use argument `queue` with option `main`');
                }
                $id = $this->queueShift();
                if ($id === false) {
                    return 0;
                }
                $ids = [$id];
                break;
            case 'all':
                if (!empty($this->input->getOption('id'))) {
                    throw new \Exception('You can not use option `id` with argument `all`');
                }
                $ids = array_map(function($element) {return $element->id;}, $this->getRowsToExport());
                break;
            default:
                throw new \Exception('Unknown option `id-source`');
        }

        $threads = (!empty($input->getOption('threads'))) ? $input->getOption('threads') : 1;

        if ($input->getOption('main')) 
        {
            if (isset($tdsSa)) {
                throw new \Exception('You can not specify ids sa in multithreads');
            }
            
            global $redis;
            $redis = RedisProvider::getInstance(RedisProvider::USAGE_CACHE_LOCAL);

//            $db = DB::getInstance(DB::USAGE_WRITE);
//
//            if ($threads > 1) {
//                $db->query("call sp_Alias_SA()");
//                $db->query("call sp_Alias_Catalogue()");
//            }
            
//            if ($threads > 1) {
//                cacheClear('sa_csv_all(%', null, '', true);
//            }
            
            if ($input->getOption('unshift')) {
                $this->queueUnshift($ids);
            } else {
                $this->queuePush($ids);
            }

            $this->runThreads($threads);
        } 
        else if ($this->is_extra)
        {
            if ( ! $ids) {
                throw new \Exception('There is not items for recache');
            }
            
            $this->recache($ids);
        } 
        else 
        {
            if ($threads > 1) {
                throw new \Exception('You can not run multiple threads in slave thread');
            }
            
            
            $this->recache($ids, $idsSa);
            if ($this->input->getArgument('id-source') === 'queue') {
                while (true) {
                    $id = $this->queueShift();
                    if ($id === false) {
                        return 0;
                    }
                    $this->recache([$id]);
                }
            }
        }
        return 0;
    }

    /**
     * Run separate threads
     * @param int $n count of threads
     * @throws \Exception
     */
    private function runThreads($n)
    {
        exec('ps auxwww|grep "recache:sa queue" |grep -v grep|grep -v php-fpm| grep -v bash|grep -v "su -"', $res);

        if (count($res) >= $n) {
            throw new \Exception('You can not run more threads');
        }
        $n -= count($res);

        $command = 'php console.php recache:sa queue --threads=1';
        if (!empty($this->input->getOption('limit-sa'))) {
            $command .= ' --limit-sa=' . $this->input->getOption('limit-sa');
        }
        $command .= ' > /dev/null &';
        for ($i = 0; $i < $n; $i++) {
            exec($command);
        }
    }

    /**
     * Recache concrete rows
     * @param int[] $ids row id
     * @param int[]|null $idsSa
     */
    private function recache($ids, $idsSa = null)
    {
        /**
         * Force to use local prolo redis server instead of default server
         */
        global $redis;
        $redis = RedisProvider::getInstance(RedisProvider::USAGE_CACHE_LOCAL);

        $recache = new RecacheSA();
        if (isset($idsSa)) {
            $recache->setSaIds($idsSa);
        }
        
        if ($this->input->getOption('limit-sa') !== null) {
            $recache->setLimitSA($this->input->getOption('limit-sa'));
        }
        
        if ($this->input->getOption('no-cache')) {
            $recache->dontUseCache();
        }

        $allExports = $this->getRowsToExport($ids, $this->is_extra);

        foreach ($allExports as $export) {
            $this->debugTimer1 = getmicrotime();
            $this->debugTimer2 = getmicrotime();
            foreach ($recache->recache($export) as $message) {
                if (
                    ($message['level'] === 'report')
                    || ($message['level'] === 'main')
                ) {
                    file_put_contents(
                        "last_csv",
                        (($this->input->getArgument('id-source') === 'queue') ? 'p' . getmypid() . ':' : '')
                            . $message['message'] . "\n",
                        FILE_APPEND
                    );
                    if (
                        ($this->input->getOption('show-report'))
                        || ($message['level'] === 'main')
                    ) {
                        $milliseconds = intval(explode(' ', microtime())[0] * 1000);
                        $this->output->writeln(date('Y-m-d H:i:s') . '.' . $milliseconds . "\t" . $message['message']);
                    }
                } elseif ($message['level'] === 'debug') {
                    if ($this->input->getOption('show-debug')) {
                        $this->processDebugMessage($message['message']);
                    }
                }
            }
        }
    }

    /**
     * Process and output debug message
     * @param string[] $message
     */
    private function processDebugMessage($message)
    {
        if (in_array($message, self::$reTiming1)) {
            $timer1 = getmicrotime();
        }
        if (in_array($message, self::$reTiming2)) {
            $timer2 = getmicrotime();
        }

        if (in_array($message, self::$showTiming1)) {
            $message .= ' ' . round(getmicrotime() - $this->debugTimer1, 4);
        }
        if (in_array($message, self::$showTiming2)) {
            $message .= ' ' . round(getmicrotime() - $this->debugTimer2, 4);
        }

        if (isset($timer1)) {
            $this->debugTimer1 = $timer1;
        }
        if (isset($timer2)) {
            $this->debugTimer2 = $timer2;
        }

        $this->output->writeln($message);
    }

    /**
     * Get all|concrete rows to export
     * @param int[]|null $ids if null - all rows
     * @return string[]
     */
    private function getRowsToExport($ids = null, $extra = false)
    {
        $dbr = DB::getInstance(DB::USAGE_READ);

        $queryAllExports = "
            SELECT * 
            FROM `saved_csv` 
            WHERE `cached` "
            . ($extra ? " AND `extra_hours` != '' " : " AND `extra_hours` = '' ") 
            . (($ids !== null) ? (' AND id IN ('. implode(', ', $ids) . ') ' . "\n") : '') 
            . " ORDER BY `priority` ASC";

        return $dbr->getAll($queryAllExports);
    }

    /**
     * Get rows to export
     * @param int[]|null $ids if null - all rows
     * @return string[]
     */
    private function getExtraRowsToExport()
    {
        $dbr = DB::getInstance(DB::USAGE_READ);

        $queryAllExports = "
            SELECT * 
            FROM `saved_csv` 
            WHERE `cached` AND `extra_hours` != ''
            ORDER BY `priority` ASC";

        $rows = [];
        foreach ($dbr->getAll($queryAllExports) as $item) 
        {
            $item->extra_hours = explode(',', $item->extra_hours);
            $item->extra_hours = array_map('intval', $item->extra_hours);
            
            if (in_array((int) date('H'), $item->extra_hours))
            {
                $rows[] = (int)$item->id;
            }
        }
        
        return $rows;
    }

    /**
     * Push ids to export queue
     * Add to the end of queue if queue is not empty
     * @param int[] $ids
     */
    private function queuePush($ids)
    {
        $file = fopen($this->queueFile, 'r+');
        if ($file === false) {
            $file = fopen($this->queueFile, 'w');
            flock($file, LOCK_EX);
            $queue = $ids;
        } else {
            flock($file, LOCK_EX);
            $queueRaw = fgets($file);
            $queue = explode(' ', $queueRaw);

            $queue = array_merge($queue, $ids);
        }
        $queue = array_unique($queue);

        foreach ($queue as $i => $id) {
            if ((int)$id === 0) {
                unset($queue[$i]);
            }
        }

        ftruncate($file, 0);
        rewind($file);
        fwrite($file, implode(' ', $queue));
        fclose($file);
        flock($file, LOCK_UN);
    }

    /**
     * Push ids to export queue
     * Add to the head of queue if queue is not empty
     * @param int[] $ids
     */
    private function queueUnshift($ids)
    {
        $file = fopen($this->queueFile, 'r+');
        if ($file === false) {
            $file = fopen($this->queueFile, 'w');
            flock($file, LOCK_EX);
            $queue = $ids;
        } else {
            flock($file, LOCK_EX);
            $queueRaw = fgets($file);
            $queue = explode(' ', $queueRaw);

            $queue = array_merge($ids, $queue);
        }
        $queue = array_unique($queue);

        foreach ($queue as $i => $id) {
            if ((int)$id === 0) {
                unset($queue[$i]);
            }
        }

        ftruncate($file, 0);
        rewind($file);
        fwrite($file, implode(' ', $queue));
        fclose($file);
        flock($file, LOCK_UN);
    }

    /**
     * Shift first element from export queue
     * @return false|int false if queue empty
     */
    private function queueShift()
    {
        $file = fopen($this->queueFile, 'r+');
        flock($file, LOCK_EX);
        $queueRaw = fgets($file);
        $result = false;
        if (strlen($queueRaw) > 0) {
            $queue = explode(' ', $queueRaw);
            $result = array_shift($queue);
            ftruncate($file, 0);
            rewind($file);
            fwrite($file, implode(' ', $queue));
        }
        fclose($file);
        flock($file, LOCK_UN);
        return $result;
    }
}