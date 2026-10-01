<?php

namespace App\Chat\Application\Tool;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/** A group of account tools of one area (questionnaires, organizations…), collected by ChatTools. */
#[AutoconfigureTag('app.chat_toolbox')]
interface ChatToolbox
{
    /** @return list<ChatTool> */
    public function tools(): array;
}
