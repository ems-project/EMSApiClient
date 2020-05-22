<?php

declare(strict_types=1);

namespace App;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\FileinfoMimeTypeGuesser;

final class Api
{
    /** @var HttpClient */
    private $client;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(string $url, string $token, LoggerInterface $logger)
    {
        $this->client = HttpClient::create([
            'base_uri' => $url,
            'headers' => ['X-Auth-Token' => $token]
        ]);

        if ($this->client instanceof LoggerAwareInterface) {
            $this->client->setLogger($logger);
        }

        $this->logger = $logger;
    }

    public function test(): bool
    {
        try {
            $response = $this->client->request('GET', '/api/test');
            $json = \json_decode($response->getContent(false), true);

            return $json['success'] ?? false;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    public function upload(SplFileInfo $file): bool
    {
        try {
            $hash = sha1_file($file->getRealPath());
            $type = (new FileinfoMimeTypeGuesser())->guessMimeType($file->getRealPath());

            $response = $this->client->request('POST', '/api/file/init-upload', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'name' => $file->getFilename(),
                    'hash' => $hash,
                    'size' => $file->getSize(),
                    'type' => $type,
                ])
            ]);
            $json = \json_decode($response->getContent(false), true);
            $success = $json['success'] ?? false;

            if (!$success) {
                return false;
            }

            return $this->chunk($file, $type, $hash);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    private function chunk(SplFileInfo $file, string $type, string $hash): bool
    {
        try {
            $url = sprintf('/api/file/chunk/%s', $hash);
            $response = $this->client->request('POST', $url, [
                'headers' => ['Content-Type' => $type],
                'body' => file_get_contents($file->getRealPath())
            ]);

            $json = \json_decode($response->getContent(false), true);

            return $json['success'] ?? false;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }
}