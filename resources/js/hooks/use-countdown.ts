import { useEffect, useRef, useState } from 'react';

/**
 * Sisa waktu dihitung dari `deadline_at` dan `server_now` yang dikirim server,
 * bukan dari jam perangkat (risiko R3). Selisih jam perangkat terhadap server
 * dihitung ulang setiap kali `serverNow` baru diterima (sinkronisasi berkala).
 */
export function useCountdown(
    deadlineAt: string,
    serverNow: string,
    onExpire: () => void,
): number {
    const offsetRef = useRef(0);
    const expiredRef = useRef(false);
    const onExpireRef = useRef(onExpire);
    const [remaining, setRemaining] = useState(() =>
        Math.max(0, Date.parse(deadlineAt) - Date.parse(serverNow)),
    );

    useEffect(() => {
        onExpireRef.current = onExpire;
    }, [onExpire]);

    useEffect(() => {
        offsetRef.current = Date.parse(serverNow) - Date.now();
        const deadline = Date.parse(deadlineAt);

        const tick = () => {
            const left = Math.max(
                0,
                deadline - (Date.now() + offsetRef.current),
            );
            setRemaining(left);

            if (left === 0 && !expiredRef.current) {
                expiredRef.current = true;
                onExpireRef.current();
            }
        };

        tick();
        const interval = setInterval(tick, 1000);

        return () => clearInterval(interval);
    }, [deadlineAt, serverNow]);

    return remaining;
}
