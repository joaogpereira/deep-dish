import React from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import PublicNavbar from '@/components/PublicNavbar';
import { isRotaDeAutenticacao } from '@/lib/rotas';

const PublicLayout: React.FC = () => {
  const { pathname } = useLocation();
  const isAuthPage = isRotaDeAutenticacao(pathname);

  return (
    <div className="min-h-screen bg-background">
      {!isAuthPage && <PublicNavbar />}
      <main>
        <Outlet />
      </main>
    </div>
  );
};

export default PublicLayout;
