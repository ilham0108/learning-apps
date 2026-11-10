import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vite-plus/test';
import { ConfirmDialog } from '@/components/app/confirm-dialog';

function renderDialog(
    props: Partial<Parameters<typeof ConfirmDialog>[0]> = {},
) {
    const onConfirm = vi.fn();
    const onOpenChange = vi.fn();

    render(
        <ConfirmDialog
            open
            onOpenChange={onOpenChange}
            title="Tolak kandidat?"
            description="Tindakan ini dicatat di riwayat."
            confirmLabel="Tolak"
            onConfirm={onConfirm}
            {...props}
        />,
    );

    return { onConfirm, onOpenChange };
}

describe('ConfirmDialog', () => {
    it('shows the title and description', () => {
        renderDialog();

        expect(
            screen.getByRole('dialog', { name: 'Tolak kandidat?' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Tindakan ini dicatat di riwayat.'),
        ).toBeInTheDocument();
    });

    it('calls onConfirm when the confirm button is pressed', async () => {
        const { onConfirm } = renderDialog();

        await userEvent.click(screen.getByRole('button', { name: 'Tolak' }));

        expect(onConfirm).toHaveBeenCalledOnce();
    });

    it('closes without confirming when cancelled', async () => {
        const { onConfirm, onOpenChange } = renderDialog();

        await userEvent.click(screen.getByRole('button', { name: 'Batal' }));

        expect(onOpenChange).toHaveBeenCalledWith(false);
        expect(onConfirm).not.toHaveBeenCalled();
    });

    it('disables both buttons while processing', () => {
        renderDialog({ processing: true });

        expect(screen.getByRole('button', { name: 'Batal' })).toBeDisabled();
        expect(screen.getByRole('button', { name: /Tolak/ })).toBeDisabled();
    });
});
