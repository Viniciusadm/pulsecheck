import { AxiosError, AxiosHeaders, type AxiosResponse } from 'axios'

export function axiosError(status: number, data: unknown = {}): AxiosError {
  const config = { headers: new AxiosHeaders() }
  const response = { status, data, statusText: '', headers: {}, config } as AxiosResponse

  return new AxiosError(`Request failed with status code ${status}`, 'ERR_BAD_RESPONSE', config, null, response)
}
