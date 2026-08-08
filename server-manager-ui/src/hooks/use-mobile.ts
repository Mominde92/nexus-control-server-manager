import * as React from 'react'

const DEFAULT_MOBILE_BREAKPOINT = 768

/**
 * Track whether the viewport is narrower than `breakpoint` (in px).
 *
 * Uses `window.matchMedia` so it stays in sync with CSS breakpoints and does
 * not thrash on every resize event.
 */
export function useIsMobile(breakpoint: number = DEFAULT_MOBILE_BREAKPOINT) {
  const [isMobile, setIsMobile] = React.useState<boolean | undefined>(undefined)

  React.useEffect(() => {
    if (typeof window === 'undefined' || !window.matchMedia) {
      return
    }

    const mql = window.matchMedia(`(max-width: ${breakpoint - 1}px)`)

    const onChange = () => {
      setIsMobile(window.innerWidth < breakpoint)
    }

    onChange()
    mql.addEventListener('change', onChange)

    return () => {
      mql.removeEventListener('change', onChange)
    }
  }, [breakpoint])

  return !!isMobile
}

export { DEFAULT_MOBILE_BREAKPOINT }
