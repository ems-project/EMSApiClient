<?php

declare(strict_types=1);

namespace App\Command;

use App\Api;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

final class UploadFilesCommand extends Command
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
        $this->style->title('Upload files');

        if (!$this->api->test()) {
            return 0;
        }

        foreach ($this->findFiles($input->getArgument('directory')) as $file) {
            $this->api->upload($file);
        }

        return 1;
    }

    private function findFiles(string $directory): \Generator
    {
        $finder = new Finder();
        $finder->files()->in($directory);

        if (!$finder->hasResults()) {
            $this->style->error(sprintf('No files found in %s', $directory));
        }

        $this->style->section(sprintf('Found %d files in %s', $finder->count(), $directory));
        $progressBar = $this->style->createProgressBar($finder->count());

        foreach ($finder as $file) {
            yield $file;
            $progressBar->advance();
        }

        $progressBar->finish();;
    }
}