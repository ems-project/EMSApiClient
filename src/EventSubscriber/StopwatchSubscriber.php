<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Stopwatch\Stopwatch;

final class StopwatchSubscriber implements EventSubscriberInterface
{
    private $stopwatch;

    public function __construct()
    {
        $this->stopwatch = new Stopwatch();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onCommand'],
            ConsoleEvents::TERMINATE => ['onTerminate'],
        ];
    }

    public function onCommand(ConsoleCommandEvent $event): void
    {
        $this->stopwatch->start((string) $event->getCommand()->getName());
    }

    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();
        $stopwatch = $this->stopwatch->stop((string) $command->getName());

        $io = new SymfonyStyle($event->getInput(), $event->getOutput());
        $io->newLine(2);
        $io->listing([
            sprintf('Duration: %s', $this->formatDuration($stopwatch->getDuration())),
            sprintf('Memory: %s', $this->formatMemory($stopwatch->getMemory()))
        ]);
    }

    private function formatMemory(int $bytes): string
    {
        return round($bytes / 1000 / 1000, 2) . ' MB';
    }

    private function formatDuration(int $microseconds): string
    {
        return $microseconds / 1000 . ' s';
    }

}