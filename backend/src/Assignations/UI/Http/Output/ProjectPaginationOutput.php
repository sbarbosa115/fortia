<?php

namespace App\Assignations\UI\Http\Output;

/** Numbered pagination (PRD §8.1 style 1): {page, page_size, total_items, total_pages, has_next, has_previous}. */
final class ProjectPaginationOutput
{
    public function __construct(
        public readonly int $page,
        public readonly int $page_size,
        public readonly int $total_items,
        public readonly int $total_pages,
        public readonly bool $has_next,
        public readonly bool $has_previous,
    ) {
    }

    public static function of(int $page, int $pageSize, int $total): self
    {
        $pages = (int) ceil($total / max(1, $pageSize));

        return new self($page, $pageSize, $total, $pages, $page < $pages, $page > 1);
    }
}
