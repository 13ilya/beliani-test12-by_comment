<?php

namespace label\LowMarginAlert;

use label\DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class LowMarginAlertCommand
 * Class used to alert users, when SA margin is below 'low_margin' value
 */
class LowMarginAlertCommand extends Command
{
    /**
     * Configure the current command
     */
    protected function configure()
    {
        $this
            ->setName('low_margin_alert')
            ->setDescription('Makes alerts to users');
    }

    /**
     * Run the current command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lowMarginAlert = new LowMarginAlert();
        $lowMarginAlert->execute_alert();
    }

}