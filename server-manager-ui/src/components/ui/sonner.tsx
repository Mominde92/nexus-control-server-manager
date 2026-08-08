import * as React from 'react'
import { Toaster as Sonner, type ToasterProps } from 'sonner'

/**
 * Resolve the active theme by observing the `.dark` class on <html>.
 * Avoids a next-themes dependency — this is a plain Vite SPA.
 */
function useResolvedTheme(): 'light' | 'dark' {
  const [theme, setTheme] = React.useState<'light' | 'dark'>(() =>
    typeof document !== 'undefined' &&
    document.documentElement.classList.contains('dark')
      ? 'dark'
      : 'light',
  )

  React.useEffect(() => {
    const root = document.documentElement

    const sync = () => {
      setTheme(root.classList.contains('dark') ? 'dark' : 'light')
    }

    sync()

    const observer = new MutationObserver(sync)
    observer.observe(root, { attributes: true, attributeFilter: ['class'] })

    return () => {
      observer.disconnect()
    }
  }, [])

  return theme
}

function Toaster({ ...props }: ToasterProps) {
  const theme = useResolvedTheme()

  return (
    <Sonner
      theme={theme}
      className="toaster group"
      position="top-right"
      style={
        {
          '--normal-bg': 'var(--popover)',
          '--normal-text': 'var(--popover-foreground)',
          '--normal-border': 'var(--border)',
          '--success-bg': 'var(--success)',
          '--success-text': 'var(--success-foreground)',
          '--error-bg': 'var(--destructive)',
          '--error-text': 'var(--destructive-foreground)',
          '--warning-bg': 'var(--warning)',
          '--warning-text': 'var(--warning-foreground)',
          '--info-bg': 'var(--info)',
          '--info-text': 'var(--info-foreground)',
        } as React.CSSProperties
      }
      {...props}
    />
  )
}

export { Toaster }
