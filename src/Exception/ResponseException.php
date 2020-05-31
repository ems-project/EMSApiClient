<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResponseException extends \Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forFileInitUpload(ResponseInterface $response, \SplFileInfo $file): ResponseException
    {
        return new self(vsprintf('Init upload file %s failed [%d]: %s', [
            $file->getFilename(),
            $response->getStatusCode(),
            $response->getContent(false)
        ]));
    }

    public static function forFileChunk(ResponseInterface $response, \SplFileInfo $file): ResponseException
    {
        return new self(vsprintf('Chunk file %s failed [%d]: %s', [
            $file->getFilename(),
            $response->getStatusCode(),
            $response->getContent(false)
        ]));
    }
}