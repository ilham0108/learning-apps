/**
 * Bentuk data dari API Resource Laravel. Semua `id` adalah public_id (ULID),
 * tanggal berformat ISO 8601 UTC, dan tanggal tanpa jam berformat YYYY-MM-DD.
 */

export type RecruitmentStage =
    | 'invited'
    | 'registered'
    | 'under_review'
    | 'shortlisted'
    | 'rejected'
    | 'hired'
    | 'withdrawn';

export type CampaignStatus = 'draft' | 'open' | 'closed';

/** CampaignSummaryResource */
export type CampaignSummary = {
    id: string;
    name: string;
    status: CampaignStatus;
    position: string;
    department: string;
    starts_on: string;
    ends_on: string | null;
    applications_count?: number;
};

/** OwnApplicationResource */
export type OwnApplication = {
    id: string;
    campaign: string;
    position: string;
    department: string;
    stage: RecruitmentStage;
    applied_at: string;
};

export type PaginationMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

/** Hasil App\Http\Resources\Paginated */
export type Paginated<T> = {
    data: T[];
    meta: PaginationMeta;
};

export type EvaluationStatus = 'draft' | 'final';
export type Recommendation = 'strong_yes' | 'yes' | 'consider' | 'no';
export type EvaluationCriterion =
    | 'exam'
    | 'learning'
    | 'interview'
    | 'attitude'
    | 'other';

/** DepartmentResource */
export type Department = {
    id: string;
    name: string;
    is_active: boolean;
    positions: {
        id: string;
        name: string;
        is_active: boolean;
        campaigns_count: number;
    }[];
};

export type PositionOption = {
    id: string;
    name: string;
    department: string;
};

export type CampaignOption = {
    id: string;
    name: string;
    status: CampaignStatus;
};

export type HrUserOption = {
    id: string;
    name: string;
    email: string;
};

/** CampaignResource */
export type CampaignDetail = {
    id: string;
    name: string;
    status: CampaignStatus;
    position: PositionOption;
    starts_on: string;
    ends_on: string | null;
    closed_at: string | null;
    created_by: string | null;
    hr_users?: HrUserOption[];
};

/** CandidateListResource */
export type CandidateListItem = {
    id: string;
    candidate_number: string;
    name: string;
    email: string;
    is_active: boolean;
    is_activated: boolean;
    registered_on: string;
    applications: {
        id: string;
        campaign: string;
        position: string;
        stage: RecruitmentStage;
    }[];
};

export type StageHistoryEntry = {
    from: RecruitmentStage | null;
    to: RecruitmentStage;
    reason: string | null;
    changed_by: string | null;
    created_at: string | null;
};

export type InternalNote = {
    id: string;
    body: string;
    author: string | null;
    created_at: string | null;
    updated_at: string | null;
    can_edit: boolean;
};

export type CandidateEvaluation = {
    id: string;
    evaluator: string | null;
    is_mine: boolean;
    status: EvaluationStatus;
    recommendation: Recommendation | null;
    summary: string | null;
    finalized_at: string | null;
    scores: {
        criterion: EvaluationCriterion;
        score: number | null;
        note: string | null;
    }[];
};

/** ApplicationDetailResource */
export type ApplicationDetail = {
    id: string;
    campaign: {
        id: string;
        name: string;
        status: CampaignStatus;
        position: string;
        department: string;
    };
    stage: RecruitmentStage;
    stage_changed_at: string;
    applied_at: string;
    allowed_stages: RecruitmentStage[];
    history: StageHistoryEntry[];
    notes: InternalNote[];
    evaluations: CandidateEvaluation[];
};

/** CandidateDetailResource */
export type CandidateDetail = {
    id: string;
    candidate_number: string;
    name: string;
    email: string;
    phone: string | null;
    last_education: string | null;
    registered_on: string;
    is_active: boolean;
    activated_at: string | null;
    consent_given_at: string | null;
    consent_policy_version: string | null;
    last_login_at: string | null;
    applications: ApplicationDetail[];
};

export type ProgramStatus = 'draft' | 'published' | 'archived';
export type LearningProgressStatus =
    | 'not_started'
    | 'in_progress'
    | 'completed';
export type AssignmentSource =
    | 'individual'
    | 'campaign'
    | 'position'
    | 'selection';
export type MaterialType =
    | 'article'
    | 'pdf'
    | 'image'
    | 'video_embed'
    | 'video_file'
    | 'link';

/** ProgressSummary::toArray() */
export type ProgressSummary = {
    required_total: number;
    required_completed: number;
    percent: number;
    status: LearningProgressStatus;
};

/** ProgramSummaryResource */
export type ProgramSummary = {
    id: string;
    name: string;
    category: string | null;
    status: ProgramStatus;
    target_position: string | null;
    estimated_minutes: number | null;
    modules_count?: number;
    materials_count?: number;
    assignments_count?: number;
};

export type AdminMaterial = {
    id: string;
    title: string;
    type: MaterialType;
    is_required: boolean;
    is_archived: boolean;
    file_name: string | null;
    preview_url: string | null;
};

/** ProgramDetailResource */
export type ProgramDetail = {
    id: string;
    name: string;
    description: string | null;
    category: string | null;
    status: ProgramStatus;
    estimated_minutes: number | null;
    target_position: { id: string; name: string } | null;
    modules?: {
        id: string;
        title: string;
        description: string | null;
        materials: AdminMaterial[];
    }[];
};

export type ProgramAssignmentRow = {
    id: string;
    candidate: { id: string; name: string; candidate_number: string };
    campaign: string;
    assigned_via: AssignmentSource;
    due_at: string | null;
    progress: ProgressSummary;
};

/** Isi materi yang aman untuk browser (MaterialContent::for). */
export type MaterialContent = {
    body_html: string | null;
    file_url: string | null;
    file_name: string | null;
    file_mime: string | null;
    file_size: number | null;
    embed_url: string | null;
    external_url: string | null;
};

export type QuestionType =
    | 'multiple_choice'
    | 'multiple_select'
    | 'true_false'
    | 'short_answer'
    | 'essay'
    | 'case_study';
export type Difficulty = 'easy' | 'medium' | 'hard';
export type QuestionStatus = 'active' | 'archived';
export type ExamStatus = 'draft' | 'published' | 'archived';
export type ExamType =
    | 'pre_test'
    | 'post_test'
    | 'general_knowledge'
    | 'technical'
    | 'product_knowledge'
    | 'sop'
    | 'case_study';
export type NavigationMode = 'free' | 'forward_only';
export type ScorePolicy = 'highest' | 'latest';
export type MultiSelectScoring = 'all_or_nothing' | 'partial';
export type ResultRelease = 'immediate' | 'after_close' | 'manual';

export type CategoryOption = { id: string; name: string };

/** QuestionListResource */
export type QuestionListItem = {
    id: string;
    excerpt: string;
    category: string;
    topic: string | null;
    type: QuestionType;
    difficulty: Difficulty;
    default_points: string;
    status: QuestionStatus;
    version: number;
    children_count?: number;
    usage_count?: number;
};

export type QuestionContent = {
    type: QuestionType;
    difficulty: Difficulty;
    body_html: string;
    explanation_html: string | null;
    rubric: string | null;
    default_points: string;
    options: { body: string; is_correct: boolean }[];
    accepted_answers: string[];
};

/** QuestionResource (khusus HR, berisi kunci jawaban) */
export type QuestionDetail = QuestionContent & {
    id: string;
    category?: CategoryOption;
    topic: string | null;
    version: number;
    status: QuestionStatus;
    is_superseded: boolean;
    children?: QuestionContent[];
};

/** ExamResource */
export type ExamDetail = {
    id: string;
    title: string;
    description: string | null;
    type: ExamType;
    duration_minutes: number;
    passing_grade: string;
    max_attempts: number;
    opens_at: string | null;
    closes_at: string | null;
    opens_at_input: string | null;
    closes_at_input: string | null;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    navigation: NavigationMode;
    score_policy: ScorePolicy;
    multi_select_scoring: MultiSelectScoring;
    result_release: ResultRelease;
    show_review: boolean;
    status: ExamStatus;
    is_locked: boolean;
    duplicated_from: { id: string; title: string } | null;
    prerequisite_programs?: { id: string; name: string }[];
    related_programs?: { id: string; name: string }[];
    questions_count?: number;
    assignments_count?: number;
    total_points?: string;
};

export type ExamQuestionRow = {
    id: string;
    excerpt: string;
    type: QuestionType;
    category: string;
    difficulty: Difficulty;
    version: number;
    is_superseded: boolean;
    points: string;
};

export type ExamAssignmentRow = {
    id: string;
    candidate: { id: string; name: string; candidate_number: string };
    campaign: string;
    assigned_via: AssignmentSource;
    opens_at: string | null;
    closes_at: string | null;
    max_attempts: number;
    has_override: boolean;
    override: {
        opens_at: string | null;
        closes_at: string | null;
        max_attempts: number | null;
    };
    attempts_count: number;
};
