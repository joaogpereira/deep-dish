import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { Home, Search, CalendarDays, Users, LogOut } from 'lucide-react';
import ThemeToggle from '@/components/ThemeToggle';
import logo from '@/assets/logo/logo-sem-fundo.png';

const links = [
  { to: '/app', label: 'Início', icon: Home },
  { to: '/app/search', label: 'Buscar', icon: Search },
  { to: '/app/restaurants', label: 'Restaurantes', icon: Users },
  { to: '/app/queue', label: 'Fila', icon: CalendarDays },
];

const AppNavbar: React.FC = () => {
  const { logout, user } = useAuth();
  const location = useLocation();

  return (
    <>
      <header className="fixed top-0 left-0 right-0 z-50 bg-card/80 backdrop-blur-xl border-b border-border/60 safe-area-top">
        <div className="container mx-auto flex h-14 items-center justify-between px-4">
          <Link to="/app" className="flex items-center gap-2.5 group">
            <div className="h-8 w-8 rounded-lg overflow-hidden transition-transform duration-200 ease-out-expo group-hover:scale-105">
              <img src={logo} alt="Logo Deep Dish" className="h-full w-full object-cover" />
            </div>
            <span className="font-display text-lg font-bold text-foreground">Deep Dish</span>
          </Link>
          <div className="flex items-center gap-1">
            <span className="text-sm text-muted-foreground hidden sm:block mr-2">
              Olá, {user?.name?.split(' ')[0]}
            </span>
            <ThemeToggle variant="dark" />
            <button
              onClick={logout}
              className="h-9 w-9 flex items-center justify-center rounded-lg text-muted-foreground hover:text-primary hover:bg-primary/5 transition-colors duration-200"
              title="Sair"
            >
              <LogOut className="h-[18px] w-[18px]" />
            </button>
          </div>
        </div>
      </header>

      <nav className="fixed bottom-0 left-0 right-0 z-50 bg-card/80 backdrop-blur-xl border-t border-border/60 md:hidden safe-area-bottom">
        <div className="flex items-center justify-around py-1.5 px-2">
          {links.map(l => {
            const active = location.pathname === l.to;
            return (
              <Link
                key={l.to}
                to={l.to}
                className={`relative flex flex-col items-center gap-0.5 min-w-[56px] min-h-[44px] justify-center px-3 py-1 text-xs rounded-xl transition-all duration-200 ${
                  active
                    ? 'text-primary'
                    : 'text-muted-foreground active:scale-95'
                }`}
              >
                {active && (
                  <span className="absolute -top-1.5 h-0.5 w-5 rounded-full bg-primary" />
                )}
                <l.icon className={`h-5 w-5 transition-transform duration-200 ${active ? 'scale-110' : ''}`} />
                <span className={`transition-colors ${active ? 'font-medium' : ''}`}>{l.label}</span>
              </Link>
            );
          })}
        </div>
      </nav>
    </>
  );
};

export default AppNavbar;
