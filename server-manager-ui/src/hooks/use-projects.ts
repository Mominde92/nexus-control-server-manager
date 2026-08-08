import { createCrudHooks } from '@/hooks/use-crud-queries'
import { projectService } from '@/services/project.service'
import type { Project, ProjectPayload } from '@/types/models'

const hooks = createCrudHooks<Project, ProjectPayload>('projects', projectService)

export const useProjects = hooks.useList
export const useProject = hooks.useDetail
export const useCreateProject = hooks.useCreate
export const useUpdateProject = hooks.useUpdate
export const useDeleteProject = hooks.useDelete
export const useRestoreProject = hooks.useRestore

/** Trigger the CSV export for the current filter set. */
export const exportProjects = projectService.exportCsv
