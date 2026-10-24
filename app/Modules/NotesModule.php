<?php

namespace App\Modules;

use App\Models\Note;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotesModule extends HubModule
{
    public string $slug = 'notes';

    public string $title = 'Notes';

    public string $summary = 'Write, edit, and delete short notes.';

    public function load(Request $request): array
    {
        return [
            'notes' => Note::latest('updated_at')->orderByDesc('id')->get(),
            'note' => $this->currentNote($request),
        ];
    }

    public function submit(Request $request): Response
    {
        $note = $this->currentNote($request);

        if ($request->input('action') === 'delete' && $note) {
            $note->delete();

            return redirect($this->url());
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
        ]);
        if ($note) {
            $note->update($data);
        } else {
            $note = Note::create($data);
        }

        return redirect($this->url(['note' => $note->id]));
    }

    private function currentNote(Request $request): ?Note
    {
        $id = $request->input('note');

        return $id ? Note::findOrFail($id) : null;
    }
}
