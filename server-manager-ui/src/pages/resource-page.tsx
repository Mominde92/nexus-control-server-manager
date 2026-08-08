import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { Download, Plus, Search } from 'lucide-react'
import apiClient, { unwrapList } from '@/lib/api-client'
import { useListParams } from '@/hooks/use-list-params'
import { DataTable, type DataTableColumn } from '@/components/common/data-table'
import { RowActions } from '@/components/common/row-actions'
import { PasswordCell, UsernameCell } from '@/components/common/credential-cell'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { PaginatedResult } from '@/types/api'

type Row={id:number;name?:string;title?:string;description?:string;email?:string;username?:string|null;has_password?:boolean;role?:{value:string;label:string}|null;status?:{label:string};type?:{label:string};expiry_date?:string;created_at?:string}
const titles:Record<string,string>={hostings:'Hosting Management',domains:'Domain Management',providers:'Providers',clients:'Clients',projects:'Projects',users:'Users','activity-logs':'Activity Logs'}
const editable=['hostings','domains','providers','clients','projects','users']

export function ResourcePage({resource}:{resource:string}){
  const list=useListParams({defaultSort:'created_at'});const navigate=useNavigate();const canEdit=editable.includes(resource)
  const query=useQuery<PaginatedResult<Row>>({queryKey:[resource,list.params],queryFn:async()=>unwrapList(await apiClient.get(`/${resource}`,{params:list.params}))})
  const standardColumns:DataTableColumn<Row>[]=[
    {key:'name',header:'Name',sortable:true,render:row=><div><p className="font-medium">{row.name??row.title??row.description??`#${row.id}`}</p>{row.email&&<p className="text-xs text-muted-foreground">{row.email}</p>}</div>},
    ...(['hostings','domains'].includes(resource)?[{key:'username',header:'Username',render:(row:Row)=><UsernameCell value={row.username}/>},{key:'password',header:'Password',render:(row:Row)=><PasswordCell resource={resource as 'hostings'|'domains'} id={row.id} hasPassword={row.has_password}/>}]:[]),
    {key:'type',header:'Type',render:row=>row.type?.label??'—'},
    {key:'status',header:'Status',render:row=>row.status?<Badge variant="secondary">{row.status.label}</Badge>:'—'},
    {key:'expiry_date',header:'Expiry',sortable:true,render:row=>row.expiry_date??'—'},
    {key:'created_at',header:'Created',sortable:true,render:row=>row.created_at?new Date(row.created_at).toLocaleDateString():'—'},
  ]
  const userColumns:DataTableColumn<Row>[]=[
    {key:'name',header:'User',sortable:true,render:row=><div><p className="font-semibold">{row.name}</p><p className="text-xs text-muted-foreground">{row.email}</p></div>},
    {key:'role',header:'Role',render:row=><Badge variant="outline" className="capitalize">{row.role?.label??'No role'}</Badge>},
    {key:'status',header:'Status',render:row=>row.status?<Badge variant="secondary">{row.status.label}</Badge>:'—'},
  ]
  const hostingColumns:DataTableColumn<Row>[]=[
    {key:'name',header:'Hosting',sortable:true,render:row=><p className="font-semibold">{row.title??row.name??`#${row.id}`}</p>},
    {key:'username',header:'Username',render:row=><UsernameCell value={row.username}/>},
    {key:'password',header:'Password',render:row=><PasswordCell resource="hostings" id={row.id} hasPassword={row.has_password}/>},
    {key:'status',header:'Status',render:row=>row.status?<Badge variant="secondary">{row.status.label}</Badge>:'—'},
  ]
  const columns=resource==='users'?userColumns:resource==='hostings'?hostingColumns:standardColumns
  function exportTable(){
    const rows=query.data?.data??[]
    const headers=resource==='hostings'?['Hosting','Username','Password','Status']:resource==='users'?['User','Email','Role','Status']:columns.map(column=>String(column.header))
    const values=rows.map(row=>resource==='hostings'?[row.title??row.name??'',row.username??'Not set',row.has_password?'••••••••':'Not set',row.status?.label??'']:resource==='users'?[row.name??'',row.email??'',row.role?.label??'No role',row.status?.label??'']:[row.name??row.title??row.description??'',...(['domains'].includes(resource)?[row.username??'Not set',row.has_password?'••••••••':'Not set']:[]),row.type?.label??'',row.status?.label??'',row.expiry_date??'',row.created_at?new Date(row.created_at).toLocaleDateString():''])
    const escape=(value:unknown)=>`"${String(value??'').replaceAll('"','""')}"`
    const csv='\uFEFF'+[headers,...values].map(row=>row.map(escape).join(',')).join('\r\n')
    const url=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));const anchor=document.createElement('a');anchor.href=url;anchor.download=`${resource}-table.csv`;anchor.click();URL.revokeObjectURL(url)
  }
  return <div className="space-y-5"><div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-xs font-semibold tracking-wider text-indigo-600 uppercase">Management</p><h1 className="mt-1 text-2xl font-bold">{titles[resource]??resource}</h1><p className="text-sm text-muted-foreground">Search, review and manage all records.</p></div><div className="flex gap-2">{resource!=='users'&&<Button variant="outline" onClick={exportTable}><Download/>Export Excel</Button>}{canEdit&&<Button onClick={()=>navigate(`/${resource}/new`)}><Plus/>Add new</Button>}</div></div><div className="relative max-w-md"><Search className="absolute top-2.5 left-3 size-4 text-muted-foreground"/><Input className="pl-9" placeholder={`Search ${titles[resource]?.toLowerCase()}…`} value={list.searchInput} onChange={event=>list.setSearch(event.target.value)}/></div>{query.error&&<div className="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-destructive">{query.error.message}</div>}<DataTable columns={columns} rows={query.data?.data} meta={query.data?.meta} isLoading={query.isLoading} sort={list.sort} direction={list.direction} onSort={list.setSort} onPageChange={list.setPage} onPerPageChange={list.setPerPage} onRowClick={canEdit?row=>navigate(`/${resource}/${row.id}`):undefined} rowActions={canEdit?row=><RowActions onView={()=>navigate(`/${resource}/${row.id}`)} onEdit={()=>navigate(`/${resource}/${row.id}/edit`)} onDelete={()=>navigate(`/${resource}/${row.id}/delete`)}/>:undefined}/></div>
}
