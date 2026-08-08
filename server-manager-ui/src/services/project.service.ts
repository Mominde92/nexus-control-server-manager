import { createCrudService } from '@/services/crud.service'
import type { Project, ProjectPayload } from '@/types/models'

export const projectService = createCrudService<Project, ProjectPayload>('projects')

export default projectService
