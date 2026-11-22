import React from 'react';
import { Outlet } from 'react-router-dom';
import AppNavbar from '@/components/AppNavbar';

const AppLayout: React.FC = () => (
  <div className="min-h-screen bg-background">
    <AppNavbar />
    <main className="pt-[calc(3.5rem+env(safe-area-inset-top))] pb-[calc(5rem+env(safe-area-inset-bottom))] md:pb-6">
      <div className="container mx-auto px-4 py-6">
        <Outlet />
      </div>
    </main>
  </div>
);

export default AppLayout;
