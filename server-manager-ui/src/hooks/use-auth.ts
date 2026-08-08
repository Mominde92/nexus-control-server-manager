import * as React from 'react'

import { AuthContext, type AuthContextValue } from '@/contexts/auth-context'

/** Access the current session. Throws if used outside `<AuthProvider>`. */
export function useAuth(): AuthContextValue {
  const context = React.useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used within an <AuthProvider>')
  }

  return context
}

export default useAuth
