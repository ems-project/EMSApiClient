<?php

declare(strict_types=1);

namespace App\Command;

use App\Api;
use App\Files;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class UploadFilesCommand extends Command implements CommandInterface
{
    /** @var Api */
    private $api;
    /** @var string */
    private $emsUrl;
    /** @var string */
    private $emsToken;
    /** @var SymfonyStyle */
    private $style;

    public function __construct(string $emsUrl, string $emsToken)
    {
        parent::__construct('api:upload-files');
        $this->emsUrl = $emsUrl;
        $this->emsToken = $emsToken;
    }

    protected function configure()
    {
        $this
            ->setDescription('Upload files from a local directory')
            ->addArgument('directory', InputArgument::REQUIRED, 'path to files')
            ->addOption('from', 'from',InputOption::VALUE_REQUIRED, 'start point files')
            ->addOption('until', 'until',InputOption::VALUE_REQUIRED, 'end point files')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->style = new SymfonyStyle($input, $output);
        $this->api = new Api($this->emsUrl, $this->emsToken, new ConsoleLogger($output));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->api->test()) {
            return 0;
        }

        $files = Files::fromInput($input);
        $this->style->writeln('Scanning directory ...');
        $this->style->section((string) $files);

        $progressBar = $this->style->createProgressBar($files->count());
        foreach ($files as $file) {
            $this->api->upload($file);
            $progressBar->advance();
        }
        $progressBar->finish();

        $this->style->newLine(2);
        $this->style->definitionList('Api Summary', new TableSeparator() ,...$this->api->getInfo());

        return 1;
    }
}