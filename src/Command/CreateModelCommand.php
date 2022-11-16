<?php

namespace Lle\PdfGeneratorBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Lle\PdfGeneratorBundle\Entity\PdfModel;
use Lle\PdfGeneratorBundle\Generator\PdfGenerator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

final class CreateModelCommand extends Command
{
    protected static $defaultName = 'lle:pdf-generator:create-model';

    public function __construct(
        private EntityManagerInterface $em,
        private PdfGenerator $generator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Create a new pdf model');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $model = $this->generator->newInstance();
        $model->setCode($io->ask('What is the code'));

        $io->note('The pdf path is ' . $this->generator->getPath());

        $model->setPath($io->ask('What is the ressource'));
        $model->setLibelle($io->ask('What is the libelle'));
        $model->setType($io->choice('What is the type', $this->generator->getTypes(), $this->generator->getDefaultGenerator()));
        $model->setDescription($io->ask('What is the description'));

        $this->em->persist($model);
        $this->em->flush();

        $io->success('Model is create !');

        return Command::SUCCESS;
    }
}