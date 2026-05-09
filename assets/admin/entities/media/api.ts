import { useQuery } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'
import type { MediaAssetItem } from '../../types/api'

interface MediaResponse {
  assets: MediaAssetItem[]
}

export function useMediaAssetsQuery() {
  return useQuery({
    queryKey: ['admin', 'media', 'assets'],
    queryFn: async () => {
      const response = await apiRequest<MediaResponse>('/admin/api/media/assets')
      return response.assets
    },
  })
}
