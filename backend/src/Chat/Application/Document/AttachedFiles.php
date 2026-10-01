<?php

namespace App\Chat\Application\Document;

use App\Chat\Domain\AttachedFile;

/**
 * POST /chat/files: reads a document the user attaches to the chat and hands its text back; nothing is stored (the
 * client keeps it with its message, like the draft).
 */
final class AttachedFiles
{
    public function __construct(private readonly DocumentText $documents)
    {
    }

    public function read(string $filename, string $contents): AttachedFile
    {
        $extension = AttachedFile::admit($filename, \strlen($contents));

        return AttachedFile::read($filename, $this->documents->read($extension, $contents));
    }
}
