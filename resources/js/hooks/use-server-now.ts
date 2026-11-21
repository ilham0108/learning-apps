import { useEffect, useState } from 'react';

/**
 * Waktu server saat ini (ms), diperbarui setiap `intervalMs`. Selisih jam
 * perangkat terhadap `serverNow` dihitung sekali saat halaman dimuat, sehingga
 * hitung mundur tidak bergantung pada jam perangkat yang salah.
 */
export function useServerNow(serverNow: string, intervalMs = 30_000): number {
    const [offset] = useState(() => Date.parse(serverNow) - Date.now());
    const [now, setNow] = useState(() => Date.now() + offset);

    useEffect(() => {
        const id = setInterval(() => setNow(Date.now() + offset), intervalMs);

        return () => clearInterval(id);
    }, [offset, intervalMs]);

    return now;
}
