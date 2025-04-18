<?php

namespace label\Spam;

use label\DB;
use label\RedisProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SpamCommand
 * That command used to send spam messages
 */
class SpamCommand extends Command
{
    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * Configure the current command
     */
    protected function configure()
    {
        $this
            ->setName('cron:spam')
            ->setDescription('Makes send spam messages')
            ->addArgument(
                'source',
                null,
                InputArgument::REQUIRED,
                'where get ids, could be one of: main, queue'
            )
            ->addOption(
                'threads',
                null,
                InputOption::VALUE_REQUIRED,
                'run spam in n threads'
            )
            ->addOption(
                'domain',
                null,
                InputOption::VALUE_OPTIONAL,
                'current domain for job'
            );
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

        $threads = (!empty($input->getOption('threads'))) ? $input->getOption('threads') : 1;
        $domain = (!empty($input->getOption('domain'))) ? $input->getOption('domain') : false;
        
        if ( ! $domain)
        {
            $domain = gethostname();
        }
        
        if ($input->getArgument('source') == 'main') {
            
            $spam = new Spam();
            $spam->newsletter_cron_inactive();
            
            $this->runThreads($threads, $domain);
        } else {
            if ($threads > 1) {
                throw new \Exception('You can not run multiple threads in slave thread');
            }
            
            $this->send($domain);
        }
        return 0;
    }

    /**
     * Run separate threads
     * @param int $n count of threads
     * @throws \Exception
     */
    private function runThreads($n, $domain)
    {
        exec('ps auxwww|grep "cron:spam queue" |grep -v grep|grep -v php-fpm| grep -v bash|grep -v "su -"', $res);

        if (count($res) >= $n) {
            throw new \Exception('You can not run more threads');
        }
        $n -= count($res);

        $command = 'php console.php cron:spam queue --domain=' . escapeshellarg($domain) . ' --threads=1 > /dev/null &';
        for ($i = 0; $i < $n; $i++) {
            exec($command);
        }
    }

    /**
     * Send messages
     */
    private function send($domain)
    {
        $_time = time();
        
        $spam = new Spam($domain);
        while (true) 
        {
            if ($spam->get_spam()) 
            {
                $spam->initialize();
                $spam->send();
            }
            
            sleep(15);
            
            if ($_time < time() - 3300) 
            {
                break;
            }
        }
    }
}