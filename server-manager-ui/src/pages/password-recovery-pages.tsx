import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { KeyRound } from 'lucide-react'
import { authService } from '@/services/auth.service'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

function Shell({title,description,children}:{title:string;description:string;children:React.ReactNode}) {
  return <div className="grid min-h-screen place-items-center bg-muted/40 p-4"><div className="w-full max-w-md rounded-2xl border bg-card p-8 shadow-xl"><div className="mb-7 flex items-center gap-3"><div className="grid size-11 place-items-center rounded-xl bg-primary text-primary-foreground"><KeyRound/></div><div><h1 className="text-xl font-bold">{title}</h1><p className="text-sm text-muted-foreground">{description}</p></div></div>{children}<Link className="mt-5 block text-center text-sm text-primary hover:underline" to="/login">Back to sign in</Link></div></div>
}

export function ForgotPasswordPage(){
  const[email,setEmail]=useState('');const[message,setMessage]=useState('');const[busy,setBusy]=useState(false)
  async function submit(event:React.FormEvent){event.preventDefault();setBusy(true);try{setMessage(await authService.forgotPassword(email))}catch(cause){setMessage((cause as {message?:string}).message??'Request failed.')}finally{setBusy(false)}}
  return <Shell title="Reset your password" description="We'll email you a secure reset link."><form onSubmit={submit}><Label htmlFor="email">Email address</Label><Input className="mt-1.5" id="email" type="email" required value={email} onChange={event=>setEmail(event.target.value)}/>{message&&<p className="mt-4 rounded-lg bg-muted p-3 text-sm">{message}</p>}<Button className="mt-5 w-full" disabled={busy}>{busy?'Sending…':'Send reset link'}</Button></form></Shell>
}

export function ResetPasswordPage(){
  const[params]=useSearchParams();const[email,setEmail]=useState(params.get('email')??'');const[password,setPassword]=useState('');const[confirmation,setConfirmation]=useState('');const[message,setMessage]=useState('');const[busy,setBusy]=useState(false)
  async function submit(event:React.FormEvent){event.preventDefault();setBusy(true);try{setMessage(await authService.resetPassword({token:params.get('token')??'',email,password,password_confirmation:confirmation}))}catch(cause){setMessage((cause as {message?:string}).message??'Reset failed.')}finally{setBusy(false)}}
  return <Shell title="Choose a new password" description="Use a strong password you haven't used before."><form className="space-y-4" onSubmit={submit}><div><Label>Email</Label><Input className="mt-1.5" type="email" required value={email} onChange={event=>setEmail(event.target.value)}/></div><div><Label>New password</Label><Input className="mt-1.5" type="password" required value={password} onChange={event=>setPassword(event.target.value)}/></div><div><Label>Confirm password</Label><Input className="mt-1.5" type="password" required value={confirmation} onChange={event=>setConfirmation(event.target.value)}/></div>{message&&<p className="rounded-lg bg-muted p-3 text-sm">{message}</p>}<Button className="w-full" disabled={busy}>{busy?'Updating…':'Update password'}</Button></form></Shell>
}
