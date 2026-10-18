import { QueryClient } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import { createMemoryRouter } from 'react-router'
import { AppProviders } from '../AppProviders.tsx'
import { routes } from '../routes/routes.tsx'

export function renderApp(initialPath: string) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  const router = createMemoryRouter(routes, { initialEntries: [initialPath] })

  render(<AppProviders queryClient={queryClient} router={router} />)

  return { router, queryClient }
}
