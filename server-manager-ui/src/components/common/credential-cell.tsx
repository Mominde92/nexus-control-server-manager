import { useState } from 'react'
import { Check, Copy, Eye, EyeOff, LoaderCircle } from 'lucide-react'
import { toast } from 'sonner'
import apiClient, { unwrap } from '@/lib/api-client'
import type { ApiEnvelope } from '@/types/api'
import type { Credentials } from '@/types/models'

async function copy(value:string,label:string){await navigator.clipboard.writeText(value);toast.success(`${label} copied to clipboard.`)}

export function UsernameCell({value}:{value?:string|null}){
  const[copied,setCopied]=useState(false)
  if(!value)return <span className="text-xs text-slate-400">Not set</span>
  return <div className="flex min-w-36 items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 dark:border-slate-700 dark:bg-slate-800/60"><code className="max-w-36 truncate text-xs font-semibold">{value}</code><button title="Copy username" className="grid size-7 shrink-0 place-items-center rounded-md text-slate-400 transition hover:bg-white hover:text-indigo-600 dark:hover:bg-slate-700" onClick={async event=>{event.stopPropagation();await copy(value,'Username');setCopied(true);setTimeout(()=>setCopied(false),1200)}}>{copied?<Check className="size-3.5 text-emerald-500"/>:<Copy className="size-3.5"/>}</button></div>
}

export function PasswordCell({resource,id,hasPassword}:{resource:'hostings'|'domains';id:number;hasPassword?:boolean}){
  const[value,setValue]=useState<string|null>(null);const[visible,setVisible]=useState(false);const[loading,setLoading]=useState(false);const[copied,setCopied]=useState(false)
  if(!hasPassword)return <span className="text-xs text-slate-400">Not set</span>
  async function reveal(){if(value!==null){setVisible(v=>!v);return}setLoading(true);try{const credentials=unwrap(await apiClient.get<ApiEnvelope<Credentials>>(`/${resource}/${id}/credentials`));setValue(credentials.password??'');setVisible(true)}catch(cause){toast.error((cause as{message?:string}).message??'Unable to reveal password.')}finally{setLoading(false)}}
  return <div className="flex min-w-40 items-center justify-between gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800/60"><button title={visible?'Hide password':'Reveal password'} className="flex min-w-0 flex-1 items-center gap-2 text-left" onClick={event=>{event.stopPropagation();void reveal()}}>{loading?<LoaderCircle className="size-3.5 animate-spin text-indigo-500"/>:visible?<EyeOff className="size-3.5 text-indigo-500"/>:<Eye className="size-3.5 text-slate-400"/>}<code className="max-w-28 truncate text-xs font-bold tracking-wider">{visible?(value||'Empty'):'••••••••'}</code></button><button disabled={!value} title="Copy password" className="grid size-7 shrink-0 place-items-center rounded-md text-slate-400 transition hover:bg-white hover:text-indigo-600 disabled:opacity-30 dark:hover:bg-slate-700" onClick={async event=>{event.stopPropagation();if(!value)return;await copy(value,'Password');setCopied(true);setTimeout(()=>setCopied(false),1200)}}>{copied?<Check className="size-3.5 text-emerald-500"/>:<Copy className="size-3.5"/>}</button></div>
}
