<?php

namespace Lle\PdfGeneratorBundle\Command;

use Lle\PdfGeneratorBundle\Version\VersionInitializer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'lle:pdf-generator:init-versions', description: 'Start the history of the files of the existing templates')]
final class InitVersionsCommand extends Command
{
    public function __construct(
        protected VersionInitializer $initializer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->initializer->init();
        (new SymfonyStyle($input, $output))->success($count . ' version(s) created.');

        return Command::SUCCESS;
    }
}
