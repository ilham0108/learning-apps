<?php

namespace Tests\Feature\Database;

use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Exams\Models\AttemptQuestion;
use App\Domain\Exams\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_receive_a_ulid_public_id_and_keep_an_integer_primary_key()
    {
        $profile = CandidateProfile::factory()->create();

        $this->assertTrue(Str::isUlid($profile->public_id));
        $this->assertIsInt($profile->getKey());
        $this->assertTrue($profile->getIncrementing());
        $this->assertSame('public_id', $profile->getRouteKeyName());
        $this->assertSame($profile->public_id, $profile->getRouteKey());
    }

    public function test_route_binding_resolves_by_public_id_only()
    {
        $profile = CandidateProfile::factory()->create();

        $resolved = (new CandidateProfile)->resolveRouteBinding($profile->public_id);
        $this->assertTrue($profile->is($resolved));

        $this->expectException(ModelNotFoundException::class);
        (new CandidateProfile)->resolveRouteBinding((string) $profile->id);
    }

    public function test_attempt_questions_use_a_random_token_instead_of_the_bank_question_id()
    {
        $question = AttemptQuestion::factory()->create();

        $this->assertTrue(Str::isUlid($question->token));
        $this->assertSame('token', $question->getRouteKeyName());
    }

    public function test_user_serialization_hides_the_internal_id_and_secrets()
    {
        $user = User::factory()->withTwoFactor()->create();

        $array = $user->toArray();

        $this->assertArrayHasKey('public_id', $array);
        $this->assertArrayNotHasKey('id', $array);
        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('two_factor_secret', $array);
    }

    public function test_attempt_serialization_hides_the_session_token_hash()
    {
        $attempt = ExamAttempt::factory()->create(['session_token_hash' => hash('sha256', 'token')]);

        $this->assertArrayNotHasKey('session_token_hash', $attempt->toArray());
    }
}
