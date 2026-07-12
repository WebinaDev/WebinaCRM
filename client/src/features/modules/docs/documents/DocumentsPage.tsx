import { TableListSkeleton } from "@/components/TableListSkeleton"
import { useCallback, useEffect, useRef, useState } from "react"
import { useSearchParams } from "react-router-dom"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  deleteDocument,
  getDocumentDownloadUrl,
  getDocuments,
  uploadDocument,
  type DocumentItem,
} from "@/api/documents"
import { getAjaxMessage } from "@/api/client"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { FileText, Loader2, Trash2, Upload } from "lucide-react"

export function DocumentsPage() {
  const { t, isRtl, formatDateTime, formatNumber } = useLocale()
  const { layoutProps, setError } = useCrmFeedback()
  const [searchParams] = useSearchParams()
  const entityType = searchParams.get("entity_type") ?? undefined
  const entityId = searchParams.get("entity_id") ? Number(searchParams.get("entity_id")) : undefined

  const [documents, setDocuments] = useState<DocumentItem[]>([])
  const [loading, setLoading] = useState(true)
  const [uploading, setUploading] = useState(false)
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleting, setDeleting] = useState(false)
  const fileRef = useRef<HTMLInputElement>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await getDocuments({
        entity_type: entityType,
        entity_id: entityId,
        limit: 100,
      })
      if (res.success && res.data?.documents) {
        setDocuments(res.data.documents)
      } else {
        setError(getAjaxMessage(res) ?? t("pages.documents.loadError"))
        setDocuments([])
      }
    } catch {
      setError(t("pages.documents.loadError"))
      setDocuments([])
    } finally {
      setLoading(false)
    }
  }, [entityType, entityId, t, setError])

  useEffect(() => {
    void load()
  }, [load])

  const handleUpload = async (file: File) => {
    setUploading(true)
    setError(null)
    const fd = new FormData()
    fd.append("file", file)
    if (entityType) fd.append("entity_type", entityType)
    if (entityId) fd.append("entity_id", String(entityId))
    try {
      const res = await uploadDocument(fd)
      if (res.success) {
        await load()
      } else {
        setError(getAjaxMessage(res) ?? t("pages.documents.loadError"))
      }
    } catch {
      setError(t("pages.documents.loadError"))
    } finally {
      setUploading(false)
      if (fileRef.current) fileRef.current.value = ""
    }
  }

  const confirmDelete = async () => {
    if (deleteId == null) return
    setDeleting(true)
    const res = await deleteDocument(deleteId)
    setDeleting(false)
    if (res.success) {
      setDocuments((prev) => prev.filter((d) => d.id !== deleteId))
      setDeleteId(null)
    } else {
      setError(getAjaxMessage(res) ?? t("pages.documents.loadError"))
    }
  }

  const handleDownload = (id: number) => {
    const url = getDocumentDownloadUrl(id)
    if (url) window.open(url, "_blank")
  }

  const uploadButton = (
    <>
      <input
        ref={fileRef}
        type="file"
        className="hidden"
        onChange={(e) => {
          const f = e.target.files?.[0]
          if (f) void handleUpload(f)
        }}
      />
      <Button
        type="button"
        disabled={uploading}
        onClick={() => fileRef.current?.click()}
      >
        {uploading ? (
          <Loader2 className="h-4 w-4 animate-spin me-2" />
        ) : (
          <Upload className="h-4 w-4 me-2" />
        )}
        {t("pages.documents.upload")}
      </Button>
    </>
  )

  return (
    <CrmPageLayout
      title={t("pages.documents.title")}
      actions={uploadButton}
      {...layoutProps}
    >
      {(entityType || entityId != null) && (
        <div className="flex flex-wrap gap-2">
          {entityType && <Badge variant="secondary">{entityType}</Badge>}
          {entityId != null && <Badge variant="outline">#{entityId}</Badge>}
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="text-base flex items-center gap-2">
            <FileText className="h-4 w-4" />
            {t("pages.documents.title")}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {loading ? (

            <TableListSkeleton rows={8} columns={5} />

          ) : documents.length === 0 ? (
            <PmEmptyState icon={FileText} message={t("pages.documents.empty")} />
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t("pages.documents.col_name")}</TableHead>
                  <TableHead>{t("pages.documents.col_file")}</TableHead>
                  <TableHead>{t("pages.documents.col_size")}</TableHead>
                  <TableHead>{t("pages.documents.col_date")}</TableHead>
                  <TableHead className="text-end">{t("common.actions")}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {documents.map((doc) => (
                  <TableRow key={doc.id}>
                    <TableCell className="font-medium">{doc.title || doc.file_name}</TableCell>
                    <TableCell className="text-muted-foreground text-sm">{doc.file_name}</TableCell>
                    <TableCell>
                      {doc.file_size != null
                        ? `${formatNumber(Math.round(doc.file_size / 1024))} KB`
                        : "—"}
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {doc.uploaded_at ? formatDateTime(doc.uploaded_at) : "—"}
                    </TableCell>
                    <TableCell className="text-end space-x-2 space-x-reverse">
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => handleDownload(doc.id)}
                      >
                        {t("pages.documents.download")}
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="text-destructive"
                        onClick={() => setDeleteId(doc.id)}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <PmConfirmDialog
        open={deleteId != null}
        onOpenChange={(open) => !open && setDeleteId(null)}
        title={t("common.delete")}
        description={t("pages.documents.delete")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDelete}
        loading={deleting}
        isRtl={isRtl}
      />
    </CrmPageLayout>
  )
}
