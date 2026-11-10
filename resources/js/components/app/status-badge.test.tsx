import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';
import {
    CampaignStatusBadge,
    RecruitmentStageBadge,
} from '@/components/app/status-badge';

describe('status badges', () => {
    it('always shows a text label, not only a color', () => {
        render(<RecruitmentStageBadge stage="rejected" />);

        expect(screen.getByText('Tidak lolos')).toBeInTheDocument();
    });

    it('labels every campaign status', () => {
        const { rerender } = render(<CampaignStatusBadge status="open" />);
        expect(screen.getByText('Dibuka')).toBeInTheDocument();

        rerender(<CampaignStatusBadge status="closed" />);
        expect(screen.getByText('Ditutup')).toBeInTheDocument();
    });
});
