import { QueryClientProvider } from '@tanstack/react-query'
import { createBrowserRouter, RouterProvider } from 'react-router'
import { ToastProvider } from './components/ui/ToastProvider.tsx'
import { AuthProvider } from './features/auth/AuthProvider.tsx'
import { queryClient } from './lib/queryClient.ts'
import { routes } from './routes/routes.tsx'

const router = createBrowserRouter(routes)

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <ToastProvider>
          <RouterProvider router={router} />
        </ToastProvider>
      </AuthProvider>
    </QueryClientProvider>
  )
}

export default App
