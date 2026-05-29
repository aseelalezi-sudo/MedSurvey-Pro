import { DoorOpen, Stethoscope, Building2, Pill, ClipboardCheck, Users, Activity, Heart, FileText, type LucideIcon } from "lucide-react";

export const SECTION_ICONS: Record<string, LucideIcon> = {
  "door-open": DoorOpen,
  "stethoscope": Stethoscope,
  "building": Building2,
  "pill": Pill,
  "clipboard-check": ClipboardCheck,
  "users": Users,
  "activity": Activity,
  "heart": Heart,
  "file-text": FileText,
};

export const SECTION_ICON_KEYS = Object.keys(SECTION_ICONS);
