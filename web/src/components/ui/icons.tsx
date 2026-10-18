import type { SVGProps } from 'react'

type IconProps = SVGProps<SVGSVGElement>

function Icon({ children, ...props }: IconProps) {
  return (
    <svg
      viewBox="0 0 20 20"
      width="1em"
      height="1em"
      fill="none"
      stroke="currentColor"
      strokeWidth={2}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
      {...props}
    >
      {children}
    </svg>
  )
}

export function PulseIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <path d="M1.5 10.5h4l2-5 3.5 9 2.5-6 1.5 2h3.5" />
    </Icon>
  )
}

export function UpIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <circle cx="10" cy="10" r="7.5" />
      <path d="m6.75 10.25 2.25 2.25 4.25-4.75" />
    </Icon>
  )
}

export function DownIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <circle cx="10" cy="10" r="7.5" />
      <path d="m7.5 7.5 5 5m0-5-5 5" />
    </Icon>
  )
}

export function PausedIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <circle cx="10" cy="10" r="7.5" />
      <path d="M8.25 7.25v5.5m3.5-5.5v5.5" />
    </Icon>
  )
}

export function PendingIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <circle cx="10" cy="10" r="7.5" />
      <path d="M10 6.25V10l2.5 1.5" />
    </Icon>
  )
}

export function DegradedIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <circle cx="10" cy="10" r="7.5" />
      <path d="M10 6.5v4m0 3h.01" />
    </Icon>
  )
}

export function MenuIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <path d="M3 5.5h14M3 10h14M3 14.5h14" />
    </Icon>
  )
}

export function CloseIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <path d="m5 5 10 10M15 5 5 15" />
    </Icon>
  )
}

export function DashboardIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <path d="M3 3.5h6v6H3zM11 3.5h6v3.5h-6zM11 9.5h6v7h-6zM3 12h6v4.5H3z" />
    </Icon>
  )
}

export function MonitorsIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <rect x="2.5" y="3.5" width="15" height="10" rx="1.5" />
      <path d="M7 16.5h6M5.5 9h2l1-2.5 2 5 1-2.5h3" />
    </Icon>
  )
}

export function SettingsIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <path d="M3 5.5h8m3 0h3M3 14.5h3m3 0h8" />
      <circle cx="12.5" cy="5.5" r="1.75" />
      <circle cx="7.5" cy="14.5" r="1.75" />
    </Icon>
  )
}

export function BellIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <path d="M5 13.5V9a5 5 0 0 1 10 0v4.5l1.5 2h-13zM8.25 17.5a1.75 1.75 0 0 0 3.5 0" />
    </Icon>
  )
}

export function LogOutIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <path d="M8 3.5H4.5v13H8M12.5 6.5 16 10l-3.5 3.5M16 10H8" />
    </Icon>
  )
}

export function EyeIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <path d="M1.5 10S4.5 4 10 4s8.5 6 8.5 6-3 6-8.5 6-8.5-6-8.5-6Z" />
      <circle cx="10" cy="10" r="2.5" />
    </Icon>
  )
}

export function EyeOffIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <path d="M8.2 4.2A8 8 0 0 1 10 4c5.5 0 8.5 6 8.5 6a14 14 0 0 1-2 2.8M5.2 5.2C2.8 6.8 1.5 10 1.5 10s3 6 8.5 6a8 8 0 0 0 4.8-1.6" />
      <path d="M8.2 8.2a2.5 2.5 0 0 0 3.6 3.6M2 2l16 16" />
    </Icon>
  )
}

export function GripIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <path d="M7.5 5h.01M12.5 5h.01M7.5 10h.01M12.5 10h.01M7.5 15h.01M12.5 15h.01" strokeWidth={3} />
    </Icon>
  )
}

export function PlusIcon(props: IconProps) {
  return (
    <Icon {...props}>
      <path d="M10 4v12M4 10h12" />
    </Icon>
  )
}
