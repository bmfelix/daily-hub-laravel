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
            if ($module->onHome) {
                $response->assertSee($module->url(), false);
            }
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

    public function test_done_tasks_are_listed_last(): void
    {
        Task::create(['title' => 'Walk', 'done' => true]);
        Task::create(['title' => 'Read']);

        $this->get('/todo')->assertSeeInOrder(['Read', 'Walk']);
    }

    public function test_open_note_shows_its_text(): void
    {
        $note = Note::create(['title' => 'Shown', 'body' => 'Body text']);

        $this->get("/notes?note={$note->id}")->assertSee('Body text');
    }
}
