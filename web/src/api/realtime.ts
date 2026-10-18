import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { http } from './client.ts'

type ChannelAuthorization = { auth: string; channel_data?: string; shared_secret?: string }

export function createEcho(key: string) {
  const isSecure = window.location.protocol === 'https:'
  const port = Number(window.location.port) || (isSecure ? 443 : 80)

  return new Echo({
    broadcaster: 'reverb',
    Pusher,
    key,
    wsHost: window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: isSecure,
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: (error: Error | null, data: ChannelAuthorization | null) => void) => {
        http
          .post<ChannelAuthorization>('/api/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
          .then((response) => callback(null, response.data))
          .catch((error: Error) => callback(error, null))
      },
    }),
  })
}
