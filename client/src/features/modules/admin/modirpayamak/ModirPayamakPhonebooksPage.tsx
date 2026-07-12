import { useCallback, useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { TableListSkeleton } from "@/components/TableListSkeleton"
import {
  edgeAddPhonebookContact,
  edgeDeletePhonebook,
  edgeField,
  edgeListPhonebookContacts,
  edgeListPhonebooks,
  edgeSavePhonebook,
  type EdgeRow,
} from "@/api/modirpayamak-edge"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import { PmEmptyState } from "@/features/shared/pm/PmEmptyState"
import { useModirPayamakEdge } from "./hooks/useModirPayamakEdge"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"
import { ModirPayamakJsonDebug } from "./components/ModirPayamakJsonDebug"
import { ModirPayamakNotConfigured } from "./components/ModirPayamakNotConfigured"
import { useModirPayamakConfigured } from "./hooks/useModirPayamakConfigured"
import { BookUser, Plus, Trash2 } from "lucide-react"
import { cn } from "@/lib/utils"

function phonebookId(row: EdgeRow): number {
  return Number(edgeField(row, "id", "phonebook_id")) || 0
}

export function ModirPayamakPhonebooksPage() {
  const { t, isRtl } = useLocale()
  const { layoutProps, applyResponse } = useCrmFeedback()
  const { configured } = useModirPayamakConfigured()
  const loader = useCallback(() => edgeListPhonebooks(), [])
  const { items: phonebooks, raw, loading, reload } = useModirPayamakEdge(loader)

  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [contacts, setContacts] = useState<EdgeRow[]>([])
  const [contactsLoading, setContactsLoading] = useState(false)

  const [bookDialogOpen, setBookDialogOpen] = useState(false)
  const [bookName, setBookName] = useState("")
  const [editBookId, setEditBookId] = useState<number | null>(null)
  const [savingBook, setSavingBook] = useState(false)
  const [deleteBookId, setDeleteBookId] = useState<number | null>(null)
  const [deletingBook, setDeletingBook] = useState(false)

  const [contactPhone, setContactPhone] = useState("")
  const [contactName, setContactName] = useState("")
  const [addingContact, setAddingContact] = useState(false)

  const loadContacts = useCallback(async (id: number) => {
    setContactsLoading(true)
    const res = await edgeListPhonebookContacts(id)
    setContacts(res.items)
    setContactsLoading(false)
  }, [])

  const selectPhonebook = (row: EdgeRow) => {
    const id = phonebookId(row)
    if (!id) return
    setSelectedId(id)
    void loadContacts(id)
  }

  const saveBook = async () => {
    setSavingBook(true)
    const res = await edgeSavePhonebook(editBookId, { name: bookName.trim(), title: bookName.trim() })
    setSavingBook(false)
    if (applyResponse({ success: res.ok, message: res.message }, { successMessage: t("common.saved") })) {
      setBookDialogOpen(false)
      setBookName("")
      setEditBookId(null)
      void reload()
    }
  }

  const confirmDeleteBook = async () => {
    if (!deleteBookId) return
    setDeletingBook(true)
    const res = await edgeDeletePhonebook(deleteBookId)
    setDeletingBook(false)
    if (applyResponse({ success: res.ok, message: res.message }, { successMessage: t("common.deleted") })) {
      setDeleteBookId(null)
      if (selectedId === deleteBookId) {
        setSelectedId(null)
        setContacts([])
      }
      void reload()
    }
  }

  const addContact = async () => {
    if (!selectedId || !contactPhone.trim()) return
    setAddingContact(true)
    const res = await edgeAddPhonebookContact(selectedId, {
      number: contactPhone.trim(),
      phone: contactPhone.trim(),
      name: contactName.trim(),
    })
    setAddingContact(false)
    if (applyResponse({ success: res.ok, message: res.message }, { successMessage: t("common.saved") })) {
      setContactPhone("")
      setContactName("")
      void loadContacts(selectedId)
    }
  }

  return (
    <CrmPageLayout title={t("pages.modirpayamak.phonebooksTitle")} {...layoutProps}>
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.phonebooksTitle")} />
      <ModirPayamakNotConfigured configured={configured ?? true} />

      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-2">
            <CardTitle className="text-base">{t("pages.modirpayamak.phonebooksTitle")}</CardTitle>
            <Button
              size="sm"
              variant="outline"
              onClick={() => {
                setEditBookId(null)
                setBookName("")
                setBookDialogOpen(true)
              }}
            >
              <Plus className="me-2 h-4 w-4" />
              {t("pages.modirpayamak.addPhonebook")}
            </Button>
          </CardHeader>
          <CardContent className="space-y-2">
            {loading ? (
              <TableListSkeleton rows={5} columns={2} withHeader={false} />
            ) : phonebooks.length === 0 ? (
              <PmEmptyState icon={BookUser} message={t("pages.modirpayamak.phonebooksEmpty")} />
            ) : (
              phonebooks.map((row, i) => {
                const id = phonebookId(row)
                const name = edgeField(row, "name", "title")
                return (
                  <div
                    key={id || i}
                    className={cn(
                      "flex items-center justify-between rounded-lg border p-3 cursor-pointer transition-colors",
                      selectedId === id ? "border-primary bg-primary/5" : "hover:bg-muted/50",
                    )}
                    onClick={() => selectPhonebook(row)}
                  >
                    <span className="font-medium">{name}</span>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="text-destructive"
                      onClick={(e) => {
                        e.stopPropagation()
                        setDeleteBookId(id)
                      }}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                )
              })
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">{t("pages.modirpayamak.contacts")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {!selectedId ? (
              <p className="text-sm text-muted-foreground py-8 text-center">{t("pages.modirpayamak.selectPhonebook")}</p>
            ) : (
              <>
                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label>{t("pages.modirpayamak.colRecipient")}</Label>
                    <Input dir="ltr" value={contactPhone} onChange={(e) => setContactPhone(e.target.value)} />
                  </div>
                  <div className="space-y-2">
                    <Label>{t("pages.modirpayamak.contactName")}</Label>
                    <Input value={contactName} onChange={(e) => setContactName(e.target.value)} />
                  </div>
                  <Button type="button" className="sm:col-span-2" disabled={addingContact} onClick={() => void addContact()}>
                    {t("pages.modirpayamak.addContact")}
                  </Button>
                </div>
                {contactsLoading ? (
                  <TableListSkeleton rows={6} columns={2} />
                ) : contacts.length === 0 ? (
                  <PmEmptyState message={t("pages.modirpayamak.contactsEmpty")} />
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>{t("pages.modirpayamak.contactName")}</TableHead>
                        <TableHead>{t("pages.modirpayamak.colRecipient")}</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {contacts.map((row, i) => (
                        <TableRow key={i}>
                          <TableCell>{edgeField(row, "name", "title")}</TableCell>
                          <TableCell dir="ltr" className="font-mono">
                            {edgeField(row, "number", "phone", "mobile")}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </>
            )}
          </CardContent>
        </Card>
      </div>

      <Dialog open={bookDialogOpen} onOpenChange={setBookDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("pages.modirpayamak.addPhonebook")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label>{t("pages.modirpayamak.name")}</Label>
            <Input value={bookName} onChange={(e) => setBookName(e.target.value)} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setBookDialogOpen(false)}>{t("common.cancel")}</Button>
            <Button disabled={savingBook} onClick={() => void saveBook()}>{t("common.save")}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PmConfirmDialog
        open={deleteBookId != null}
        onOpenChange={(open) => !open && setDeleteBookId(null)}
        title={t("common.delete")}
        description={t("pages.modirpayamak.confirm.deletePhonebook")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDeleteBook}
        loading={deletingBook}
        isRtl={isRtl}
      />

      <ModirPayamakJsonDebug data={raw} />
    </CrmPageLayout>
  )
}
