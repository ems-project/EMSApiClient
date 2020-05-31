<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Finder\Finder;

final class Files implements \Countable, \IteratorAggregate
{
    /** @var string */
    private $directory;
    /** @var \Iterator */
    private $files;
    /** @var ?int */
    private $from;
    /** @var ?int */
    private $until;

    private function __construct(string $directory)
    {
        $this->files = Finder::create()->files()->in($directory)->getIterator();
        $this->directory = $directory;
    }

    public function __toString()
    {
        if (0 === $this->count()) {
            return sprintf('No files found in %s', $this->directory);
        }

        if ($this->countFiles() === $this->count()) {
            return sprintf('Found %d files found in %s', $this->count(), $this->directory);
        }

        return vsprintf('Found %d files in %s containing %d files', [
            $this->count(),
            $this->directory,
            $this->countFiles()
        ]);
    }

    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    public function countFiles(): int
    {
        return iterator_count($this->files);
    }

    public function getIterator()
    {
        $index = 0;
        $from = $this->from;
        $until = $this->until;

        return new \CallbackFilterIterator($this->files, function () use (&$index, $from, $until) {
            $index++;

            if ($from && $index <= $from) {
                return false;
            }

            if ($until && $index > $until) {
                return false;
            }

            return true;
        });
    }

    public static function fromInput(InputInterface $input): Files
    {
        $files = new self( $input->getArgument('directory'));

        $from = $input->getOption('from');
        if ($from) {
            $files->setFrom((int) $from);
        }

        $until = $input->getOption('until');
        if ($until) {
            $files->setUntil((int) $until);
        }

        return $files;
    }

    public function setFrom(int $from): void
    {
        $this->from = $from;
    }

    public function setUntil(int $until): void
    {
        $this->until = $until;
    }
}