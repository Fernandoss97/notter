/**
 * O aviso de fim de ciclo. Numa tela em que a aba passa a maior parte do
 * tempo em segundo plano, o fim do pomodoro precisa chegar por som e por
 * notificação — o visual sozinho não é visto.
 *
 * Tudo aqui é oportunista: sem AudioContext ou sem permissão de
 * notificação, a função simplesmente não faz nada em vez de quebrar.
 */
export function announcePhaseEnd(title: string, body: string): void {
    playChime();
    notify(title, body);
}

/**
 * Pede a permissão de notificação. Só faz sentido chamar a partir de um
 * gesto do usuário (iniciar/retomar), que é quando o navegador aceita.
 */
export function requestNotificationPermission(): void {
    if (
        typeof Notification === 'undefined' ||
        Notification.permission !== 'default'
    ) {
        return;
    }

    void Notification.requestPermission();
}

function playChime(): void {
    const AudioContextClass =
        typeof window !== 'undefined'
            ? (window.AudioContext ??
              (
                  window as unknown as {
                      webkitAudioContext?: typeof AudioContext;
                  }
              ).webkitAudioContext)
            : undefined;

    if (!AudioContextClass) {
        return;
    }

    try {
        const context = new AudioContextClass();
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = 660;
        gain.gain.setValueAtTime(0.0001, context.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.2, context.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(
            0.0001,
            context.currentTime + 0.9,
        );

        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start();
        oscillator.stop(context.currentTime + 0.9);
        oscillator.onended = () => void context.close();
    } catch {
        // Autoplay bloqueado ou contexto indisponível: o aviso visual e a
        // notificação continuam valendo.
    }
}

function notify(title: string, body: string): void {
    if (
        typeof Notification === 'undefined' ||
        Notification.permission !== 'granted'
    ) {
        return;
    }

    try {
        new Notification(title, { body });
    } catch {
        // Alguns navegadores só permitem notificação via service worker.
    }
}
