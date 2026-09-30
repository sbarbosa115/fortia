<?php

namespace App\Shared\UI\Http\Web;

use App\Shared\Application\Storage\ObjectNotFound;
use App\Shared\Application\Storage\SignedRequestVerifier;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The endpoints behind the local object storage's signed URLs (LocalObjectStorage): what S3 would serve with a cloud
 * store. Uploads and downloads are accepted only with a valid, unexpired signature for that exact key.
 */
final class StorageController
{
    public function __construct(private readonly SignedRequestVerifier $storage)
    {
    }

    /** Form-style upload: the signed fields plus "file" (PRD §8.4 POST /signed-urls, answer_media). */
    #[Route('/storage/upload', name: 'storage_upload', methods: ['POST', 'OPTIONS'])]
    public function upload(Request $request): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return new Response(null, 204);
        }
        $allowed = $this->storage->verifyUploadForm($request->request->all());
        $file = $request->files->get('file');
        if (null === $allowed || !$file instanceof UploadedFile || !$file->isValid()) {
            return new Response('Forbidden', 403);
        }
        $size = (int) $file->getSize();
        if ($size < $allowed['min'] || $size > $allowed['max']) {
            return new Response('EntityTooLarge', 400);
        }
        $this->storage->store($allowed['key'], (string) file_get_contents($file->getPathname()), $allowed['content_type']);

        return new Response(null, 204);
    }

    /** Direct signed upload of the raw body (PRD §8.4 POST /signed-urls, prompt). */
    #[Route('/storage/put', name: 'storage_put', methods: ['PUT', 'OPTIONS'])]
    public function put(Request $request): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return new Response(null, 204);
        }
        $allowed = $this->storage->verifyPut($request->query->all());
        if (null === $allowed) {
            return new Response('Forbidden', 403);
        }
        $this->storage->store($allowed['key'], $request->getContent(), $allowed['content_type']);

        return new Response(null, 200);
    }

    #[Route('/storage/download', name: 'storage_download', methods: ['GET'])]
    public function download(Request $request): Response
    {
        $allowed = $this->storage->verifyDownload($request->query->all());
        if (null === $allowed) {
            return new Response('Forbidden', 403);
        }
        try {
            $contents = $this->storage->read($allowed['key']);
        } catch (ObjectNotFound) {
            return new Response('Not found', 404);
        }
        $filename = basename($allowed['key']);
        $response = new Response($contents);
        $mime = (new \finfo(\FILEINFO_MIME_TYPE))->buffer($contents);
        $response->headers->set('Content-Type', false === $mime ? 'application/octet-stream' : $mime);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            'inline' === $allowed['disposition'] ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
        ));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox");

        return $response;
    }
}
