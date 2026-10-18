import { QueryClientProvider, type QueryClient } from '@tanstack/react-query'
import { RouterProvider, type createBrowserRouter } from 'react-router'
import { ToastProvider } from './components/ui/ToastProvider.tsx'
import { AuthProvider } from './features/auth/AuthProvider.tsx'
import { ConfigProvider } from './features/config/ConfigProvider.tsx'

type AppProvidersProps = {
  queryClient: QueryClient
  router: ReturnType<typeof createBrowserRouter>
}

export function AppProviders({ queryClient, router }: AppProvidersProps) {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <ToastProvider>
          <ConfigProvider>
            <RouterProvider router={router} />
          </ConfigProvider>
        </ToastProvider>
      </AuthProvider>
    </QueryClientProvider>
  )
}
