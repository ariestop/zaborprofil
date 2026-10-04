import type { SeoAuditIssue } from '../../../entities/seo/model'

export function countIssues(issues: SeoAuditIssue[]): Record<SeoAuditIssue['severity'], number> {
  const counts: Record<SeoAuditIssue['severity'], number> = { P0: 0, P1: 0, P2: 0 }
  for (const issue of issues) {
    counts[issue.severity] += 1
  }

  return counts
}

export function severityTone(severity: SeoAuditIssue['severity']): 'warning' | 'neutral' {
  return severity === 'P2' ? 'neutral' : 'warning'
}
