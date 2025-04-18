<?php
namespace label\Spider;

use label\DB;
use label\RedisProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SpiderBuildCommand
 * Command used to create list of all products/categories pages and cookies
 */
class SpiderBuildCommand extends Command
{
    const OPTION_ENQUEUE = 'enqueue';
    const OPTION_ID = 'id';
    const OPTION_SHOW_DEBUG = 'show-debug';
    const OPTION_LIMIT_URL = 'limit-url';
    const OPTION_URL = 'url';
    const OPTION_MOBILE_ONLY = 'mobile-only';
    const OPTION_DESKTOP_ONLY = 'desktop-only';

    /**
     * @var bool
     */
    private $mobileOnly = false;

    /**
     * @var bool
     */
    private $desktopOnly = false;

    /**
     * @var bool
     */
    private $reportOnly;

    /**
     * @var mixed[]
     */
    private $queue = [];

    /**
     * @var \Shop_Catalogue
     */
    private $shopCatalogue;

    /**
     * @var int
     */
    private $countJobs = 0;
    
    /**
     * @var string[][] $fluentCookiesSet array of fluent cookies
     */
    private $fluentCookiesSet = [];

    /**
     * Configure the current command
     */
    protected function configure()
    {
        $this
            ->setName('spider:build')
            ->setDescription('build queue for spider')
            ->addOption(
                self::OPTION_ENQUEUE,
                null,
                InputOption::VALUE_NONE,
                'enqueue urls, otherwise only report'
            )
            ->addOption(
                self::OPTION_ID,
                null,
                InputOption::VALUE_IS_ARRAY  | InputOption::VALUE_REQUIRED,
                'shop id'
            )
            ->addOption(
                self::OPTION_SHOW_DEBUG,
                null,
                InputOption::VALUE_NONE,
                'output debug data'
            )
            ->addOption(
                self::OPTION_LIMIT_URL,
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_REQUIRED,
                'limit urls to push into queue'
            )
            ->addOption(
                self::OPTION_URL,
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_REQUIRED,
                'url without domain with leading slash to push into queue'
            )
            ->addOption(
                self::OPTION_MOBILE_ONLY,
                null,
                InputOption::VALUE_NONE,
                'clear only mobile version'
            )
            ->addOption(
                self::OPTION_DESKTOP_ONLY,
                null,
                InputOption::VALUE_NONE,
                'clear only desktop version'
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
        $this->reportOnly = !($input->getOption(self::OPTION_ENQUEUE));

        $dbr = DB::getInstance(DB::USAGE_READ);
        $db = DB::getInstance(DB::USAGE_WRITE);
        if ((!empty($input->getOption(self::OPTION_ID)))) {
            $condition = ' AND id IN (' . implode(', ', $input->getOption(self::OPTION_ID)) . ')';
        } else {
            $condition = '';
        }
        $shopsIds = array_map(
            function($element){return $element->id;},
            $dbr->getAll('SELECT id FROM shop WHERE inactive = 0' . $condition)
        );
            
        $url = $input->getOption(self::OPTION_URL);

        \Resque::setBackend(REDIS_HOST, RedisProvider::getDatabaseIndex(RedisProvider::USAGE_QUEUE));

        $limit = $input->getOption(self::OPTION_LIMIT_URL);

        if ($input->getOption(self::OPTION_MOBILE_ONLY) || $input->getOption(self::OPTION_DESKTOP_ONLY)) {
            if ($input->getOption(self::OPTION_MOBILE_ONLY)) {
                $this->fluentCookiesSet[] = ['off_mobile' => '0'];
                $this->mobileOnly = true;
            }
            if ($input->getOption(self::OPTION_DESKTOP_ONLY)) {
                $this->fluentCookiesSet[] = ['off_mobile' => '1'];
                $this->desktopOnly = true;
            }
            /**
             * @todo throw exception if both
             */
        } else {
            $this->fluentCookiesSet = [['off_mobile' => '0'], ['off_mobile' => '1']];
        }

        foreach ($shopsIds as $id) {
            $shopCatalogue = new \Shop_Catalogue($db, $dbr, $id);
            $shopCatalogue->_shop->lang = $shopCatalogue->_seller->data->default_lang;
            $this->shopCatalogue = $shopCatalogue;

            $logPath = TMP_DIR . '/spider_build_' . $id . '.txt';
            file_put_contents($logPath, 'Start' . PHP_EOL);
            
            if ($url) {
                $message = $this->addOne($url);
                if (
                    ($message['level'] === 'report')
                    || (
                        ($message['level'] === 'debug')
                        && ($input->getOption(self::OPTION_SHOW_DEBUG))
                    )
                ) {
                    $record = date('Y-m-d H:i:s') . '|' . "Shop: " . $this->shopCatalogue->_shop->url;
                    file_put_contents($logPath, $record . PHP_EOL, FILE_APPEND);
                    $output->writeln($record);
                    $record = date('Y-m-d H:i:s') . '|' . "URL: " . $url;
                    file_put_contents($logPath, $record . PHP_EOL, FILE_APPEND);
                    $output->writeln($record);
                    $record = date('Y-m-d H:i:s') . '|' . $message['message'];
                    file_put_contents($logPath, $record . PHP_EOL, FILE_APPEND);
                    $output->writeln($record);
                }
            } else {
                foreach($this->build() as $message) {
                    if (
                        ($message['level'] === 'report')
                        || (
                            ($message['level'] === 'debug')
                            && ($input->getOption(self::OPTION_SHOW_DEBUG))
                        )
                    ) {
                        $record = date('Y-m-d H:i:s') . '|' . $message['message'];
                        file_put_contents($logPath, $record . PHP_EOL, FILE_APPEND);
                        $output->writeln($record);
                    }
                    if (
                        ($limit !== null)
                        && ($message['message'][0] === '[')
                        && ($this->countJobs >= $limit)
                    ) {
                        file_put_contents($logPath, 'Finished' . PHP_EOL, FILE_APPEND);
                        $output->writeln('Finished');
                        return 0;
                    }
                }
            }
            file_put_contents($logPath, 'Finished' . PHP_EOL, FILE_APPEND);
            $output->writeln('Finished');
        }
        return 0;
    }

    /**
     * @param String $url
     * @return string[]
     */
    private function addOne($url) {
        $lang = $this->shopCatalogue->_seller->data->default_lang;
        return $this->pushQueue($lang, $url);
    }
    
    /**
     * @return \Generator
     */
    private function build()
    {
        global $getall;
        $getall = 1;
        global $all_products;
        $all_products = 1;
        
        yield ['level' => 'report', 'message' =>  "Shop: " . $this->shopCatalogue->_shop->url];

        $lang = $this->shopCatalogue->_seller->data->default_lang;
        $url = "/";
        yield $this->pushQueue($lang, $url);

        foreach ($this->shopCatalogue->listAll(0, 0) as $cat) {
            $url = "/{$cat->alias}/";
            yield $this->pushQueue($lang, $url);
            yield ['level' => 'debug', 'message' =>  "Offers for {$cat->id}"];
            $offers = $this->shopCatalogue->getOffers($cat->id);
            yield ['level' => 'debug', 'message' =>  "count:" . count($offers)];
            foreach ($offers as $offer) {
                $url = "/{$offer->ShopSAAlias}.html";
                yield $this->pushQueue($lang, $url);
            }
            foreach ($cat->children as $cat1) {
                $url = "/{$cat->alias}/{$cat1->alias}/";
                yield $this->pushQueue($lang, $url);
                yield ['level' => 'debug', 'message' =>  "Offers for {$cat1->id}"];
                $offers = $this->shopCatalogue->getOffers($cat1->id);
                yield ['level' => 'debug', 'message' =>  "count:" . count($offers)];
                foreach ($offers as $offer) {
                    $url = "/{$offer->ShopSAAlias}.html";
                    yield $this->pushQueue($lang, $url);
                }
                foreach ($cat1->children as $cat2) {
                    $url = "/{$cat->alias}/{$cat1->alias}/{$cat2->alias}/";
                    yield $this->pushQueue($lang, $url);
                    yield ['level' => 'debug', 'message' =>  "Offers for {$cat2->id}"];
                    $offers = $this->shopCatalogue->getOffers($cat2->id);
                    yield ['level' => 'debug', 'message' =>  "count:" . count($offers)];
                    foreach ($offers as $offer) {
                        $url = "/{$offer->ShopSAAlias}.html";
                        yield $this->pushQueue($lang, $url);
                    }
                    foreach ($cat2->children as $cat3) {
                        $url = "/{$cat->alias}/{$cat1->alias}/{$cat2->alias}/{$cat3->alias}/";
                        yield $this->pushQueue($lang, $url);
                        yield ['level' => 'debug', 'message' =>  "Offers for {$cat3->id}"];
                        $offers = $this->shopCatalogue->getOffers($cat3->id);
                        yield ['level' => 'debug', 'message' =>  "count:" . count($offers)];
                        foreach ($offers as $offer) {
                            $url = "/{$offer->ShopSAAlias}.html";
                            yield $this->pushQueue($lang, $url);
                        }
                        foreach ($cat3->children as $cat4) {
                            $url = "/{$cat->alias}/{$cat1->alias}/{$cat2->alias}/{$cat3->alias}/{$cat4->alias}/";
                            yield $this->pushQueue($lang, $url);
                            yield ['level' => 'debug', 'message' =>  "Offers for {$cat4->id}"];
                            $offers = $this->shopCatalogue->getOffers($cat4->id);
                            yield ['level' => 'debug', 'message' =>  "count:" . count($offers)];
                            foreach ($offers as $offer) {
                                $url = "/{$offer->ShopSAAlias}.html";
                                yield $this->pushQueue($lang, $url);
                            }
                        }
                    }
                }
            }
        }
    }
    /**
     * Push to queue
     * @param string $lang
     * @param string $url url without domain with leading slash
     * @return string[]
     */
    private function pushQueue($lang, $url)
    {
        if ($this->shopCatalogue->_shop->ssl) {
            $schema = 'https';
        } else {
            $schema = 'http';
        }

        $domain = 'www.' . $this->shopCatalogue->_shop->url;

        $jobs = [];

        $cookies = [];
        if ($this->desktopOnly xor $this->mobileOnly) {
            $cookies = ['off_mobile' => $this->desktopOnly ? '1' : '0'];
        }

//        $jobs[] = [
//            'action' => 'clear',
//            'schema' => $schema,
//            'domain' => $domain,
//            'url' => $url,
//            'cookies' => $cookies,
//        ];

        $constCookiesSet[1] = [
            'shop_lang' => $lang,
            'skin' => 'autumn',
            'currency_code' => '',
        ];
        $constCookiesSet[2] = [
            'shop_lang' => '',
            'skin' => 'autumn',
            'currency_code' => '',
        ];
        foreach ($this->fluentCookiesSet as $cookiesSet) {
            foreach ($constCookiesSet as $constCookies) {
                $cookies = array_merge($constCookies, $cookiesSet);
                $jobs[] = [
                    'action' => 'crawl',
                    'schema' => $schema,
                    'domain' => $domain,
                    'url' => $url,
                    'cookies' => $cookies,
                ];
            }
        }

        $keyQueue = md5(serialize($jobs));
        if (!isset($this->queue[$keyQueue])) {
            $this->queue[$keyQueue] = true;
            $identifier = str_pad(++$this->countJobs, 5, '0', STR_PAD_LEFT);
            if ($this->reportOnly) {
                $token = 'REPORT-ONLY';
            } else {
                $token = \Resque::enqueue('spider', '\label\Spider\SpiderJob', $jobs);
            }
            $message =
                '[' . $identifier . ']cookies-set:' . json_encode($this->fluentCookiesSet)
                . '|schema:' . $schema . '|domain:' . $domain . '|url:' . $url . '|token:' . $token;
        } else {
            $message = '[00000]duplicate';
        }

        return [
            'level' => 'debug',
            'message' => $message,
        ];
    }
}