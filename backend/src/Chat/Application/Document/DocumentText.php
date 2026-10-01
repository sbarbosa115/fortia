<?php

namespace App\Chat\Application\Document;

/**
 * Reads the text of a document the user attaches to the chat (port; see AttachedFile for the types).
 */
interface DocumentText
{
    /**
     * @param string $extension one of AttachedFile::EXTENSIONS
     *
     * @throws \App\Chat\Domain\Error\UnreadableFile FILE_UNREADABLE when the file is damaged or not what it says
     */
    public function read(string $extension, string $contents): string;
}
