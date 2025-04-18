<?php

namespace label\BeslistAPI;

use label\DB;

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

    /**
     * @var InputInterface
     */
    private static $input;

    /**
     * @var OutputInterface
     */
    private static $output;
    
    private static $LIMIT = 100;

    /**
     * Show and log messages
     * @param string $message
     */
    public function processMessage($message)
    {
        $milliseconds = intval(explode(' ', microtime())[0] * 1000);
        $record = date('Y-m-d H:i:s') . '.' . $milliseconds
                . '|p' . str_pad(getmypid(), 7, '0', STR_PAD_LEFT)
                . '|' . $message;
        self::$output->writeln($record);
        file_put_contents(TMP_DIR . '/beslist_api.txt', $record . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Configure the current command
     */
    protected function configure()
    {
        $this
                ->setName('api:beslist:put')
                ->setDescription('run beslist script to put data to beslist server');
    }

    /** @noinspection PhpMissingParentCallCommonInspection */

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

        exec('ps auxwww|grep "cron:beslist:put"|grep -v grep|grep -v php-fpm| grep -v bash|grep -v "su -"', $res);
        if (count($res) > 2)
        {
            self::$output->writeln('You can not run more threads');
            exit;
        }

        $db = DB::getInstance(DB::USAGE_WRITE);
        $dbr = DB::getInstance(DB::USAGE_READ);

        $time = time();

        $urls = [
            'auth' => 'https://shopitem.api.beslist.nl/auth/v1/shops',
            'put' => 'https://shopitem.api.beslist.nl/product/v2/shops/%d/items',
            'item' => 'https://shopitem.api.beslist.nl/product/v2/shops/%d/items/%d',
        ];

        $this->processMessage('Start working');

        $csv_list = $dbr->getAll("SELECT * FROM `saved_csv` 
        WHERE `api_shop_id` != '' AND `api_client_id` != '' AND `api_key` != ''");

        $this->processMessage('Found ' . count($csv_list) . ' lists');

        $curl = new \Curl();
        foreach ($csv_list as $csv)
        {
            $fname = ROOT_DIR . '/tmp/sa_csv_fields_' . $csv->id . '_api.csv';

            $this->processMessage('Current list: ' . $csv->title . ' (' . $csv->id . ')');

            $shedule = $csv->api_shedule;
            $shedule = explode(',', $shedule);
            $shedule = array_map('intval', $shedule);

            if (!in_array((int) date('H'), $shedule))
            {
                $this->processMessage('Not in shedule time. Sheduled on: ' . $csv->api_shedule . '; Current date: ' . date('H'));
                continue;
            }

            $curl->initialize([
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.4 (KHTML, like Gecko) Chrome/22.0.1229.92 Safari/537.4',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    "Accept:application/json",
                    "apikey:" . $csv->api_key,
                ],
            ]);

            $this->processMessage('Try to auth, AUTK KEY: ' . $csv->api_key);

            $curl->set_url($urls['auth']);
            $res = $curl->exec();
            $res = json_decode($res, true);

            $res = isset($res[$csv->api_shop_id]) ? $res[$csv->api_shop_id] : false;
            if (!$res)
            {
                $this->processMessage('Auth wrong, check data please (key: ' . $csv->api_key . ', shop_id: ' . $csv->api_shop_id . ')');
                continue;
            }

            $this->processMessage('Start recache list ' . $csv->title . ' (' . $csv->id . ')');
            
            $query = '
                SELECT * 
                FROM saved_csv 
                WHERE id = '. $csv->id . '
            ';
            
            $item = $dbr->getRow($query);
            
            $recache = new RecacheSA();
            $data = $recache->recache($item);

            $this->processMessage('End recache list ' . $csv->title . ' (' . $csv->id . ')');
            $this->processMessage('Found ' . count($data) . ' items');
            
            $old_csv = $this->getOldCSV($fname);
            $this->processMessage('Get Old CSV. Found ' . count($old_csv) . ' items');
            
            $orders = $this->compareItems($old_csv, $data);
            $this->processMessage('Get NEW/CHANGED. Found ' . count($orders) . ' items');
            
            $this->putNewCSV($fname, $data);
            $this->processMessage('Save csv file (' . $fname . ')');
            
            $iterations = ceil(count($orders) / self::$LIMIT);
            
            for ($i = 0; $i < $iterations; ++$i)
            {
                $this->processMessage("\titeration: $i");

                $put_orders = array_slice($orders, ($i * self::$LIMIT), self::$LIMIT);

                $url = sprintf($urls['put'], $csv->api_shop_id);
                $curl->set_post(json_encode($put_orders), false);
                $curl->set_url($url, 'PUT');

                $res = $curl->exec();
                $res = json_decode($res, true);
                if ($res['url'])
                {
                    $this->processMessage('Success');
                    $this->processMessage(print_r($res, true));

                    $curl->set_url($res['url'], 'GET');
                    $res = $curl->exec();
                    $res = json_decode($res, true);

                    $this->processMessage('Get batch');
                    $this->processMessage(print_r($res, true));
                }
                else
                {
                    $this->processMessage('Error');
                    $this->processMessage(print_r($res, true));
                }
            }
        }

        $this->processMessage('End working');
    }
    
    private function compareItems($old, $new) 
    {
        $diff = [];
        if ($old)
        {
            foreach ($new as $item)
            {
                $exist = false;
                $equals = true;
                foreach ($old as $old_item)
                {
                    if ($old_item['externalId'] == $item['externalId'])
                    {
                        $exist = true;

                        foreach ($old_item as $header => $value)
                        {
                            if ( ! isset($item[$header]) || $item[$header] != $value)
                            {
                                $equals = false;
                                break 2;
                            }
                        }
                    }
                }

                if ( ! $exist || ! $equals)
                {
                    $diff[] = $item;
                }
            }
        }
        else 
        {
            $diff = $new;
        }
        
        return $diff;
    }
    
    private function getOldCSV($fname) 
    {
        $csv = [];
        if (file_exists($fname))
        {
            $headers = [];

            $handle = fopen($fname, "r");
            if ($handle)
            {
                while (($values = fgetcsv($handle, 10000, ";")) !== FALSE)
                {
                    if (!$headers)
                    {
                        $headers = $values;
                    }
                    else
                    {
                        $item = [];
                        foreach ($headers as $id => $header)
                        {
                            $item[$header] = $values[$id];
                        }
                        $csv[] = $item;
                    }
                }

                fclose($handle);
            }
        }

        return $csv;
    }

    private function putNewCSV($fname, $data) 
    {
        $header = false;
        $handle = fopen($fname, 'w');
        if ($handle)
        {
            foreach ($data as $item)
            {
                if ( ! $header)
                {
                    $header = true;
                    fputcsv($handle, array_keys($item), ';');
                }

                fputcsv($handle, $item, ';');
            }

            fclose($handle);
        }
    }

}
