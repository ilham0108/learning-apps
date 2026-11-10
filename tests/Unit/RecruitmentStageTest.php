<?php

namespace Tests\Unit;

use App\Domain\Candidates\Enums\RecruitmentStage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecruitmentStageTest extends TestCase
{
    /**
     * @return array<string, array{RecruitmentStage, RecruitmentStage, bool}>
     */
    public static function transitions(): array
    {
        return [
            'invited → registered' => [RecruitmentStage::Invited, RecruitmentStage::Registered, true],
            'invited → withdrawn' => [RecruitmentStage::Invited, RecruitmentStage::Withdrawn, true],
            'invited → shortlisted' => [RecruitmentStage::Invited, RecruitmentStage::Shortlisted, false],
            'registered → under_review' => [RecruitmentStage::Registered, RecruitmentStage::UnderReview, true],
            'registered → hired' => [RecruitmentStage::Registered, RecruitmentStage::Hired, false],
            'under_review → shortlisted' => [RecruitmentStage::UnderReview, RecruitmentStage::Shortlisted, true],
            'under_review → rejected' => [RecruitmentStage::UnderReview, RecruitmentStage::Rejected, true],
            'under_review → hired' => [RecruitmentStage::UnderReview, RecruitmentStage::Hired, false],
            'shortlisted → hired' => [RecruitmentStage::Shortlisted, RecruitmentStage::Hired, true],
            'shortlisted → rejected' => [RecruitmentStage::Shortlisted, RecruitmentStage::Rejected, true],
            'rejected → under_review' => [RecruitmentStage::Rejected, RecruitmentStage::UnderReview, false],
            'hired → rejected' => [RecruitmentStage::Hired, RecruitmentStage::Rejected, false],
            'withdrawn → registered' => [RecruitmentStage::Withdrawn, RecruitmentStage::Registered, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_transition_rules(RecruitmentStage $from, RecruitmentStage $to, bool $allowed)
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_final_stages_have_no_further_transitions()
    {
        $this->assertTrue(RecruitmentStage::Hired->isFinal());
        $this->assertTrue(RecruitmentStage::Rejected->isFinal());
        $this->assertTrue(RecruitmentStage::Withdrawn->isFinal());
        $this->assertFalse(RecruitmentStage::Shortlisted->isFinal());
    }

    public function test_only_rejected_can_be_corrected_back_to_review()
    {
        $this->assertTrue(RecruitmentStage::Rejected->canBeCorrectedTo(RecruitmentStage::UnderReview));
        $this->assertFalse(RecruitmentStage::Hired->canBeCorrectedTo(RecruitmentStage::UnderReview));
        $this->assertFalse(RecruitmentStage::Rejected->canBeCorrectedTo(RecruitmentStage::Shortlisted));
    }

    public function test_rejection_withdrawal_and_correction_require_a_reason()
    {
        $this->assertTrue(RecruitmentStage::UnderReview->requiresReason(RecruitmentStage::Rejected));
        $this->assertTrue(RecruitmentStage::Shortlisted->requiresReason(RecruitmentStage::Withdrawn));
        $this->assertTrue(RecruitmentStage::Rejected->requiresReason(RecruitmentStage::UnderReview));
        $this->assertFalse(RecruitmentStage::UnderReview->requiresReason(RecruitmentStage::Shortlisted));
    }
}
