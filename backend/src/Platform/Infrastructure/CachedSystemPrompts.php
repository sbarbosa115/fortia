<?php

namespace App\Platform\Infrastructure;

use App\Platform\Application\SystemPrompts;
use App\Platform\Domain\Repository\SystemPromptVersionRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CachedSystemPrompts implements SystemPrompts
{
    private const TTL = 300;

    public function __construct(
        private readonly SystemPromptVersionRepository $versions,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/config/system_prompts')]
        private readonly string $defaultsDir,
    ) {
    }

    public function get(string $key): string
    {
        if (!\array_key_exists($key, self::KEYS)) {
            throw new \InvalidArgumentException(\sprintf('Unknown system prompt "%s".', $key));
        }

        return $this->cache->get('system_prompt.'.$key, function (ItemInterface $item) use ($key): string {
            $item->expiresAfter(self::TTL);
            try {
                $latest = $this->versions->latest($key);
                if (null !== $latest) {
                    return $latest->text();
                }
            } catch (\Throwable $e) {
                $this->logger->warning('System prompt store failed; using the default of {key}.', ['key' => $key, 'exception' => $e]);
            }

            return $this->default($key);
        });
    }

    public function render(string $key, array $values = []): string
    {
        $text = $this->get($key);
        foreach ($values as $name => $value) {
            $text = str_replace('{'.$name.'}', $value, $text);
        }

        return $text;
    }

    /** The platform's default text of a key. */
    public function default(string $key): string
    {
        $path = $this->defaultsDir.'/'.$key.'.md';

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /** Forgets the cached text (after an edit). */
    public function forget(string $key): void
    {
        $this->cache->delete('system_prompt.'.$key);
    }
}
