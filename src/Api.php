<?php

declare(strict_types=1);

namespace App;

use App\Exception\ResponseException;
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
    /** @var array */

    private $info = [
        self::INFO_UPLOAD_FAILED => null,
        self::INFO_UPLOAD_NEW => null,
        self::INFO_UPLOAD_SKIPPED => null,
    ];

    private const INFO_UPLOAD_FAILED  = 'Failed file uploads';
    private const INFO_UPLOAD_NEW     = 'New file uploads';
    private const INFO_UPLOAD_SKIPPED = 'Skipped file uploads';

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

    public function getInfo(): array
    {
        $info = [];

        foreach (array_filter($this->info) as $text => $count) {
            $info[] = [$text => $count];
        }

        return $info;
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

    public function upload(SplFileInfo $file)
    {
        try {
            $hash = sha1_file($file->getRealPath());
            if ($this->hashExists($hash)) {
                $this->info[self::INFO_UPLOAD_SKIPPED]++;
                return;
            }

            $type = (new FileinfoMimeTypeGuesser())->guessMimeType($file->getRealPath());
            $url = '/api/file/init-upload';

            $response = $this->client->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'name' => $file->getFilename(),
                    'hash' => $hash,
                    'size' => $file->getSize(),
                    'type' => $type,
                ])
            ]);

            $json = \json_decode($response->getContent(true), true);
            $success = $json['success'] ?? false;

            if (!$success) {
                throw ResponseException::forFileInitUpload($response, $file);
            }

            $this->chunk($file, $type, $hash);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->info[self::INFO_UPLOAD_FAILED]++;
        }
    }

    private function hashExists(string $hash): bool
    {
        $url = sprintf('/data/file/view/%s', $hash);
        $response = $this->client->request('HEAD', $url);

        return $response->getStatusCode() === 200 ? true: false;
    }

    private function chunk(SplFileInfo $file, string $type, string $hash)
    {
        try {
            $url = sprintf('/api/file/chunk/%s', $hash);
            $response = $this->client->request('POST', $url, [
                'headers' => ['Content-Type' => $type],
                'body' => file_get_contents($file->getRealPath())
            ]);
            $json = \json_decode($response->getContent(true), true);
            $success = $json['success'] ?? false;

            if (!$success) {
                throw ResponseException::forFileChunk($response, $file);
            }

            $this->info[self::INFO_UPLOAD_NEW]++;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->info[self::INFO_UPLOAD_FAILED]++;
        }
    }
}