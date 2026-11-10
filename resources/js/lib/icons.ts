/**
 * Peta ikon Lucide untuk konsep yang berulang, agar ikon yang sama dipakai
 * di seluruh aplikasi. Ukuran standar: 16 px (size-4) di tombol dan tabel,
 * 20 px (size-5) di sidebar dan judul kartu.
 */
import type { LucideIcon } from 'lucide-react';
import { FileText, FileType, Image, Link2, PlayCircle } from 'lucide-react';
import type { MaterialType } from '@/types';

export {
    LayoutDashboard as DashboardIcon,
    Megaphone as CampaignIcon,
    Users as CandidatesIcon,
    UserRound as CandidateIcon,
    BookOpen as LearningIcon,
    FileText as MaterialIcon,
    Library as QuestionBankIcon,
    ClipboardCheck as ExamIcon,
    Timer as TimerIcon,
    PenLine as GradingIcon,
    ChartColumn as ReportIcon,
    ShieldCheck as SecurityIcon,
    Settings as SettingsIcon,
    LogOut as LogoutIcon,
    GraduationCap as BrandIcon,
    Inbox as EmptyIcon,
    TriangleAlert as ErrorIcon,
    Briefcase as PositionIcon,
    CalendarDays as CalendarIcon,
    CheckCircle2 as CompletedIcon,
    Circle as NotStartedIcon,
    CircleDot as InProgressIcon,
} from 'lucide-react';

export const materialTypeIcons: Record<MaterialType, LucideIcon> = {
    article: FileText,
    pdf: FileType,
    image: Image,
    video_embed: PlayCircle,
    video_file: PlayCircle,
    link: Link2,
};
