<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GitHubClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function repos(string $username): array
    {
        return Http::timeout(6)->accept('application/vnd.github+json')
            ->get("https://api.github.com/users/{$username}/repos", ['sort' => 'pushed', 'per_page' => 30, 'type' => 'owner'])
            ->throw()->json() ?? [];
    }
}
