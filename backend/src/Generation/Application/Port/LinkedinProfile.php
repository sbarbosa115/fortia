<?php

namespace App\Generation\Application\Port;

/** What is read from a public profile: the input of the LinkedIn diagnostic (PRD §7.18). */
final class LinkedinProfile
{
    /**
     * @param list<string> $experience one line per position ("Title at Company, years")
     * @param list<string> $skills
     */
    public function __construct(
        public readonly string $url,
        public readonly string $name,
        public readonly string $headline,
        public readonly ?string $location = null,
        public readonly ?string $summary = null,
        public readonly array $experience = [],
        public readonly array $skills = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'headline' => $this->headline,
            'location' => $this->location,
            'summary' => $this->summary,
            'experience' => $this->experience,
            'skills' => $this->skills,
        ];
    }
}
