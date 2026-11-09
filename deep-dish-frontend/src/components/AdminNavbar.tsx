import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { LayoutDashboard, Settings, Grid3X3, Users, CalendarDays, UserCog, LogOut } from 'lucide-react';
import ThemeToggle from '@/components/ThemeToggle';
import logo from '@/assets/logo/logo-sem-fundo.png';

const sideLinks = [
  { to: '/restaurant/dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/restaurant/queue', label: 'Fila', icon: Users },
  { to: '/restaurant/reservations', label: 'Reservas', icon: CalendarDays },
  { to: '/restaurant/tables', label: 'Mesas', icon: Grid3X3 },
  { to: '/restaurant/staff', label: 'Equipe', icon: UserCog },
  { to: '/restaurant/settings', label: 'Configurações', icon: Settings },
];

const AdminNavbar: React.FC = () => {
  const { logout } = useAuth();
  const location = useLocation();

  return (
    <>
      {/* Desktop sidebar */}
      <aside className="fixed left-0 top-0 bottom-0 z-40 hidden w-60 flex-col bg-dark-surface text-dark-surface-foreground md:flex print:hidden">
        <div className="flex h-16 items-center gap-2.5 px-5 border-b border-white/[0.06]">
          <div className="h-8 w-8 rounded-lg overflow-hidden">
            <img src={logo} alt="Logo Deep Dish" className="h-full w-full object-cover" />
          </div>
          <span className="font-display text-lg font-bold">Deep Dish</span>
        </div>
        <nav className="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto scrollbar-thin">
          {sideLinks.map(l => {
            const active = location.pathname === l.to;
            return (
              <Link
                key={l.to}
                to={l.to}
                className={`relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-200 ${
                  active
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'text-dark-surface-foreground/60 hover:bg-white/[0.06] hover:text-dark-surface-foreground'
                }`}
              >
                <l.icon className="h-[18px] w-[18px]" />
                {l.label}
              </Link>
            );
          })}
        </nav>
        <div className="border-t border-white/[0.06] p-3 space-y-1">
          <div className="flex items-center justify-between px-3 py-1">
            <span className="text-xs text-dark-surface-foreground/40">Tema</span>
            <ThemeToggle variant="light" />
          </div>
          <button
            onClick={logout}
            className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-dark-surface-foreground/60 hover:bg-white/[0.06] hover:text-dark-surface-foreground transition-colors duration-200"
          >
            <LogOut className="h-[18px] w-[18px]" />
            Sair
          </button>
        </div>
      </aside>

      {/* Mobile top bar */}
      <header className="fixed top-0 left-0 right-0 z-50 bg-dark-surface text-dark-surface-foreground md:hidden border-b border-white/[0.06] print:hidden">
        <div className="flex h-14 items-center justify-between px-4">
          <span className="font-display text-lg font-bold">Deep Dish</span>
          <div className="flex items-center gap-1">
            <ThemeToggle variant="light" />
            <button
              onClick={logout}
              className="h-9 w-9 flex items-center justify-center rounded-lg text-dark-surface-foreground/60 hover:text-primary transition-colors"
            >
              <LogOut className="h-[18px] w-[18px]" />
            </button>
          </div>
        </div>
      </header>

      {/* Mobile bottom nav */}
      <nav className="fixed bottom-0 left-0 right-0 z-50 bg-dark-surface text-dark-surface-foreground md:hidden border-t border-white/[0.06] safe-area-bottom print:hidden">
        <div className="flex items-center justify-around py-1.5 px-2">
          {sideLinks.slice(0, 5).map(l => {
            const active = location.pathname === l.to;
            return (
              <Link
                key={l.to}
                to={l.to}
                className={`relative flex flex-col items-center gap-0.5 min-w-[48px] min-h-[44px] justify-center px-2 py-1 text-[10px] rounded-xl transition-all duration-200 ${
                  active
                    ? 'text-primary'
                    : 'text-dark-surface-foreground/50 active:scale-95'
                }`}
              >
                {active && (
                  <span className="absolute -top-1.5 h-0.5 w-5 rounded-full bg-primary" />
                )}
                <l.icon className={`h-5 w-5 transition-transform duration-200 ${active ? 'scale-110' : ''}`} />
                <span className={`truncate ${active ? 'font-medium' : ''}`}>{l.label}</span>
              </Link>
            );
          })}
        </div>
      </nav>
    </>
  );
};

export default AdminNavbar;
