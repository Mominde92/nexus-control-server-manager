import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { QueryClientProvider } from '@tanstack/react-query'
import './index.css'
import App from './App.tsx'
import { queryClient } from '@/lib/query-client'
import { AuthProvider } from '@/contexts/auth-context'
import { Toaster } from '@/components/ui/sonner'
import { AppErrorBoundary } from '@/components/common/app-error-boundary'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <AppErrorBoundary><QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider><App /></AuthProvider>
        <Toaster richColors />
      </BrowserRouter>
    </QueryClientProvider></AppErrorBoundary>
  </StrictMode>,
)
