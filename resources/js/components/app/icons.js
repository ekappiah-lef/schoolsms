import {
    BedDouble,
    BookOpen,
    CalendarClock,
    GraduationCap,
    KeyRound,
    Banknote,
    LayoutDashboard,
    Layers,
    NotebookPen,
    Plus,
    School,
    ScrollText,
    Settings,
    SlidersHorizontal,
    ChartColumn,
    ReceiptText,
    Utensils,
    ShoppingBag,
    UserCog,
    Users,
    Wallet,
    Circle,
} from 'lucide-react';

/** Icon names sent by App\Support\Navigation mapped to components. */
const icons = {
    dashboard: LayoutDashboard,
    users: Users,
    'graduation-cap': GraduationCap,
    'notebook-pen': NotebookPen,
    'calendar-clock': CalendarClock,
    'scroll-text': ScrollText,
    wallet: Wallet,
    banknote: Banknote,
    plus: Plus,
    'user-cog': UserCog,
    school: School,
    layers: Layers,
    'book-open': BookOpen,
    'bed-double': BedDouble,
    'key-round': KeyRound,
    settings: Settings,
    sliders: SlidersHorizontal,
    chart: ChartColumn,
    receipt: ReceiptText,
    utensils: Utensils,
    'shopping-bag': ShoppingBag,
};

export function navIcon(name) {
    return icons[name] ?? Circle;
}
