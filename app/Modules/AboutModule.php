<?php

namespace App\Modules;

use App\Services\GitHubClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AboutModule extends HubModule
{
    public string $slug = 'about';

    public string $title = 'About';

    public string $summary = 'Who made this, and what else they have built.';

    public bool $onHome = false;

    private const REPOS_SHOWN = 6;

    public function __construct(private GitHubClient $client) {}

    public function load(Request $request): array
    {
        return ['profile' => config('hub.about'), 'repositories' => $this->repositories(), 'githubUsername' => config('hub.github_username')];
    }

    /**
     * Recent public projects, skipping forks. GitHub being down only hides this section.
     *
     * @return list<array<string, mixed>>
     */
    private function repositories(): array
    {
        $username = config('hub.github_username');
        if (! $username) {
            return [];
        }

        try {
            return Cache::remember('github-repos', 3600, fn () => collect($this->client->repos($username))
                ->reject(fn ($repo) => $repo['fork'] ?? false)->take(self::REPOS_SHOWN)
                ->map(fn ($repo) => ['name' => $repo['name'], 'url' => $repo['html_url'], 'description' => $repo['description'] ?? '', 'language' => $repo['language'] ?? ''])
                ->values()->all());
        } catch (ConnectionException|RequestException) {
            return [];
        }
    }
}
