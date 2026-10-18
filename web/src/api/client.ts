import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios'

type RetriableRequest = InternalAxiosRequestConfig & { csrfRetried?: boolean }

let unauthorizedHandler: () => void = () => {}

export const http = axios.create({
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export function fetchCsrfCookie() {
  return http.get('/sanctum/csrf-cookie')
}

export function onUnauthorized(handler: () => void) {
  unauthorizedHandler = handler
}

http.interceptors.response.use(undefined, async (error: AxiosError) => {
  const request = error.config as RetriableRequest | undefined
  const status = error.response?.status

  if (status === 419 && request && !request.csrfRetried) {
    request.csrfRetried = true
    await fetchCsrfCookie()

    return http.request(request)
  }

  if (status === 401 && request?.url !== '/api/user') {
    unauthorizedHandler()
  }

  return Promise.reject(error)
})
