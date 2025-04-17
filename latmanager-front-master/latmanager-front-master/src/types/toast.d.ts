import '@/components/ui/toast'
import { ToastAction } from '@/components/ui/toast'
import { ReactElement } from 'react'

declare module '@/components/ui/toast' {
  interface ToastProps {
    variant?: 'default' | 'destructive' | 'success' | 'warning' | 'info'
  }

  type ToastActionElement = ReactElement<typeof ToastAction>
}
