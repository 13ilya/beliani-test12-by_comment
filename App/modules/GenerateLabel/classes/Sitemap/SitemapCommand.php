<?php

namespace label\Sitemap;

use label\DB;
use label\Sitemap\Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SitemapCommand
 * Used for work with sitemap using console
 */
class SitemapCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('sitemap:make')
            ->setDescription('Generate sitemaps for shops')
            ->addOption(
                'site',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Sites you want to generate sitemap'
            )
            ->addOption(
                'site-except',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Sites you DON\'T want to generate sitemap'
            )
            ->addOption(
                'ununique-show',
                null,
                InputOption::VALUE_NONE,
                'Show all ununique urls on the screen'
            )
            ->addOption(
                'specialchars-show',
                null,
                InputOption::VALUE_NONE,
                'Show report about non-ascii chars found in urls'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $db = DB::getInstance(DB::USAGE_READ);
        $query = '
            SELECT id
            FROM shop
            WHERE 1';

        $siteIncluded = [];
        $preparedPlacesIncluded = [];
        if (!empty($input->getOption('site'))) {
            foreach ($input->getOption('site') as $site) {
                $siteIncluded[] = str_replace(['www.', 'http:', 'https:', '/'], '', $site);
                $preparedPlacesIncluded[] = '?';
            }
        }
        if (count($siteIncluded)) {
            $query .= '
                AND REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                url,
                                \'/\',
                                \'\'
                            ),
                            \'https:\',
                            \'\'
                        ),
                        \'http:\',
                        \'\'
                    ),
                    \'www.\',
                    \'\'
                ) IN ('.implode(',', $preparedPlacesIncluded).')';
        }

        $siteExcluded = [];
        $preparedPlacesExcluded = [];
        if (!empty($input->getOption('site-except'))) {
            foreach ($input->getOption('site-except') as $site) {
                $siteExcluded[] = str_replace(['www.', 'http:', 'https:', '/'], '', $site);
                $preparedPlacesExcluded[] = '?';
            }
        }
        if (count($siteExcluded)) {
            $query .= '
                AND REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                url,
                                \'/\',
                                \'\'
                            ),
                            \'https:\',
                            \'\'
                        ),
                        \'http:\',
                        \'\'
                    ),
                    \'www.\',
                    \'\'
                ) NOT IN ('.implode(',', $preparedPlacesExcluded).')';
        }

        $sites = $db->getAll($query, null, array_merge($siteIncluded, $siteExcluded));
        
        foreach ($sites as $site) {
            $generator = new Generator($site->id);
            $output->writeln('Starting ' . $generator->getFullDomain());
            if ($input->getOption('specialchars-show')) {
                $generator->enableSpecialCharsReport();
            }
            $generator->run();
            $generator->writeInFile();
            if ($input->getOption('specialchars-show')) {
                if (count($generator->getSpecialCharsReport())) {
                    $output->writeln('Unappropriate chars in url found:');
                    foreach ($generator->getSpecialCharsReport() as $foundUrl) {
                        $output->writeln($foundUrl);
                    }
                }
            }
            $output->writeln('Done '. $generator->getFullDomain());

            if ($input->getOption('ununique-show')) {
                foreach ($generator->getUnuniqueUrls() as $url) {
                    $output->writeln($url['url'].' : ');
                    foreach ($url['locationSet'] as $location) {
                        $output->writeln(' - ' . $location['location'] . ' ' . $location['language']);
                    }
                }
            }
        }

        $output->writeln('Finish');
    }
}