<?php

namespace Tests\Unit;

use App\Domain\Candidates\Enums\RecruitmentStage;
use PHPUnit\Framework\TestCase;

/**
 * Aturan perpindahan tahap ada di StageTransitions (diuji di
 * ApplicationManagementTest); enum hanya menyimpan kategori tahap.
 */
class RecruitmentStageTest extends TestCase
{
    public function test_only_outcomes_are_final()
    {
        $this->assertSame(
            [RecruitmentStage::Hired, RecruitmentStage::Rejected, RecruitmentStage::Withdrawn],
            array_values(array_filter(RecruitmentStage::cases(), fn (RecruitmentStage $stage) => $stage->isFinal())),
        );
    }

    public function test_process_stages_share_one_category()
    {
        $this->assertSame(
            ['invited', 'registered', 'in_process', 'hired', 'rejected', 'withdrawn'],
            array_map(fn (RecruitmentStage $stage) => $stage->value, RecruitmentStage::cases()),
        );
    }
}
