import React from 'react';
import { Outlet } from 'react-router-dom';
import AdminNavbar from '@/components/AdminNavbar';

const AdminLayout: React.FC = () => (
  <div className="min-h-screen bg-background">
    <AdminNavbar />
    <main className="pt-14 pb-20 md:pt-0 md:pb-0 md:ml-60 print:m-0 print:p-0">
      <div className="container mx-auto px-4 py-6 md:py-8 print:p-0">
        <Outlet />
      </div>
    </main>
  </div>
);

export default AdminLayout;
