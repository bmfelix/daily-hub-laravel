<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Task;
use App\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DataModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The homepage Radio card calls radio APIs; answer them as "down".
        Http::fake(['*' => Http::response(null, 503)]);
    }

    public function test_home_links_to_every_module(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (app(ModuleRegistry::class)->all() as $module) {
            $response->assertSee($module->url(), false);
        }
    }

    public function test_menu_marks_the_current_page(): void
    {
        $this->get('/todo')->assertSee('href="'.url('/todo').'" aria-current="page"', false);
    }

    public function test_unknown_page_is_404(): void
    {
        $this->get('/nope')->assertNotFound();
    }

    public function test_module_without_a_form_rejects_posts(): void
    {
        $this->post('/weather')->assertStatus(405);
    }

    public function test_add_task(): void
    {
        $this->post('/todo', ['action' => 'add', 'title' => 'Buy milk'])->assertRedirect('/todo');

        $this->assertDatabaseHas('tasks', ['title' => 'Buy milk', 'done' => false]);
    }

    public function test_empty_task_title_shows_an_error(): void
    {
        $this->from('/todo')->post('/todo', ['action' => 'add', 'title' => ''])->assertSessionHasErrors('title');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_toggle_and_delete_task(): void
    {
        $task = Task::create(['title' => 'Walk']);

        $this->post('/todo', ['action' => 'toggle', 'task' => $task->id]);
        $this->assertTrue($task->fresh()->done);

        $this->post('/todo', ['action' => 'delete', 'task' => $task->id]);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_done_tasks_are_listed_last(): void
    {
        Task::create(['title' => 'Walk', 'done' => true]);
        Task::create(['title' => 'Read']);

        $this->get('/todo')->assertSeeInOrder(['Read', 'Walk']);
    }

    public function test_missing_task_is_404(): void
    {
        $this->post('/todo', ['action' => 'toggle', 'task' => 999])->assertNotFound();
    }

    public function test_create_note(): void
    {
        $response = $this->post('/notes', ['action' => 'save', 'title' => 'Idea', 'body' => 'Text']);

        $response->assertRedirect('/notes?note='.Note::sole()->id);
    }

    public function test_edit_note(): void
    {
        $note = Note::create(['title' => 'Old']);

        $this->post('/notes', ['action' => 'save', 'note' => $note->id, 'title' => 'New', 'body' => '']);

        $this->assertSame('New', $note->fresh()->title);
        $this->assertDatabaseCount('notes', 1);
    }

    public function test_delete_note(): void
    {
        $note = Note::create(['title' => 'Gone']);

        $this->post('/notes', ['action' => 'delete', 'note' => $note->id])->assertRedirect('/notes');

        $this->assertDatabaseCount('notes', 0);
    }

    public function test_open_note_fills_the_form(): void
    {
        $note = Note::create(['title' => 'Shown', 'body' => 'Body text']);

        $this->get("/notes?note={$note->id}")->assertSee('Edit note')->assertSee('Body text');
    }
}
