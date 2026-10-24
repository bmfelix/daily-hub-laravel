<?php

namespace App\Modules;

use App\Models\Task;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TodoModule extends HubModule
{
    public string $slug = 'todo';

    public string $title = 'To-do';

    public string $summary = 'A simple task list saved in the database.';

    public function load(Request $request): array
    {
        return ['tasks' => Task::orderBy('done')->latest()->orderByDesc('id')->get()];
    }

    public function submit(Request $request): Response
    {
        $action = $request->input('action');

        if ($action === 'add') {
            $data = $request->validate(['title' => ['required', 'string', 'max:200']]);
            Task::create($data);
        } elseif (in_array($action, ['toggle', 'delete'], true)) {
            $task = Task::findOrFail($request->input('task'));
            $action === 'toggle' ? $task->update(['done' => ! $task->done]) : $task->delete();
        }

        return redirect($this->url());
    }
}
