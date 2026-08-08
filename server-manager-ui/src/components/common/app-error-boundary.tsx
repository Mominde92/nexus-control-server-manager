import { Component, type ErrorInfo, type ReactNode } from 'react'
import { Button } from '@/components/ui/button'

export class AppErrorBoundary extends Component<{children:ReactNode},{failed:boolean}> {
  state={failed:false}
  static getDerivedStateFromError(){return{failed:true}}
  componentDidCatch(error:Error,info:ErrorInfo){console.error('Application render error',error,info)}
  render(){if(this.state.failed)return <div className="grid min-h-screen place-items-center bg-muted/30 p-6"><div className="max-w-md rounded-2xl border bg-card p-8 text-center shadow-lg"><h1 className="text-xl font-bold">The page could not be displayed</h1><p className="mt-2 text-sm text-muted-foreground">Refresh the page to load the latest application state.</p><Button className="mt-5" onClick={()=>window.location.reload()}>Reload page</Button></div></div>;return this.props.children}
}
