<?php

namespace App\Chat\UI\Http\Output;

/** 202 {job} of POST /chat (PRD §8.10). */
final class ChatJobEnvelopeOutput
{
    public function __construct(public readonly ChatJobOutput $job)
    {
    }
}
