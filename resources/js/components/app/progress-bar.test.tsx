import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vite-plus/test';
import { ProgressBar } from '@/components/app/progress-bar';

describe('ProgressBar', () => {
    it('exposes the value to assistive technology and as text', () => {
        render(
            <ProgressBar
                percent={66.6}
                label="2 dari 3 materi wajib selesai"
            />,
        );

        const bar = screen.getByRole('progressbar', {
            name: '2 dari 3 materi wajib selesai',
        });
        expect(bar).toHaveAttribute('aria-valuenow', '67');
        expect(screen.getByText('67%')).toBeInTheDocument();
    });

    it('clamps values outside 0–100', () => {
        const { rerender } = render(<ProgressBar percent={140} label="x" />);
        expect(screen.getByRole('progressbar')).toHaveAttribute(
            'aria-valuenow',
            '100',
        );

        rerender(<ProgressBar percent={-5} label="x" />);
        expect(screen.getByRole('progressbar')).toHaveAttribute(
            'aria-valuenow',
            '0',
        );
    });
});
