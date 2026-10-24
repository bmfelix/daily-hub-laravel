<?php

namespace App\Modules;

use Illuminate\Contracts\Container\Container;

/**
 * The modules listed in config/hub.php, built through the container so their
 * API clients are injected.
 */
class ModuleRegistry
{
    /** @var array<string, HubModule>|null */
    private ?array $modules = null;

    public function __construct(private Container $container) {}

    /**
     * @return array<string, HubModule> keyed by slug, in menu order
     */
    public function all(): array
    {
        if ($this->modules === null) {
            $this->modules = [];
            foreach (config('hub.modules') as $class) {
                $module = $this->container->make($class);
                $this->modules[$module->slug] = $module;
            }
        }

        return $this->modules;
    }

    public function find(string $slug): ?HubModule
    {
        return $this->all()[$slug] ?? null;
    }
}
