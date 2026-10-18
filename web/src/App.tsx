import { createBrowserRouter } from 'react-router'
import { AppProviders } from './AppProviders.tsx'
import { queryClient } from './lib/queryClient.ts'
import { routes } from './routes/routes.tsx'

const router = createBrowserRouter(routes)

function App() {
  return <AppProviders queryClient={queryClient} router={router} />
}

export default App
