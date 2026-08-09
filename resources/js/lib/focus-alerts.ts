/**
 * O aviso de fim de ciclo. Numa tela em que a aba passa a maior parte do
 * tempo em segundo plano, o fim do pomodoro precisa chegar por som e por
 * notificação — o visual sozinho não é visto.
 *
 * Tudo aqui é oportunista: sem AudioContext ou sem permissão de
 * notificação, a função simplesmente não faz nada em vez de quebrar.
 */
const CHIME_GAIN = 0.45;

/** Um contexto para o app inteiro, destravado num gesto e reusado.
 *
 * Criar o contexto na hora de tocar é o que fazia o aviso falhar em
 * silêncio: sem gesto do usuário no documento, o contexto nasce
 * `suspended`, `oscillator.start()` não lança nada e nenhum som sai. O
 * caso comum é justamente esse — recarregar a tela com uma sessão em
 * andamento e deixar o ciclo terminar sem clicar em nada. */
let audioContext: AudioContext | null = null;

export function announcePhaseEnd(title: string, body: string): void {
    playChime();
    notify(title, body);
}

/**
 * Prepara o áudio e a permissão de notificação. Só faz sentido chamar a
 * partir de um gesto do usuário (iniciar, retomar, avançar), que é quando
 * o navegador libera os dois.
 */
export function prepareAlerts(): void {
    unlockAudio();
    requestNotificationPermission();
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

/**
 * O usuário fechou a porta: a tela precisa dizer que o aviso vai chegar
 * só por som, em vez de deixar a pessoa contando com algo que nunca vem.
 */
export function notificationsBlocked(): boolean {
    return (
        typeof Notification !== 'undefined' &&
        Notification.permission === 'denied'
    );
}

/** Cria (uma vez) e retoma o contexto de áudio. Seguro de chamar sempre. */
export function unlockAudio(): void {
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
        audioContext ??= new AudioContextClass();

        if (audioContext.state === 'suspended') {
            void audioContext.resume().catch(() => {});
        }
    } catch {
        audioContext = null;
    }
}

function playChime(): void {
    unlockAudio();

    const context = audioContext;

    if (!context) {
        return;
    }

    // Suspenso na hora de tocar significa que nenhum gesto destravou o
    // áudio ainda. Vale tentar retomar: em parte dos navegadores o
    // `resume()` passa, e o custo de falhar é o mesmo silêncio de antes.
    if (context.state === 'suspended') {
        void context.resume().then(
            () => ring(context),
            () => {},
        );

        return;
    }

    ring(context);
}

/**
 * Dois toques em vez de um: um bipe isolado se confunde com qualquer
 * outro som do sistema, e o par sobe de tom, que é o que faz o aviso ser
 * lido como "acabou" e não como "chegou mensagem".
 */
function ring(context: AudioContext): void {
    try {
        tone(context, 660, context.currentTime, 0.5);
        tone(context, 880, context.currentTime + 0.22, 0.7);
    } catch {
        // Autoplay bloqueado ou contexto indisponível: o aviso visual e a
        // notificação continuam valendo.
    }
}

function tone(
    context: AudioContext,
    frequency: number,
    startAt: number,
    duration: number,
): void {
    const oscillator = context.createOscillator();
    const gain = context.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(0.0001, startAt);
    gain.gain.exponentialRampToValueAtTime(CHIME_GAIN, startAt + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

    oscillator.connect(gain);
    gain.connect(context.destination);
    oscillator.start(startAt);
    oscillator.stop(startAt + duration);
}

function notify(title: string, body: string): void {
    if (
        typeof Notification === 'undefined' ||
        Notification.permission !== 'granted'
    ) {
        return;
    }

    try {
        // A `tag` substitui o aviso anterior em vez de empilhar: quem
        // volta ao computador depois de horas quer ver o último ciclo,
        // não a fila de todos eles.
        new Notification(title, { body, tag: 'notter-phase' });
    } catch {
        // Alguns navegadores só permitem notificação via service worker.
    }
}
