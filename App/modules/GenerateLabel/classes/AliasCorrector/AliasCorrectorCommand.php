<?php

namespace label\AliasCorrector;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AliasCorrectorCommand
 * Used to rewrite bad aliases in database and make report about that.
 */
class AliasCorrectorCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('alias:correct')
            ->setDescription('Correct bad aliases in database')
            ->addOption(
                'show-manageable',
                null,
                InputOption::VALUE_NONE,
                'Only show what should be changed without applying changes'
            )
            ->addOption(
                'show-problem',
                null,
                InputOption::VALUE_NONE,
                'Show list of problem aliases that can not be corrected'
            )->addOption(
                'store-manageable',
                null,
                InputOption::VALUE_NONE,
                'Store report about manageable aliases into file'
            )->addOption(
                'report-only',
                null,
                InputOption::VALUE_NONE,
                'Do not apply any correction'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $areas = [
            Corrector::AREA_CONTENT,
            Corrector::AREA_SERVICE,
            Corrector::AREA_CATEGORY,
            Corrector::AREA_OFFER,
            Corrector::AREA_NEWS,
        ];
        foreach ($areas as $area) {
            $corrector = new Corrector($area);
            $output->writeln($area);

            $incorrectAliasesCount = count($corrector->getManageableAliases());
            $output->writeln('Found ' . $incorrectAliasesCount . ' aliases ready to correct');
            if ($incorrectAliasesCount > 0) {
                if ($input->getOption('show-manageable')) {
                    foreach ($corrector->getManageableAliases() as $aliasId => $alias) {
                        $output->writeln($aliasId . ':' . $corrector->getEssenceId($aliasId) . ':' . $corrector->getRawAliases()[$aliasId] . ' - ' . $alias);
                    }
                }

                if ($input->getOption('store-manageable')) {
                    $file = fopen(TMP_DIR . '/alias_report/' . $area . '.csv', 'w');
                    fwrite($file, '"ID";"language";"alias before";"alias after"'."\n");
                    foreach ($corrector->getManageableAliases() as $aliasId => $alias) {
                        $line = '"' . $corrector->getEssenceId($aliasId) . '";"';
                        $line .= $corrector->getLanguage($aliasId) . '";"';
                        $line .= $corrector->getRawAliases()[$aliasId] . '";"';
                        $line .= $alias . '"' . "\n";
                        fwrite($file, $line);
                    }
                    fclose($file);
                }
            }

            $problemAliasesCount = count($corrector->getProblemAlieases());
            $output->writeln('Found ' . $problemAliasesCount . ' problem aliases');
            if ($input->getOption('show-problem')) {
                foreach ($corrector->getProblemAlieases() as $aliasId => $alias) {
                    $logline = $aliasId . ':' . $corrector->getEssenceId($aliasId) . ':';
                    if (isset($corrector->getManageableAliases()[$aliasId])) {
                        $logline .= $corrector->getManageableAliases()[$aliasId];
                    } else {
                        $logline .= $corrector->getRawAliases()[$aliasId];
                    }
                    $logline .= ' - ' . $alias;
                    $output->writeln($logline);
                }
            }

            if (!$input->getOption('report-only')) {
                if ($corrector->correct()) {
                    $output->writeln($area . ' corrected');
                } else {
                    $output->writeln('Something was wrong with correction ' . $area);
                }
            }
        }
    }
}
