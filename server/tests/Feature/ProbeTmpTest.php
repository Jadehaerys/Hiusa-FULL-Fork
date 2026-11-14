<?php

namespace Tests\Feature;

use App\Models\AcademicSemester;
use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesCollegeFixtures;
use Tests\TestCase;

class ProbeTmpTest extends TestCase
{
    use CreatesCollegeFixtures;
    use RefreshDatabase;

    public function test_probe(): void
    {
        config(['performance.api_cache.enabled' => false, 'services.groq.key' => '', 'services.anthropic.key' => '']);
        RateLimiter::for('ai-generation', fn () => Limit::none());
        $home = Organization::factory()->create(['organization_type' => 'SYSTEM_ADMINISTRATION']);
        $org = Organization::factory()->create(['organization_type' => 'STUDENT_ORGANIZATION', 'lifecycle_status' => 'active', 'is_active' => true]);
        $users = [
            'SAO' => User::factory()->superAdmin()->create(['organization_id' => $home->id]),
            'DEAN' => $this->makeCollegeHead($this->makeCollege('CCS')),
            'ADMIN' => User::factory()->admin()->create(['organization_id' => $org->id]),
            'OFFICER' => User::factory()->officer()->create(['organization_id' => $org->id]),
            'STUDENT' => User::factory()->student()->create(['organization_id' => $org->id]),
        ];
        $qs = json_decode(file_get_contents(getenv('PROBE_Q')), true);
        $out = [];
        foreach ($qs as [$r, $q]) {
            Sanctum::actingAs($users[$r]);
            $j = $this->postJson('/api/ai/assistant/ask', ['question' => $q])->json();
            $hrefs = [];
            foreach ($j['sections'] ?? [] as $s) { foreach ($s['rows'] as $row) { $hrefs[] = $row['href'] ?? '-'; } }
            $out[] = "[$r] $q\n  intent={$j['intent']} title={$j['title']}\n  ".mb_substr(str_replace("\n", ' / ', $j['answer'] ?? ''), 0, 420)."\n  hrefs=".implode(',', array_slice($hrefs, 0, 6));
        }
        file_put_contents(getenv('PROBE_OUT'), implode("\n", $out)."\n");
        $this->assertTrue(true);
    }
}
