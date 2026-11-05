<?php

namespace App\Modules;

use App\Models\Task;
use Illuminate\Http\Request;

/**
 * Shows tasks. They are read-only here: the data comes from elsewhere.
 */
class TodoModule extends HubModule
{
    public string $slug = 'todo';

    public string $title = 'To-do';

    public string $summary = 'A simple task list.';

    public string $cardView = 'cards.todo';

    public function card(): array
    {
        $tasks = Task::where('done', false)->latest()->orderByDesc('id');

        return ['openTasks' => (clone $tasks)->limit(4)->get(), 'openCount' => (clone $tasks)->count()];
    }

    public function load(Request $request): array
    {
        return ['tasks' => Task::orderBy('done')->latest()->orderByDesc('id')->get()];
    }
}
