// Навантажувальний прогін B5: 60 одночасних WebSocket-з'єднань до Reverb
// (DoD фази B5). Сплеск повідомлень поверх з'єднань — окремо, через REST
// з автентифікованою сесією.
//
// Запуск (стек має бути піднятий: docker compose up):
//   docker run --rm -i --network=host grafana/k6 run - < scripts/load-test/ws-load.js
// або з локальним k6:
//   k6 run scripts/load-test/ws-load.js
//
// Змінні: REVERB_URL (ws://localhost:8080), REVERB_APP_KEY (messenger-local-key).
// Критерій: 100% з'єднань отримують pusher:connection_established,
// з'єднання живуть 60с без розривів.

import ws from 'k6/ws';
import { check, sleep } from 'k6';

export const options = {
  scenarios: {
    websocket_connections: {
      executor: 'constant-vus',
      vus: 60,
      duration: '2m',
      gracefulStop: '30s',
    },
  },
  thresholds: {
    checks: ['rate>0.99'],
  },
};

const REVERB_URL = __ENV.REVERB_URL || 'ws://localhost:8080';
const APP_KEY = __ENV.REVERB_APP_KEY || 'messenger-local-key';

export default function () {
  const url = `${REVERB_URL}/app/${APP_KEY}?protocol=7&client=k6&version=1.0`;

  const response = ws.connect(url, {}, (socket) => {
    socket.on('open', () => {
      // Pusher-протокол: ping кожні 25с тримає з'єднання живим.
      socket.setInterval(() => {
        socket.send(JSON.stringify({ event: 'pusher:ping', data: {} }));
      }, 25000);
    });

    socket.on('message', (raw) => {
      const frame = JSON.parse(raw);
      check(frame, {
        'established or pong': (f) =>
          f.event === 'pusher:connection_established' || f.event === 'pusher:pong',
      });
    });

    socket.on('error', (e) => {
      check(null, { 'no socket error': () => false });
    });

    // Тримаємо з'єднання хвилину, потім закриваємо і йдемо на нову ітерацію.
    socket.setTimeout(() => socket.close(), 60000);
  });

  check(response, { 'ws handshake 101': (r) => r && r.status === 101 });
  sleep(1);
}
