<?php

declare(strict_types=1);

namespace Papercut\Laravel\Rendering;

use Papercut\Exception\UnknownAttribute;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\PapercutManager;
use Papercut\Style\{Theme, ThemeOverride};

/**
 * The theme overrides of the document and page tags being rendered, innermost last, so the early style checks
 * see the styles a tag's `:theme` defines.
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
final class ThemeScope
{
    /** @var list<ThemeOverride> */
    private array $stack = [];

    /**
     * Early checks that failed against the theme in scope when their tag rendered, innermost build last. A wrapper
     * component renders the caller's content before the page or document it sits in, so such a check is decided
     * once that page or document builds.
     *
     * @var list<list<\Closure(Theme): void>>
     */
    private array $collect = [];

    /**
     * Unmet checks by the node that carries them. Held strongly (PHP 8.4 GC bug with weak maps, php/php-src#20001), so
     * {@see self::isolate()} gives each render its own and drops it afterwards.
     *
     * @var \SplObjectStorage<object, list<\Closure(Theme): void>>|null
     */
    private ?\SplObjectStorage $pending = null;

    private ?Theme $base = null;
    private ?Theme $current = null;

    public function push(ThemeOverride $override): void
    {
        $this->stack[] = $override;
        $this->current = null;
    }

    public function pop(): void
    {
        array_pop($this->stack);
        $this->current = null;
    }

    /** The configured theme with the stacked overrides applied. */
    public function current(): Theme
    {
        $base = app(PapercutManager::class)->pdfOptions()->theme();
        if ($this->current !== null && $this->base === $base) {
            return $this->current;
        }
        $theme = $base;
        foreach ($this->stack as $override) {
            $theme = $theme->withOverride($override);
        }
        $this->base = $base;

        return $this->current = $theme;
    }

    /**
     * Runs $check against the current theme; when it fails, keeps it to be decided by the page or document this
     * tag ends up in.
     *
     * @param \Closure(Theme): void $check throws when the theme lacks what the tag needs
     */
    public function early(\Closure $check): void
    {
        try {
            $check($this->current());
        } catch (UnknownAttribute|InvalidStructure) {
            if ($this->collect === []) {
                $check($this->current());

                return;
            }
            $this->collect[array_key_last($this->collect)][] = $check;
        }
    }

    /** A component starts building: checks recorded until {@see self::finish()} belong to its node. */
    public function begin(): void
    {
        $this->collect[] = [];
    }

    /** The build threw: drop what it collected. */
    public function abort(): void
    {
        array_pop($this->collect);
    }

    /**
     * The build finished with $node. A page ($throw false) drops the checks its theme satisfies and passes the rest on;
     * a document ($throw true) fails on those still unmet; any other node passes them on whole.
     */
    public function finish(object $node, ?bool $throw): void
    {
        $list = array_pop($this->collect) ?? [];
        if ($throw !== null) {
            $theme = $this->current();
            $left = [];
            foreach ($list as $check) {
                try {
                    $check($theme);
                } catch (UnknownAttribute|InvalidStructure $e) {
                    if ($throw) {
                        throw $e;
                    }
                    $left[] = $check;
                }
            }
            $list = $left;
        }
        if ($list !== []) {
            $this->pending ??= new \SplObjectStorage();
            $this->pending[$node] = $list;
        }
    }

    /** A parent took $node as a child: its unmet checks become the parent's. */
    public function inherit(object $node): void
    {
        if ($this->collect !== [] && $this->pending !== null && isset($this->pending[$node])) {
            $key = array_key_last($this->collect);
            array_push($this->collect[$key], ...$this->pending[$node]);
        }
    }

    /**
     * Runs $fn with an empty stack of overrides and checks, restoring the caller's afterwards, so a render that
     * died or a nested one never sees or leaks another's. Checks still unmet at the end of $fn fail then.
     *
     * @template T
     * @param \Closure(): T $fn
     * @return T
     */
    public function isolate(\Closure $fn): mixed
    {
        [$stack, $collect, $current, $pending] = [$this->stack, $this->collect, $this->current, $this->pending];
        $this->stack = [];
        $this->collect = [[]];
        $this->current = null;
        $this->pending = null;
        try {
            $result = $fn();
            $theme = $this->current();
            foreach ($this->collect[0] ?? [] as $check) {
                $check($theme);
            }

            return $result;
        } finally {
            [$this->stack, $this->collect, $this->current, $this->pending] = [$stack, $collect, $current, $pending];
        }
    }
}
