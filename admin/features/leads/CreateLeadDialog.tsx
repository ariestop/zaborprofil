import { useState, type FormEvent } from 'react'
import { useLeadCreateMutation } from '../../entities/lead/api'
import { Button, Dialog, Input, Textarea } from '../../shared/ui'

interface CreateLeadDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onCreated: (leadId: string, name: string) => void
}

/** Заявка, которую менеджер заводит сам, например после телефонного звонка. */
export function CreateLeadDialog({ open, onOpenChange, onCreated }: CreateLeadDialogProps) {
  const mutation = useLeadCreateMutation()
  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [message, setMessage] = useState('')
  const [error, setError] = useState<string | null>(null)

  const reset = () => {
    setName('')
    setPhone('')
    setEmail('')
    setMessage('')
    setError(null)
  }

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    setError(null)
    try {
      const lead = await mutation.mutateAsync({ name, phone, email, message })
      reset()
      onCreated(lead.id, lead.name)
    } catch {
      setError('Не удалось создать заявку. Проверьте имя и телефон: в телефоне должно быть не меньше 6 цифр.')
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          reset()
        }
        onOpenChange(next)
      }}
      title="Заявка после звонка"
      description="Заведите обращение, которое пришло не через сайт, чтобы оно попало в общий список и историю."
    >
      <form className="grid gap-3" onSubmit={(event) => void submit(event)}>
        <label className="grid gap-1 text-sm font-medium">
          Имя клиента
          <Input required maxLength={180} autoComplete="off" value={name} onChange={(event) => setName(event.target.value)} />
        </label>
        <label className="grid gap-1 text-sm font-medium">
          Телефон
          <Input required maxLength={40} type="tel" autoComplete="off" value={phone} onChange={(event) => setPhone(event.target.value)} />
        </label>
        <label className="grid gap-1 text-sm font-medium">
          Email <span className="font-normal text-slate-500">(необязательно)</span>
          <Input type="email" maxLength={180} autoComplete="off" value={email} onChange={(event) => setEmail(event.target.value)} />
        </label>
        <label className="grid gap-1 text-sm font-medium">
          Что нужно клиенту
          <Textarea maxLength={2000} placeholder="Например: забор из профлиста 1,8 м, около 120 м" value={message} onChange={(event) => setMessage(event.target.value)} />
        </label>
        {error !== null ? <p role="alert" className="text-sm text-red-700 dark:text-red-400">{error}</p> : null}
        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Отмена</Button>
          <Button type="submit" disabled={mutation.isPending || name.trim() === '' || phone.trim() === ''}>Создать заявку</Button>
        </div>
      </form>
    </Dialog>
  )
}
