<?php

namespace App\Modules;

use App\Models\Note;
use Illuminate\Http\Request;

/**
 * Shows notes. They are read-only here: the data comes from elsewhere.
 */
class NotesModule extends HubModule
{
    public string $slug = 'notes';

    public string $title = 'Notes';

    public string $summary = 'Short notes to read.';

    public string $cardView = 'cards.notes';

    public function card(): array
    {
        return ['latest' => Note::latest('updated_at')->orderByDesc('id')->first()];
    }

    public function load(Request $request): array
    {
        $id = $request->query('note');

        return [
            'notes' => Note::latest('updated_at')->orderByDesc('id')->get(),
            'note' => $id ? Note::findOrFail($id) : null,
        ];
    }
}
