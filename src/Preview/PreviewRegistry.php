<?php

declare(strict_types=1);

namespace Papercut\Laravel\Preview;

use Closure;

final class PreviewRegistry
{
    /** @var array<string, Closure|array<string, mixed>> */
    private array $views = [];

    /** @param Closure|array<string, mixed> $data */
    public function register(string $view, Closure|array $data = []): void
    {
        $this->views[$view] = $data;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_map('strval', array_keys($this->views));
    }

    public function has(string $view): bool
    {
        return array_key_exists($view, $this->views);
    }

    /** @return array<string, mixed> */
    public function data(string $view): array
    {
        $data = $this->views[$view] ?? [];

        if ($data instanceof Closure) {
            $data = $data();
            if (!is_array($data)) {
                throw new \UnexpectedValueException("Preview data for $view must be an array");
            }
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
