<?php

namespace App\Content\Infrastructure\Persistence;

use App\Content\Domain\Model\Video;
use App\Content\Domain\Repository\VideoRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Video> */
final class DoctrineVideoRepository extends DoctrineRepository implements VideoRepository
{
    protected function entityClass(): string
    {
        return Video::class;
    }

    public function find(string $id): ?Video
    {
        return $this->findEntity($id);
    }

    public function listFor(?string $language): array
    {
        $criteria = null === $language ? [] : ['language' => $language];

        // The title order follows the column's collation (utf8mb4_0900_ai_ci): case- and accent-insensitive.
        return $this->repository()->findBy($criteria, ['order' => 'ASC', 'title' => 'ASC', 'id' => 'ASC']);
    }

    public function add(Video $video): void
    {
        $this->persist($video);
    }

    public function remove(Video $video): void
    {
        $this->delete($video);
    }
}
