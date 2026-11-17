<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\User;
use Database\Seeders\UatFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** #453: etiquetas ligadas a su control, errores junto al campo, tablas con scope y un solo patrón de confirmación. */
class AccessibilityGuardsTest extends TestCase
{
    use RefreshDatabase;

    /** Etiquetas que no tienen for, no envuelven su control y no nombran un grupo. */
    private function orphanLabels(string $html): array
    {
        preg_match_all('/<label(?![^>]*\b(?:for|id)=)[^>]*>(.*?)<\/label>/s', $html, $matches);

        return array_values(array_filter($matches[1], fn ($inner) => ! preg_match('/<(input|select|textarea)/', $inner)));
    }

    public function test_main_forms_have_linked_labels(): void
    {
        $this->seed(UatFixtureSeeder::class);
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => Campus::query()->value('id'), 'is_master' => true]);

        foreach (['students.create', 'courses.create', 'teachers.create', 'holidays.create', 'enrollments.create', 'groups.create', 'sessions.create'] as $route) {
            $html = $this->actingAs($master)->get(route($route))->assertOk()->getContent();
            $this->assertSame([], $this->orphanLabels($html), "Etiquetas sin for en {$route}");
        }
    }

    public function test_validation_error_shows_next_to_the_field(): void
    {
        $this->seed(UatFixtureSeeder::class);
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => Campus::query()->value('id'), 'is_master' => true]);

        $this->actingAs($master)->from(route('teachers.create'))->post(route('teachers.store'), ['first_name' => ''])
            ->assertRedirect(route('teachers.create'));
        $this->actingAs($master)->get(route('teachers.create'))
            ->assertOk()
            ->assertSee('form-field--invalid', false)
            ->assertSee('for="teacher-first-name"', false)
            ->assertSee('class="field-error" role="alert"', false);
    }

    public function test_tables_have_header_scope_and_no_inline_handlers(): void
    {
        $this->seed(UatFixtureSeeder::class);
        $master = User::factory()->create(['role' => 'admin', 'campus_id' => Campus::query()->value('id'), 'is_master' => true]);

        foreach (['students.index', 'courses.index', 'finance.index', 'teachers.index'] as $route) {
            $html = $this->actingAs($master)->get(route($route))->assertOk()->getContent();
            preg_match_all('/<thead>.*?<\/thead>/s', $html, $heads);
            foreach ($heads[0] as $head) {
                $this->assertDoesNotMatchRegularExpression('/<th(?=[\s>])(?![^>]*scope=)[^>]*>/', $head, "th sin scope en {$route}");
            }
            $this->assertStringNotContainsString('onclick=', $html, $route);
            $this->assertStringNotContainsString('onsubmit=', $html, $route);
        }
    }
}
